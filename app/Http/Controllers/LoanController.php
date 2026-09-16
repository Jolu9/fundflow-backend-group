<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

    private function markOverdue()
    {
        $newlyOverdue = Loan::where('status', 'active')
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->get();

        foreach ($newlyOverdue as $loan) {
            $penalty = round($loan->amount * 0.05, 2); // 5% penalty, one-time
            $loan->update([
                'status' => 'overdue',
                'penalty_amount' => $penalty,
                'total_due' => $loan->total_due + $penalty,
            ]);
            $this->log('penalty_applied', $loan, "5% penalty of K{$penalty} applied for overdue loan");
        }
    }

    public function index()
    {
        $this->markOverdue();
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

        $existingLoan = Loan::where('user_id', $request->user_id)
            ->whereIn('status', ['pending', 'active', 'overdue'])
            ->first();

        if ($existingLoan) {
            return response()->json([
                'message' => 'This member already has an outstanding loan and cannot be issued another until it is fully repaid.'
            ], 422);
        }

        $communityId = DB::table('community_user')
            ->where('user_id', $request->user_id)
            ->value('community_id');

        if (!$communityId) {
            return response()->json(['message' => 'This member is not part of any community.'], 422);
        }

        $amount = $request->amount;

        return DB::transaction(function () use ($request, $communityId, $amount) {
            $community = \App\Models\Community::where('id', $communityId)->lockForUpdate()->first();
            $available = $community->currentFund();

            if ($amount > $available) {
                return response()->json([
                    'message' => "Insufficient group fund. Available: K{$available}, requested: K{$amount}"
                ], 422);
            }

            $interest  = $request->interest_rate;
            $total_due = $amount + ($amount * $interest / 100);

            $loan = Loan::create([
                'user_id'       => $request->user_id,
                'community_id'  => $communityId,
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
        });
    }

    public function show(Loan $loan)
    {
        $this->markOverdue();
        return response()->json($loan->load('user'));
    }

    public function update(Request $request, Loan $loan)
    {
        $oldStatus = $loan->status;
        $newStatus = $request->input('status', $oldStatus);

        $isApproving = $oldStatus === 'pending' && in_array($newStatus, ['active', 'overdue']);

        if ($isApproving) {
            $amount = $request->input('amount', $loan->amount);

            $result = DB::transaction(function () use ($loan, $amount) {
                $community = \App\Models\Community::where('id', $loan->community_id)->lockForUpdate()->first();
                $available = $community->currentFund();

                if ($amount > $available) {
                    $loan->update([
                        'review_note' => "Approval attempted on " . now()->format('M j, Y') . " but the group fund only had K{$available} available for your K{$amount} request."
                    ]);

                    return response()->json([
                        'message' => "Insufficient group fund. Available: K{$available}, requested: K{$amount}"
                    ], 422);
                }

                return null;
            });

            if ($result) {
                return $result;
            }
        }

        $loan->update($request->only([
            'status',
            'amount_paid',
            'interest_rate',
            'due_date',
            'total_due'
        ]));

        if ($newStatus === 'active') {
            $loan->update(['review_note' => null]);
        }

        if ($newStatus === 'rejected') {
            $loan->update(['review_note' => $request->input('rejection_reason')]);
        }
        if ($oldStatus !== $loan->fresh()->status) {
            $this->log('status_changed', $loan, "Status: {$oldStatus} → {$loan->status}");

            if (in_array($loan->status, ['active', 'rejected'])) {
                \App\Models\Notification::create([
                    'user_id' => $loan->user_id,
                    'type' => 'loan',
                    'status' => $loan->status,
                    'title' => $loan->status === 'active'
                        ? "Your loan of K" . number_format($loan->amount) . " was approved"
                        : "Your loan of K" . number_format($loan->amount) . " was rejected",
                    'subtitle' => $loan->status === 'active'
                        ? "Check your due date and repayment plan"
                        : ($loan->review_note ?? "Tap for details"),
                    'route' => '/member/loans',
                    'amount' => $loan->amount,
                    'notifiable_type' => Loan::class,
                    'notifiable_id' => $loan->id,
                ]);
            }
        }

        return response()->json($loan);
    }

    // Treasurer: fix a mistake on an already-active/overdue loan
    public function editTerms(Request $request, Loan $loan)
    {
        if (!in_array($loan->status, ['active', 'overdue'])) {
            return response()->json(['message' => 'Only active or overdue loans can be edited.'], 422);
        }

        $request->validate([
            'amount'        => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'due_date'      => 'required|date',
        ]);

        $newAmount = $request->amount;
        $amountChanged = bccomp((string) $newAmount, (string) $loan->amount, 2) !== 0;

        if ($amountChanged && $loan->amount_paid > 0) {
            return response()->json([
                'message' => 'Cannot change the loan amount after repayments have been made. You can still edit the interest rate and due date.'
            ], 422);
        }

        return DB::transaction(function () use ($request, $loan, $newAmount, $amountChanged) {
            if ($amountChanged) {
                $community = \App\Models\Community::where('id', $loan->community_id)->lockForUpdate()->first();
                // Add the loan's current amount back, since it's already counted as disbursed
                $available = $community->currentFund() + $loan->amount;

                if ($newAmount > $available) {
                    return response()->json([
                        'message' => "Insufficient group fund. Available: K{$available}, requested: K{$newAmount}"
                    ], 422);
                }
            }

            $oldAmount = $loan->amount;
            $oldInterest = $loan->interest_rate;
            $oldDueDate = $loan->due_date;

            $interest = $request->interest_rate;
            $penalty = $loan->penalty_amount ?? 0;
            $totalDue = $newAmount + ($newAmount * $interest / 100) + $penalty;

            $loan->update([
                'amount'        => $newAmount,
                'interest_rate' => $interest,
                'due_date'      => $request->due_date,
                'total_due'     => $totalDue,
            ]);

            $this->log(
                'loan_edited',
                $loan,
                "Amount: K{$oldAmount} → K{$newAmount}, Interest: {$oldInterest}% → {$interest}%, Due: {$oldDueDate} → {$request->due_date}"
            );

            return response()->json($loan->load('user'));
        });
    }

    public function destroy(Loan $loan)
    {
        $this->log('loan_deleted', $loan, "Loan ID {$loan->id} deleted");
        $loan->delete();
        return response()->json(['message' => 'Loan deleted']);
    }
}
