<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoanController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/repayments', function (\Illuminate\Http\Request $request) {
    return response()->json(
        \App\Models\Repayment::with('loan.user', 'recordedBy')->latest()->get()
    );
});

Route::get('/export/loans', function () {
    $loans = \App\Models\Loan::with('user')->get();

    $filename = 'fundflow-loans-' . date('Y-m-d') . '.csv';

    $headers = [
        'Content-Type'        => 'text/csv',
        'Content-Disposition' => "attachment; filename={$filename}",
    ];

    $callback = function () use ($loans) {
        $file = fopen('php://output', 'w');

        // Header row
        fputcsv($file, ['Member', 'Amount (K)', 'Interest Rate (%)', 'Total Due (K)', 'Amount Paid (K)', 'Remaining (K)', 'Due Date', 'Status', 'Applied On']);

        foreach ($loans as $loan) {
            fputcsv($file, [
                $loan->user->name ?? 'Unknown',
                $loan->amount,
                $loan->interest_rate,
                $loan->total_due,
                $loan->amount_paid,
                $loan->total_due - $loan->amount_paid,
                $loan->due_date,
                $loan->status,
                $loan->created_at->format('Y-m-d'),
            ]);
        }

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
});

Route::post('/repayments', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'loan_id' => 'required|exists:loans,id',
        'amount' => 'required|numeric|min:1',
        'notes' => 'nullable|string',
    ]);

    $repayment = \App\Models\Repayment::create([
        'loan_id' => $request->loan_id,
        'recorded_by' => $request->user()->id,
        'amount' => $request->amount,
        'notes' => $request->notes,
    ]);

    $loan = \App\Models\Loan::find($request->loan_id);
    $loan->amount_paid += $request->amount;
    if ($loan->amount_paid >= $loan->total_due) {
        $loan->status = 'completed';
    }
    $loan->save();

    return response()->json($repayment->load('loan.user', 'recordedBy'), 201);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/activity-logs', function () {
        return response()->json(
            \App\Models\ActivityLog::with('user')->latest()->take(50)->get()
        );
    });
    Route::post('/loan-applications', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string',
        ]);

        $loan = \App\Models\Loan::create([
            'user_id' => $request->user()->id,
            'amount' => $request->amount,
            'interest_rate' => 0,
            'total_due' => $request->amount,
            'amount_paid' => 0,
            'status' => 'pending',
            'purpose' => $request->purpose,
        ]);

        return response()->json($loan, 201);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/member/repayments', function (\Illuminate\Http\Request $request) {
        $loanIds = \App\Models\Loan::where('user_id', $request->user()->id)->pluck('id');
        return response()->json(
            \App\Models\Repayment::whereIn('loan_id', $loanIds)->with('loan', 'recordedBy')->latest()->get()
        );
    });
    Route::get('/stats', [\App\Http\Controllers\StatsController::class, 'index']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('loans', LoanController::class);
    Route::get('/member/loans', function (\Illuminate\Http\Request $request) {
        return response()->json(
            \App\Models\Loan::where('user_id', $request->user()->id)->latest()->get()
        );
    });
});
