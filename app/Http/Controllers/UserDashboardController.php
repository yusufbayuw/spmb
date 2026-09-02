<?php

namespace App\Http\Controllers;

class UserDashboardController extends Controller
{
    public function index()
    {
        $registrations = auth()->user()->registrations()->with(['unit','latestPayment','selection','announcement'])->latest()->get();
        return view('dashboard', compact('registrations'));
    }
}
