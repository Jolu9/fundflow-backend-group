<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Loan;

class StatsController extends Controller
{
    public function index()
    {
        return response()->json([
            'members' => User::where('role', 'member')->count(),
            'activeLoans' => Loan::where('status', 'active')->count(),
            'overdueLoans' => Loan::where('status', 'overdue')->count(),
            'totalDisbursed' => Loan::where('status', '!=', 'pending')->sum('amount'),
        ]);
    }
}
