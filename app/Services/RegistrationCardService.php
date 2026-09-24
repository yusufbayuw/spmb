<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Registration;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistrationCardService
{
    public function identityPhoto(Registration $registration): ?Document
    {
        if ($registration->relationLoaded('documents')) {
            return $registration->documents
                ->filter(fn (Document $document): bool => $document->type === 'photo'
                    && $document->superseded_at === null
                    && (blank($document->malware_scan_status)
                        || in_array($document->malware_scan_status, ['clean', 'unavailable', 'scan_error'], true)))
                ->sortByDesc('id')
                ->first();
        }

        return $registration->documents()
            ->where('type', 'photo')
            ->whereNull('superseded_at')
            ->where(function ($query): void {
                $query->whereNull('malware_scan_status')
                    ->orWhereIn('malware_scan_status', ['clean', 'unavailable', 'scan_error']);
            })
            ->latest('id')
            ->first();
    }

    public function hasIdentityPhoto(Registration $registration): bool
    {
        $photo = $this->identityPhoto($registration);

        return $photo !== null
            && Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->exists($photo->file_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function cardData(Registration $registration): array
    {
        $registration->loadMissing([
            'unit',
            'opening.studyProgram',
            'pathway',
            'documents',
        ]);

        $photo = $this->identityPhoto($registration);
        $unit = $registration->unit;
        $opening = $registration->opening;
        $isHigherEducation = $unit?->isHigherEducation() ?? false;

        $secondaryLabel = $isHigherEducation ? 'Program Studi' : 'Asal Sekolah';
        $secondaryValue = $isHigherEducation
            ? ($opening?->studyProgram?->label() ?? '—')
            : ($registration->previous_school ?: '—');

        $period = collect([
            $opening?->academic_year,
            $opening?->wave,
        ])->filter()->implode(' · ');

        return [
            'unitName' => $unit?->name ?? 'Unit / Institusi',
            'unitNameLines' => $this->wrapText($unit?->name ?? 'Unit / Institusi', 34, 2),
            'unitNameFontSize' => $this->fontSize($unit?->name ?? '', 34, 30, 25),
            'unitAddress' => Str::limit((string) ($unit?->public_address ?? ''), 82),
            'logoDataUri' => $this->fileDataUri('public', $unit?->logo_path),
            'photoDataUri' => $photo
                ? $this->fileDataUri(ApplicantFileStorage::PRIVATE_DISK, $photo->file_path, $photo->mime_type)
                : null,
            'verificationQrDataUri' => $this->verificationQrDataUri($registration),
            'verificationUrl' => route('registration.card.verify', $registration),
            'cardNumber' => $registration->applicant_card_number,
            'participantName' => $registration->full_name,
            'participantNameLines' => $this->wrapText($registration->full_name, 30, 2),
            'participantNameFontSize' => $this->fontSize($registration->full_name, 30, 28, 21),
            'birth' => collect([
                $registration->birth_place,
                $registration->birth_date?->format('d-m-Y'),
            ])->filter()->implode(', '),
            'secondaryLabel' => $secondaryLabel,
            'secondaryValue' => $secondaryValue,
            'secondaryValueLines' => $this->wrapText($secondaryValue, 31, 2),
            'secondaryValueFontSize' => $this->fontSize($secondaryValue, 31, 24, 18),
            'pathway' => $registration->pathway?->name ?? '—',
            'period' => $period ?: '—',
            'issuedDate' => $registration->applicant_card_issued_at?->format('d-m-Y') ?? '—',
            'filenameStem' => 'kartu-pendaftaran-'.Str::slug(
                (string) ($registration->applicant_card_number ?: $registration->registration_number ?: $registration->uuid)
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public function wrapText(string $value, int $maxCharacters, int $maxLines = 2): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return ['—'];
        }

        $words = preg_split('/\s+/u', $value) ?: [$value];
        $lines = [];
        $current = '';

        foreach ($words as $index => $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current === '' || mb_strlen($candidate) <= $maxCharacters) {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;

            if (count($lines) === $maxLines - 1) {
                $remaining = implode(' ', array_slice($words, $index));
                $lines[] = Str::limit($remaining, $maxCharacters + 4);

                return $lines;
            }

            $current = $word;
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        return array_slice($lines, 0, $maxLines);
    }

    private function fontSize(string $value, int $mediumThreshold, int $largeSize, int $smallSize): int
    {
        $length = mb_strlen(trim($value));

        if ($length <= (int) floor($mediumThreshold * 0.7)) {
            return $largeSize;
        }

        if ($length <= $mediumThreshold) {
            return (int) round(($largeSize + $smallSize) / 2);
        }

        return $smallSize;
    }

    private function fileDataUri(string $diskName, ?string $path, ?string $knownMimeType = null): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return null;
        }

        $mimeType = $knownMimeType ?: $disk->mimeType($path) ?: 'application/octet-stream';

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($path));
    }

    private function verificationQrDataUri(Registration $registration): string
    {
        $result = Builder::create()
            ->writer(new SvgWriter())
            ->data(route('registration.card.verify', $registration))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(240)
            ->margin(8)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->validateResult(false)
            ->build();

        return 'data:'.$result->getMimeType().';base64,'.base64_encode($result->getString());
    }
}
