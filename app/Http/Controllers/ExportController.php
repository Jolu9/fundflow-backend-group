<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Loan;
use App\Models\Contribution;
use App\Models\Community;

class ExportController extends Controller
{
    public function report(Request $request)
    {
        $communityId = $request->user()->communities()->first()?->id;

        if (!$communityId) {
            return response()->json(['message' => 'No community found.'], 422);
        }

        $community = Community::find($communityId);
        $loans = Loan::with('user')->where('community_id', $communityId)->latest()->get();
        $contributions = Contribution::with('user')->where('community_id', $communityId)->latest()->get();

        $totalDisbursed = $loans->whereNotIn('status', ['pending', 'rejected'])->sum('amount');
        $totalRepaid = $loans->sum('amount_paid');
        $totalContributed = $contributions->sum('amount');
        $currentFund = max(0, $totalContributed + $totalRepaid - $totalDisbursed);

        $pdf = Pdf::loadView('exports.report', [
            'community' => $community,
            'loans' => $loans,
            'contributions' => $contributions,
            'totalDisbursed' => $totalDisbursed,
            'totalRepaid' => $totalRepaid,
            'totalContributed' => $totalContributed,
            'currentFund' => $currentFund,
            'activeCount' => $loans->where('status', 'active')->count(),
            'overdueCount' => $loans->where('status', 'overdue')->count(),
            'completedCount' => $loans->where('status', 'completed')->count(),
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('fundflow-report-' . date('Y-m-d') . '.pdf');
    }
}
