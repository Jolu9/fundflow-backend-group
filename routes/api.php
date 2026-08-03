<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionRequestController;
use App\Http\Controllers\RepaymentRequestController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\JoinRequestController;
use App\Http\Controllers\CommunityRequestController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\CycleController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'phone' => 'nullable|string',
    ]);

    $user = \App\Models\User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        'phone' => $request->phone,
        'role' => 'member',
        'status' => 'active',
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'role' => $user->role,
        'user' => $user,
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

    Route::post('/communities/join-by-code', function (\Illuminate\Http\Request $request) {
        $request->validate(['invite_code' => 'required|string']);

        $community = \App\Models\Community::where('invite_code', $request->invite_code)->first();

        if (!$community) {
            return response()->json(['message' => 'Invalid invite code.'], 404);
        }

        $userId = $request->user()->id;

        $alreadyMember = $community->members()->where('user_id', $userId)->exists();
        if ($alreadyMember) {
            return response()->json(['message' => 'You are already a member of this community.'], 409);
        }

        $existing = \App\Models\JoinRequest::where('user_id', $userId)
            ->where('community_id', $community->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You already have a pending or approved request for this group.'], 409);
        }

        \App\Models\JoinRequest::create([
            'community_id' => $community->id,
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Request sent. Awaiting treasurer approval.'], 201);
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

        $communityId = $request->user()->communities()->first()?->id;

        if (!$communityId) {
            $communityId = \Illuminate\Support\Facades\DB::table('community_user')
                ->where('user_id', $request->user()->id)
                ->value('community_id');
        }

        if (!$communityId) {
            return response()->json(['message' => 'You are not a member of any community.'], 422);
        }

        $loan = \App\Models\Loan::create([
            'user_id'       => $request->user()->id,
            'community_id'  => $communityId,
            'amount'        => $request->amount,
            'interest_rate' => 0,
            'total_due'     => $request->amount,
            'amount_paid'   => 0,
            'status'        => 'pending',
            'purpose'       => $request->purpose,
        ]);

        return response()->json($loan, 201);
    });

    // Member loans
    Route::get('/member/loans', function (\Illuminate\Http\Request $request) {
        return response()->json(
            \App\Models\Loan::where('user_id', $request->user()->id)->latest()->get()
        );
    });

    // Repayments (treasurer direct entry — instant, trusted)
    Route::get('/repayments', function (\Illuminate\Http\Request $request) {
        $communityId = $request->user()->communities()->first()?->id;
        $loanIds = \App\Models\Loan::where('community_id', $communityId)->pluck('id');
        return response()->json(
            \App\Models\Repayment::with('loan.user', 'recordedBy')
                ->whereIn('loan_id', $loanIds)
                ->latest()
                ->get()
        );
    });

    Route::post('/repayments', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $repayment = \App\Models\Repayment::create([
            'loan_id'     => $request->loan_id,
            'recorded_by' => $request->user()->id,
            'amount'      => $request->amount,
            'notes'       => $request->notes,
        ]);

        $loan = \App\Models\Loan::find($request->loan_id);
        $loan->amount_paid += $request->amount;
        if ($loan->amount_paid >= $loan->total_due) {
            $loan->status = 'completed';
        }
        $loan->save();

        return response()->json($repayment->load('loan.user', 'recordedBy'), 201);
    });

    // Member repayments — confirmed history only
    Route::get('/member/repayments', function (\Illuminate\Http\Request $request) {
        $loanIds = \App\Models\Loan::where('user_id', $request->user()->id)->pluck('id');
        return response()->json(
            \App\Models\Repayment::whereIn('loan_id', $loanIds)->with('loan', 'recordedBy')->latest()->get()
        );
    });

    // Repayment Requests
    Route::get('/repayment-requests/mine', [RepaymentRequestController::class, 'mine']);
    Route::get('/repayment-requests', [RepaymentRequestController::class, 'index']);
    Route::post('/repayment-requests', [RepaymentRequestController::class, 'store']);
    Route::post('/repayment-requests/{id}/confirm', [RepaymentRequestController::class, 'confirm']);
    Route::post('/repayment-requests/{id}/reject', [RepaymentRequestController::class, 'reject']);

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
    Route::get('/export/report', [ExportController::class, 'report']);

    // Cycles (Chilimba)
    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::post('/cycles/contribution-amount', [CycleController::class, 'setContributionAmount']);
    Route::post('/cycles/toggle-chilimba', [CycleController::class, 'toggleChilimba']);
    Route::post('/cycles/{id}/assign', [CycleController::class, 'assignRecipient']);
    Route::post('/cycles/{id}/complete', [CycleController::class, 'complete']);
    Route::get('/member/cycles', [CycleController::class, 'memberCycles']);

    // Communities
    Route::get('/communities/my', [CommunityController::class, 'myCommunities']);

    Route::get('/communities/explore', function () {
        return \App\Models\Community::withCount(['members as member_count' => function ($q) {
            $q->where('community_user.role', 'member');
        }])->get(['id', 'name', 'description']);
    });

    Route::get('/communities', [CommunityController::class, 'index']);
    Route::post('/communities', [CommunityController::class, 'store']);

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

    Route::get('/join-requests/mine', function (\Illuminate\Http\Request $request) {
        return \App\Models\JoinRequest::where('user_id', $request->user()->id)->get();
    });
    Route::get('/join-requests', [JoinRequestController::class, 'index']);
    Route::post('/join-requests', [JoinRequestController::class, 'store']);
    Route::patch('/join-requests/{id}', [JoinRequestController::class, 'update']);

    Route::get('/community-requests', [CommunityRequestController::class, 'index']);
    Route::post('/community-requests', [CommunityRequestController::class, 'store']);
    Route::post('/community-requests/{id}/approve', [CommunityRequestController::class, 'approve']);
    Route::post('/community-requests/{id}/reject', [CommunityRequestController::class, 'reject']);
});
