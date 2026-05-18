<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MemberController extends Controller
{
    public function index()
    {
        return response()->json(Member::with('user')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'nullable|string',
            'national_id' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'member',
        ]);

        $member = Member::create([
            'user_id' => $user->id,
            'phone' => $request->phone,
            'national_id' => $request->national_id,
            'address' => $request->address,
        ]);

        return response()->json(Member::with('user')->find($member->id), 201);
    }

    public function destroy($id)
    {
        $member = Member::findOrFail($id);
        $member->user->delete();
        return response()->json(['message' => 'Member deleted']);
    }
}
