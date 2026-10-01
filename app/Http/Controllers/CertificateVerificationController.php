<?php

namespace App\Http\Controllers;

use App\Models\UserCertification;
use App\Services\AppBrandingService;
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
