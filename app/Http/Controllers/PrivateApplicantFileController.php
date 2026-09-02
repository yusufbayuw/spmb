<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PrivateApplicantFileController extends Controller
{
    public function document(
        Request $request,
        Document $document,
        ApplicantFileStorage $storage,
    ): BinaryFileResponse {
        $document->loadMissing('registration');

        $this->authorizeRegistration(
            $request->user(),
            $document->registration,
            'view_document',
        );

        return $this->serve(
            $request,
            $storage,
            $document->file_path,
            $document->original_name ?: basename($document->file_path),
        );
    }

    public function paymentProof(
        Request $request,
        Payment $payment,
        ApplicantFileStorage $storage,
    ): BinaryFileResponse {
        $payment->loadMissing('registration');

        $this->authorizeRegistration(
            $request->user(),
            $payment->registration,
            'view_payment',
        );

        abort_unless($payment->proof_path, 404);

        return $this->serve(
            $request,
            $storage,
            $payment->proof_path,
            $payment->proof_original_name ?: basename($payment->proof_path),
        );
    }

    private function authorizeRegistration(
        ?User $user,
        ?Registration $registration,
        string $staffPermission,
    ): void {
        abort_unless($user?->is_active && $registration, 403);

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTU()) {
            abort_unless(
                $user->unit_id
                && (int) $registration->unit_id === (int) $user->unit_id
                && $user->can($staffPermission),
                403,
            );

            return;
        }

        abort_unless(
            $user->isUser()
            && (int) $registration->user_id === (int) $user->id,
            403,
        );
    }

    private function serve(
        Request $request,
        ApplicantFileStorage $storage,
        string $path,
        string $fileName,
    ): BinaryFileResponse {
        abort_unless($storage->ensurePrivate($path), 404);

        $disk = $storage->privateDisk();
        $absolutePath = $disk->path($path);
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $safeName = trim(str_replace(["\r", "\n"], '', $fileName)) ?: 'file';

        $headers = [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Type' => $mimeType,
        ];

        if ($request->boolean('download')) {
            return response()->download($absolutePath, $safeName, $headers);
        }

        $response = response()->file($absolutePath, $headers);
        $response->headers->set(
            'Content-Disposition',
            ResponseHeaderBag::makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $safeName,
                'file',
            ),
        );

        return $response;
    }
}
