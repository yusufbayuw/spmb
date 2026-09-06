<?php

namespace App\Http\Controllers;

use App\Models\AdmissionOffer;
use App\Services\AdmissionDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdmissionOfferController extends Controller
{
    public function accept(Request $request, AdmissionOffer $offer, AdmissionDecisionService $admission): RedirectResponse
    {
        abort_unless($offer->registration()->where('user_id', $request->user()->id)->exists(), 403);
        $admission->acceptOffer($offer, $request->user());

        return back()->with('status', 'Kursi berhasil dikonfirmasi. Silakan lanjutkan daftar ulang.');
    }

    public function decline(Request $request, AdmissionOffer $offer, AdmissionDecisionService $admission): RedirectResponse
    {
        abort_unless($offer->registration()->where('user_id', $request->user()->id)->exists(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $admission->declineOffer($offer, $request->user(), $data['reason']);

        return back()->with('status', 'Penawaran penerimaan telah ditolak.');
    }
}
