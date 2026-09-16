<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()
            ->whereNull('dismissed_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function dismiss($id, Request $request)
    {
        $notif = $request->user()->notifications()->findOrFail($id);
        $notif->update(['dismissed_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
