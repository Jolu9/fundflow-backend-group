<?php

namespace App\Http\Controllers;

use App\Models\CommunityRequest;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityRequestController extends Controller
{
    // Member: submit a request to create a community
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $existing = CommunityRequest::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You already have a pending community request.'], 409);
        }

        $req = CommunityRequest::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response()->json($req->load('user'), 201);
    }

    // Admin: get all pending requests
    public function index()
    {
        return CommunityRequest::with('user')
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    // Admin: approve → creates community, assigns treasurer
    public function approve($id)
    {
        $req = CommunityRequest::findOrFail($id);

        $community = Community::create([
            'name' => $req->name,
            'description' => $req->description,
            'created_by' => Auth::id(),
        ]);

        $community->members()->attach($req->user_id, ['role' => 'treasurer']);

        // Upgrade user role to treasurer
        User::where('id', $req->user_id)->update(['role' => 'treasurer']);

        $req->update(['status' => 'approved']);

        return response()->json($community->load('members'), 201);
    }

    // Admin: reject
    public function reject($id)
    {
        $req = CommunityRequest::findOrFail($id);
        $req->update(['status' => 'rejected']);
        return response()->json(['message' => 'Request rejected.']);
    }
}
