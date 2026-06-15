<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContributionRequestController extends Controller
{
    // Member: submit a contribution request
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reference_note' => 'nullable|string',
        ]);

        $communityId = DB::table('community_user')
            ->where('user_id', Auth::id())
            ->value('community_id');

        $req = \App\Models\ContributionRequest::create([
            'user_id' => Auth::id(),
            'community_id' => $communityId,
            'amount' => $request->amount,
            'reference_note' => $request->reference_note,
            'status' => 'pending',
        ]);

        return response()->json($req, 201);
    }

    // Treasurer: get pending requests for their community
    public function index(Request $request)
    {
        $communityId = DB::table('community_user')
            ->where('user_id', Auth::id())
            ->value('community_id');

        return \App\Models\ContributionRequest::with('user')
            ->where('community_id', $communityId)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    // Treasurer: confirm a request → creates actual contribution
    public function confirm($id)
    {
        $req = \App\Models\ContributionRequest::findOrFail($id);
        $req->update(['status' => 'confirmed']);

        $contribution = Contribution::create([
            'user_id' => $req->user_id,
            'recorded_by' => Auth::id(),
            'amount' => $req->amount,
            'contribution_date' => now()->toDateString(),
            'notes' => $req->reference_note ? "Mobile money ref: {$req->reference_note}" : "Confirmed via mobile money",
            'community_id' => $req->community_id,
        ]);

        return response()->json($contribution->load('user', 'recorder'), 201);
    }
}
