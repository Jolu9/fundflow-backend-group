<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContributionController extends Controller
{
    // Treasurer: get all contributions
    public function index()
    {
        return Contribution::with(['user', 'recorder'])->orderBy('contribution_date', 'desc')->get();
    }

    // Treasurer: record a contribution manually
    public function store(Request $request)
    {
        $request->validate([
            'user_id'           => 'required|exists:users,id',
            'amount'            => 'required|numeric|min:0.01',
            'contribution_date' => 'required|date',
            'notes'             => 'nullable|string',
        ]);

        $communityId = DB::table('community_user')
            ->where('user_id', $request->user_id)
            ->value('community_id');

        $contribution = Contribution::create([
            'user_id'           => $request->user_id,
            'recorded_by'       => Auth::id(),
            'amount'            => $request->amount,
            'contribution_date' => $request->contribution_date,
            'notes'             => $request->notes,
            'community_id'      => $communityId,
        ]);

        return response()->json($contribution->load(['user', 'recorder']), 201);
    }

    // Member: get their own contributions
    public function myContributions()
    {
        return Contribution::with('recorder')
            ->where('user_id', Auth::id())
            ->orderBy('contribution_date', 'desc')
            ->get();
    }
}
