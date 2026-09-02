<?php

namespace App\Http\Controllers;

use App\Services\OperationalReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationalReportController extends Controller
{
    public function __invoke(Request $request, OperationalReportService $reports): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user?->is_active && $user->hasAnyRole(['super_admin', 'tu']), 403);

        $filters = $request->only([
            'unit_id',
            'registration_opening_id',
            'current_stage',
            'lifecycle_status',
            'payment_status',
            'decision',
            'date_from',
            'date_until',
        ]);

        return $reports->download($user, $filters);
    }
}
