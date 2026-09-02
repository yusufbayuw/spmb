<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Unit;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $registrations = Registration::where('user_id', $user->id)
            ->with('unit', 'payments', 'documents')
            ->latest()
            ->get();
        
        return view('dashboard', compact('registrations'));
    }
}