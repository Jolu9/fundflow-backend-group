<?php

namespace App\Http\Controllers;

use App\Models\JoinRequest;
use App\Models\Community;
use Illuminate\Http\Request;

class JoinRequestController extends Controller
{
    // Member: request to join a community
    public function store(Request $request)
    {
        $request->validate([
            'community_id' => 'required|exists:communities,id',
        ]);

        $existing = JoinRequest::where('user_id', auth()->id())
            ->where('community_id', $request->community_id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Request already exists'], 409);
        }

        $joinRequest = JoinRequest::create([
            'community_id' => $request->community_id,
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        return response()->json($joinRequest, 201);
    }

    // Treasurer: get pending join requests for their community
    public function index(Request $request)
    {
        $user = $request->user();

        $communityId = $request->query('community_id');

        return JoinRequest::where('community_id', $communityId)
            ->where('status', 'pending')
            ->with('user')
            ->get();
    }

    // Treasurer: approve or reject
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $joinRequest = JoinRequest::findOrFail($id);
        $joinRequest->update(['status' => $request->status]);

        if ($request->status === 'approved') {
            $joinRequest->community->members()->syncWithoutDetaching([
                $joinRequest->user_id => ['role' => 'member']
            ]);
        }

        return response()->json($joinRequest);
    }
}
