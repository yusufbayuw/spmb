<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingApplicantEmailChange;
use App\Services\ApplicantEmailCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfirmApplicantEmailChangeController extends Controller
{
    public function __invoke(Request $request, PendingApplicantEmailChange $emailChange, ApplicantEmailCorrectionService $service): RedirectResponse
    {
        $service->confirm($emailChange, $request->user());

        return redirect('/pendaftar/profile')->with('status', 'email-confirmed');
    }
}
