<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Community;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    public function index(Request $request)
    {
        $communityId = $request->user()->communities()->first()?->id;
        $cycles = Cycle::with('recipient')
            ->where('community_id', $communityId)
            ->orderBy('cycle_number')
            ->get();
        return response()->json($cycles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'payout_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'pot_amount' => 'nullable|numeric|min:0',
        ]);

        $communityId = $request->user()->communities()->first()?->id;
        $community = Community::findOrFail($communityId);
        $lastCycle = Cycle::where('community_id', $communityId)->max('cycle_number');

        $currentFund = $community->currentFund();
        $potAmount = $request->pot_amount ?? $currentFund;

        if ($potAmount > $currentFund) {
            return response()->json([
                'message' => 'Pot amount cannot exceed available fund (K' . number_format($currentFund, 2) . ')'
            ], 422);
        }

        $cycle = Cycle::create([
            'community_id' => $communityId,
            'cycle_number' => ($lastCycle ?? 0) + 1,
            'pot_amount' => $potAmount,
            'status' => 'pending',
            'payout_date' => $request->payout_date,
            'notes' => $request->notes,
        ]);

        return response()->json($cycle->load('recipient'), 201);
    }

    public function assignRecipient(Request $request, int $id)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
        ]);

        $cycle = Cycle::findOrFail($id);
        $cycle->update([
            'recipient_id' => $request->recipient_id,
            'status' => 'active',
        ]);

        return response()->json($cycle->load('recipient'));
    }

    public function complete(int $id)
    {
        $cycle = Cycle::findOrFail($id);
        $cycle->update(['status' => 'completed']);
        return response()->json($cycle->load('recipient'));
    }

    public function setContributionAmount(Request $request)
    {
        $request->validate([
            'contribution_amount' => 'required|numeric|min:1',
        ]);

        $communityId = $request->user()->communities()->first()?->id;
        $community = Community::findOrFail($communityId);
        $community->update(['contribution_amount' => $request->contribution_amount]);

        return response()->json(['contribution_amount' => $community->contribution_amount]);
    }

    public function toggleChilimba(Request $request)
    {
        $communityId = $request->user()->communities()->first()?->id;
        $community = Community::findOrFail($communityId);
        $community->update(['chilimba_enabled' => !$community->chilimba_enabled]);
        return response()->json(['chilimba_enabled' => $community->chilimba_enabled]);
    }

    public function memberCycles(Request $request)
    {
        $communityId = $request->user()->communities()->first()?->id;
        $cycles = Cycle::with('recipient')
            ->where('community_id', $communityId)
            ->orderBy('cycle_number')
            ->get();
        return response()->json($cycles);
    }
}
