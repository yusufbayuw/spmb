<?php

namespace App\Http\Controllers;

use App\Models\UserCertification;
use App\Services\AppBrandingService;
use App\Services\CertificateArtifactService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificateVerificationController extends Controller
{
    public function show(UserCertification $certificate)
    {
        $certificate->load(['user.unit', 'program']);

        return view('certificates.verify', [
            'certificate' => $certificate,
            'portalName' => app(AppBrandingService::class)->portalName(),
        ]);
    }

    public function pdf(Request $request, UserCertification $certificate): Response
    {
        abort_unless(
            app(\App\Services\TrainingGovernanceService::class)->certificateArtifactEnabled()
            || filled($certificate->artifact_path),
            404,
        );

        try {
            $bytes = app(CertificateArtifactService::class)->contents($certificate);
        } catch (\RuntimeException) {
            abort(409, 'Artifact sertifikat gagal diverifikasi.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                .'; filename="sertifikat-'.$certificate->certificate_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function qr(Request $request, UserCertification $certificate): Response
    {
        $result = Builder::create()
            ->writer(new SvgWriter())
            ->data(route('certificates.verify', $certificate))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(420)
            ->margin(18)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->validateResult(false)
            ->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="sertifikat-'.$certificate->certificate_number.'.svg"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
