<?php

namespace App\Http\Controllers;

use App\Models\RegistrationOpening;
use App\Models\Unit;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AdmissionQrCodeController extends Controller
{
    public function unit(Request $request, Unit $unit): Response
    {
        abort_unless($unit->is_active, 404);

        return $this->svgResponse(
            $request,
            route('admissions.unit', ['unit' => $unit->code]),
            'penerimaan-'.Str::slug($unit->code).'.svg',
        );
    }

    public function opening(Request $request, RegistrationOpening $registrationOpening): Response
    {
        abort_unless(
            RegistrationOpening::query()
                ->visibleToApplicants()
                ->whereKey($registrationOpening->getKey())
                ->exists(),
            404,
        );

        return $this->svgResponse(
            $request,
            route('admissions.show', $registrationOpening),
            'pendaftaran-'.Str::slug($registrationOpening->label()).'.svg',
        );
    }

    private function svgResponse(Request $request, string $url, string $filename): Response
    {
        $result = Builder::create()
            ->writer(new SvgWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(420)
            ->margin(18)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->validateResult(false)
            ->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
