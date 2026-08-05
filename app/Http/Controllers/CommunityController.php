<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class CommunityController extends Controller
{
    public function index()
    {
        return Community::with(['members' => function ($q) {
            $q->withPivot('role');
        }])->get();
    }

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
            'created_by' => Auth::id(),
        ]);

        $community->members()->attach($request->treasurer_id, ['role' => 'treasurer']);

        return response()->json($community->load(['members' => function ($q) {
            $q->withPivot('role');
        }]), 201);
    }

    public function show(int $id)
    {
        $community = Community::with(['members' => function ($q) {
            $q->withPivot('role');
        }])->findOrFail($id);
        return response()->json($community);
    }

    public function destroy(int $id)
    {
        Community::findOrFail($id)->delete();
        return response()->json(['message' => 'Community deleted']);
    }

    public function myCommunities(Request $request)
    {
        $user = $request->user();

        $communities = $user->role === 'admin'
            ? Community::with(['members' => function ($q) {
                $q->withPivot('role');
            }])->get()
            : $user->communities()->with(['members' => function ($q) {
                $q->withPivot('role');
            }])->get();

        return $communities->map(function ($community) {
            $currentFund = $community->currentFund();
            $totalContributed = $community->contributions->sum('amount');
            $totalDisbursed = $community->loans->whereNotIn('status', ['pending', 'rejected'])->sum('amount');

            $data = $community->toArray();
            $data['fund_summary'] = [
                'current_fund' => $currentFund,
                'total_contributed' => $totalContributed,
                'total_disbursed' => $totalDisbursed,
            ];
            return $data;
        });
    }

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

    public function removeMember(Request $request, int $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $community = Community::findOrFail($id);
        $community->members()->detach($request->user_id);

        return response()->json(['message' => 'Member removed']);
    }

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
