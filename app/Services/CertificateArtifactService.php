<?php

namespace App\Services;

use App\Models\UserCertification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CertificateArtifactService
{
    public function ensure(UserCertification $certificate): UserCertification
    {
        if (! app(TrainingGovernanceService::class)->certificateArtifactEnabled()) {
            return $certificate;
        }

        if ($certificate->artifact_path && $this->verify($certificate)) {
            return $certificate;
        }

        return $this->generate($certificate);
    }

    public function generate(UserCertification $certificate): UserCertification
    {
        $certificate->loadMissing(['user.unit', 'program']);

        $pdf = $this->buildPdf($certificate);
        $path = 'certificates/'.$certificate->uuid.'.pdf';
        $sha256 = hash('sha256', $pdf);
        $signature = $this->signature($certificate, $sha256);

        Storage::disk('local')->put($path, $pdf);

        $certificate->update([
            'artifact_path' => $path,
            'artifact_sha256' => $sha256,
            'artifact_signature' => $signature,
            'artifact_signature_algorithm' => 'HMAC-SHA256',
            'artifact_generated_at' => now(),
        ]);

        app(AuditTrail::class)->record(
            'certification.artifact_generated',
            $certificate,
            metadata: [
                'sha256' => $sha256,
                'algorithm' => 'HMAC-SHA256',
            ],
            description: 'Artifact PDF sertifikat dibuat dan ditandatangani integritasnya',
        );

        return $certificate->fresh();
    }

    public function verify(UserCertification $certificate): bool
    {
        if (! $certificate->artifact_path
            || ! $certificate->artifact_sha256
            || ! $certificate->artifact_signature
            || ! Storage::disk('local')->exists($certificate->artifact_path)) {
            return false;
        }

        $bytes = Storage::disk('local')->get($certificate->artifact_path);
        $sha256 = hash('sha256', $bytes);

        return hash_equals($certificate->artifact_sha256, $sha256)
            && hash_equals(
                $certificate->artifact_signature,
                $this->signature($certificate, $sha256),
            );
    }

    public function contents(UserCertification $certificate): string
    {
        $certificate = $this->ensure($certificate);

        if (! $certificate->artifact_path || ! $this->verify($certificate)) {
            throw new RuntimeException('Artifact sertifikat gagal diverifikasi.');
        }

        return Storage::disk('local')->get($certificate->artifact_path);
    }

    private function signature(UserCertification $certificate, string $sha256): string
    {
        return hash_hmac(
            'sha256',
            $sha256.'|'.$certificate->verification_code.'|'.$certificate->certificate_number,
            $this->signingKey(),
        );
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }

    private function buildPdf(UserCertification $certificate): string
    {
        $branding = app(AppBrandingService::class);
        $status = strtoupper($certificate->effectiveStatus());

        $lines = [
            $branding->portalName(),
            'BUKTI KELULUSAN SERTIFIKASI SPMB',
            '',
            'Diberikan kepada: '.$certificate->recipientName(),
            'Unit: '.$certificate->recipientUnit(),
            'Role: '.$certificate->recipientRole(),
            '',
            'Sertifikasi: '.$certificate->programName(),
            'Kode / Versi: '.$certificate->programCode().' / '.$certificate->programVersion(),
            'Nomor Sertifikat: '.$certificate->certificate_number,
            'Nilai Teori: '.($certificate->theory_score !== null ? number_format((float) $certificate->theory_score, 2, '.', '') : '-'),
            'Nilai Practical: '.($certificate->practical_score !== null ? number_format((float) $certificate->practical_score, 2, '.', '') : '-'),
            'Nilai Akhir: '.number_format((float) $certificate->score, 2, '.', ''),
            'Terbit: '.$certificate->issued_at?->timezone(config('app.timezone'))->format('d/m/Y'),
            'Berlaku Sampai: '.($certificate->expires_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Tanpa batas'),
            'Status: '.$status,
            '',
            'Kode Verifikasi: '.$certificate->verification_code,
            'Verifikasi: '.route('certificates.verify', $certificate),
            '',
            'Artifact ini bersifat tamper-evident. Integritas file diverifikasi oleh aplikasi penerbit.',
        ];

        return $this->simplePdf($lines);
    }

    private function simplePdf(array $lines): string
    {
        $commands = [
            'BT',
            '/F1 16 Tf',
            '55 790 Td',
        ];

        foreach ($lines as $index => $line) {
            $fontSize = $index === 1 ? 15 : 10;
            $spacing = $index <= 1 ? 24 : 17;

            if ($index > 0) {
                $commands[] = '0 -'.$spacing.' Td';
            }

            $commands[] = '/F1 '.$fontSize.' Tf';

            foreach ($this->wrap((string) $line, 88) as $partIndex => $part) {
                if ($partIndex > 0) {
                    $commands[] = '0 -14 Td';
                }
                $commands[] = '('.$this->escapePdfText($part).') Tj';
            }
        }

        $commands[] = 'ET';
        $stream = implode("\n", $commands)."\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => "<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_keys($objects) as $number) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function wrap(string $text, int $width): array
    {
        if ($text === '') {
            return [''];
        }

        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    private function escapePdfText(string $text): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $converted === false ? $text : $converted,
        );
    }
}
