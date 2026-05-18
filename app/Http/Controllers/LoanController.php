<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    private function log(string $action, Loan $loan, ?string $details = null): void
    {
        if (Auth::check()) {
            ActivityLog::create([
                'user_id'    => Auth::id(),
                'action'     => $action,
                'model_type' => Loan::class,
                'model_id'   => $loan->id,
                'details'    => $details,
            ]);
        }
    }

    public function index()
    {
        return response()->json(Loan::with('user')->latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'       => 'required|exists:users,id',
            'amount'        => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'due_date'      => 'required|date',
            'purpose'       => 'nullable|string',
        ]);

        $amount    = $request->amount;
        $interest  = $request->interest_rate;
        $total_due = $amount + ($amount * $interest / 100);

        $loan = Loan::create([
            'user_id'       => $request->user_id,
            'amount'        => $amount,
            'interest_rate' => $interest,
            'total_due'     => $total_due,
            'amount_paid'   => 0,
            'status'        => 'active',
            'due_date'      => $request->due_date,
            'purpose'       => $request->purpose,
        ]);

        $this->log('loan_issued', $loan, "K{$amount} issued to user ID {$loan->user_id}");

        return response()->json($loan->load('user'), 201);
    }

    public function show(Loan $loan)
    {
        return response()->json($loan->load('user'));
    }

    public function update(Request $request, Loan $loan)
    {
        $oldStatus = $loan->status;

        $loan->update($request->only([
            'status',
            'amount_paid',
            'interest_rate',
            'due_date',
            'total_due'
        ]));

        if ($oldStatus !== $loan->fresh()->status) {
            $this->log('status_changed', $loan, "Status: {$oldStatus} → {$loan->status}");
        }

        return response()->json($loan);
    }

    public function destroy(Loan $loan)
    {
        $this->log('loan_deleted', $loan, "Loan ID {$loan->id} deleted");
        $loan->delete();
        return response()->json(['message' => 'Loan deleted']);
    }
}
