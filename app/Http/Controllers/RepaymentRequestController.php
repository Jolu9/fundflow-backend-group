<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\RepaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepaymentRequestController extends Controller
{
    // Member: submit a repayment request
    public function store(Request $request)
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'reference_note' => 'nullable|string',
        ]);

        $loan = Loan::where('id', $request->loan_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->firstOrFail();

        $req = RepaymentRequest::create([
            'user_id' => Auth::id(),
            'loan_id' => $loan->id,
            'community_id' => $loan->community_id,
            'amount' => $request->amount,
            'reference_note' => $request->reference_note,
            'status' => 'pending',
        ]);

        return response()->json($req->load('loan'), 201);
    }

    // Treasurer: get pending requests for their community
    public function index(Request $request)
    {
        $communityId = Auth::user()->communities()->first()?->id;

        if (!$communityId) {
            $communityId = DB::table('community_user')
                ->where('user_id', Auth::id())
                ->value('community_id');
        }

        return RepaymentRequest::with('user', 'loan')
            ->where('community_id', $communityId)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    // Member: see all of their own requests, any status
    public function mine(Request $request)
    {
        return RepaymentRequest::where('user_id', Auth::id())
            ->with('loan')
            ->latest()
            ->get();
    }

    // Treasurer: confirm -> creates actual repayment, applies to loan
    public function confirm($id)
    {
        $req = RepaymentRequest::findOrFail($id);
        $req->update(['status' => 'confirmed']);

        $repayment = Repayment::create([
            'loan_id' => $req->loan_id,
            'recorded_by' => Auth::id(),
            'amount' => $req->amount,
            'notes' => $req->reference_note ? "Mobile money ref: {$req->reference_note}" : "Confirmed via mobile money",
        ]);

        $loan = Loan::find($req->loan_id);
        $loan->amount_paid += $req->amount;
        if ($loan->amount_paid >= $loan->total_due) {
            $loan->status = 'completed';
        }
        $loan->save();

        return response()->json($repayment->load('loan.user', 'recordedBy'), 201);
    }

    // Treasurer: reject
    public function reject($id)
    {
        $req = RepaymentRequest::findOrFail($id);
        $req->update(['status' => 'rejected']);
        return response()->json($req);
    }
}
