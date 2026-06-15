<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    // Admin: get all communities
    public function index()
    {
        return Community::with('creator', 'members')->get();
    }

    // Admin: create a community
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'treasurer_id' => 'required|exists:users,id',
        ]);

        $community = Community::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => auth()->id(),
        ]);

        // Attach treasurer
        $community->members()->attach($request->treasurer_id, ['role' => 'treasurer']);

        return response()->json($community->load('members'), 201);
    }

    // Get a single community with members
    public function show(int $id)
    {
        $community = Community::with('members')->findOrFail($id);
        return response()->json($community);
    }

    // Admin: delete a community
    public function destroy(int $id)
    {
        Community::findOrFail($id)->delete();
        return response()->json(['message' => 'Community deleted']);
    }

    // Get communities for the logged in user
    public function myCommunities(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return Community::with('members')->get();
        }

        return $user->communities()->with('members')->get();
    }

    // Admin: add a member to a community
    public function addMember(Request $request, int $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $community = Community::findOrFail($id);
        $community->members()->syncWithoutDetaching([
            $request->user_id => ['role' => 'member']
        ]);

        return response()->json(['message' => 'Member added']);
    }

    // Admin: remove a member from a community
    public function removeMember(Request $request, int $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $community = Community::findOrFail($id);
        $community->members()->detach($request->user_id);

        return response()->json(['message' => 'Member removed']);
    }

    // Get cross-community activity for a user (fraud prevention)
    public function userActivity(int $userId)
    {
        $user = User::with(['communities' => function ($q) {
            $q->withPivot('role');
        }])->findOrFail($userId);

        $loans = \App\Models\Loan::where('user_id', $userId)
            ->whereIn('status', ['active', 'overdue'])
            ->with('community')
            ->get();

        return response()->json([
            'user' => $user,
            'communities' => $user->communities,
            'active_loans' => $loans,
        ]);
    }
}
