<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionRequestController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\JoinRequestController;
use App\Http\Controllers\CommunityRequestController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'invite_code' => 'nullable|string',
    ]);

    $user = \App\Models\User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        'role' => 'member',
        'status' => 'active',
    ]);

    $joinedCommunity = null;
    if ($request->invite_code) {
        $community = \App\Models\Community::where('invite_code', $request->invite_code)->first();
        if ($community) {
            $community->members()->attach($user->id, ['role' => 'member']);
            $joinedCommunity = $community;
        }
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'role' => $user->role,
        'user' => $user,
        'community' => $joinedCommunity,
    ], 201);
});

Route::get('/communities/invite/{code}', function ($code) {
    $community = \App\Models\Community::where('invite_code', $code)->first();
    if (!$community) return response()->json(['message' => 'Invalid invite code.'], 404);
    return response()->json(['name' => $community->name, 'description' => $community->description]);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Join community by invite code (after registration)
    Route::post('/communities/join-by-code', function (\Illuminate\Http\Request $request) {
        $request->validate(['invite_code' => 'required|string']);

        $community = \App\Models\Community::where('invite_code', $request->invite_code)->first();

        if (!$community) {
            return response()->json(['message' => 'Invalid invite code.'], 404);
        }

        $alreadyMember = $community->members()->where('user_id', $request->user()->id)->exists();
        if ($alreadyMember) {
            return response()->json(['message' => 'You are already a member of this community.'], 409);
        }

        $community->members()->attach($request->user()->id, ['role' => 'member']);

        return response()->json(['message' => 'Joined successfully.', 'community' => $community]);
    });

    // Users & Loans
    Route::apiResource('users', UserController::class);
    Route::apiResource('loans', LoanController::class);

    // Loan application (member)
    Route::post('/loan-applications', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string',
        ]);

        $communityUser = \Illuminate\Support\Facades\DB::table('community_user')
            ->where('user_id', $request->user()->id)
            ->first();

        $loan = \App\Models\Loan::create([
            'user_id' => $request->user()->id,
            'community_id' => $communityUser?->community_id ?? null,
            'amount' => $request->amount,
            'interest_rate' => 0,
            'total_due' => $request->amount,
            'amount_paid' => 0,
            'status' => 'pending',
            'purpose' => $request->purpose,
        ]);

        return response()->json($loan, 201);
    });

    // Member loans
    Route::get('/member/loans', function (\Illuminate\Http\Request $request) {
        return response()->json(
            \App\Models\Loan::where('user_id', $request->user()->id)->latest()->get()
        );
    });

    // Repayments
    Route::get('/repayments', function (\Illuminate\Http\Request $request) {
        return response()->json(
            \App\Models\Repayment::with('loan.user', 'recordedBy')->latest()->get()
        );
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

    // Member repayments
    Route::get('/member/repayments', function (\Illuminate\Http\Request $request) {
        $loanIds = \App\Models\Loan::where('user_id', $request->user()->id)->pluck('id');
        return response()->json(
            \App\Models\Repayment::whereIn('loan_id', $loanIds)->with('loan', 'recordedBy')->latest()->get()
        );
    });

    // Contributions
    Route::get('/contributions', [ContributionController::class, 'index']);
    Route::post('/contributions', [ContributionController::class, 'store']);
    Route::get('/member/contributions', [ContributionController::class, 'myContributions']);

    // Contribution Requests
    Route::get('/contribution-requests', [ContributionRequestController::class, 'index']);
    Route::post('/contribution-requests', [ContributionRequestController::class, 'store']);
    Route::post('/contribution-requests/{id}/confirm', [ContributionRequestController::class, 'confirm']);

    // Stats & Logs
    Route::get('/stats', [\App\Http\Controllers\StatsController::class, 'index']);
    Route::get('/activity-logs', function () {
        return response()->json(
            \App\Models\ActivityLog::with('user')->latest()->take(50)->get()
        );
    });

    // Export
    Route::get('/export/loans', function () {
        $loans = \App\Models\Loan::with('user')->get();
        $filename = 'fundflow-loans-' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];
        $callback = function () use ($loans) {
            $file = fopen('php://output', 'w');
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

    // Communities — order matters: specific routes before {id}
    Route::get('/communities/my', [CommunityController::class, 'myCommunities']);
    Route::get('/communities', [CommunityController::class, 'index']);
    Route::post('/communities', [CommunityController::class, 'store']);

    // Self-serve: create community and become treasurer
    Route::post('/communities/create', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $request->user()->update(['role' => 'treasurer']);

        $community = \App\Models\Community::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        $community->members()->attach($request->user()->id, ['role' => 'treasurer']);

        $token = $request->user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'community' => $community,
            'token' => $token,
            'role' => 'treasurer',
        ], 201);
    });

    // Treasurer: generate invite code
    Route::post('/communities/{id}/generate-invite', function ($id) {
        $community = \App\Models\Community::findOrFail($id);
        $code = strtoupper(\Illuminate\Support\Str::random(8));
        $community->update(['invite_code' => $code]);
        return response()->json(['invite_code' => $code]);
    });

    Route::get('/communities/{id}', [CommunityController::class, 'show']);
    Route::delete('/communities/{id}', [CommunityController::class, 'destroy']);
    Route::post('/communities/{id}/add-member', [CommunityController::class, 'addMember']);
    Route::post('/communities/{id}/remove-member', [CommunityController::class, 'removeMember']);
    Route::get('/users/{id}/activity', [CommunityController::class, 'userActivity']);

    // Join Requests
    Route::get('/join-requests', [JoinRequestController::class, 'index']);
    Route::post('/join-requests', [JoinRequestController::class, 'store']);
    Route::patch('/join-requests/{id}', [JoinRequestController::class, 'update']);

    // Community Requests
    Route::get('/community-requests', [CommunityRequestController::class, 'index']);
    Route::post('/community-requests', [CommunityRequestController::class, 'store']);
    Route::post('/community-requests/{id}/approve', [CommunityRequestController::class, 'approve']);
    Route::post('/community-requests/{id}/reject', [CommunityRequestController::class, 'reject']);
});
