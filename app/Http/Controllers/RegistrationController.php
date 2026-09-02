<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ParentInfo;
use App\Models\Registration;
use App\Models\Unit;
use App\Services\RegistrationWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('registration.create', ['units' => Unit::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'registrant_type' => 'required|in:parent,self',
            'registrant_relationship' => 'nullable|required_if:registrant_type,parent|in:father,mother,guardian,other',
            'unit_id' => 'required|exists:units,id',
            'nik' => 'required|string|size:16|unique:registrations,nik',
            'full_name' => 'required|string|max:150',
            'nickname' => 'nullable|string|max:50',
            'gender' => 'required|in:L,P',
            'birth_place' => 'required|string|max:100',
            'birth_date' => 'required|date|before:today',
            'religion' => 'nullable|string|max:50',
            'home_address' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'previous_school' => 'nullable|string|max:150',
            'graduation_year' => 'nullable|integer|min:2000|max:'.(now()->year + 2),
            'father_name' => 'required|string|max:150',
            'father_nik' => 'nullable|string|max:16',
            'father_occupation' => 'nullable|string|max:100',
            'father_phone' => 'nullable|string|max:20',
            'father_income' => 'nullable|numeric|min:0',
            'mother_name' => 'required|string|max:150',
            'mother_nik' => 'nullable|string|max:16',
            'mother_occupation' => 'nullable|string|max:100',
            'mother_phone' => 'nullable|string|max:20',
            'mother_income' => 'nullable|numeric|min:0',
        ]);

        $registration = DB::transaction(function () use ($validated, $request) {
            $registration = Registration::create([
                'user_id' => auth()->id(),
                'unit_id' => $validated['unit_id'],
                'registrant_type' => $validated['registrant_type'],
                'registrant_relationship' => $validated['registrant_type'] === 'self' ? 'self' : $validated['registrant_relationship'],
                'nik' => $validated['nik'],
                'full_name' => $validated['full_name'],
                'nickname' => $validated['nickname'] ?? null,
                'gender' => $validated['gender'],
                'birth_place' => $validated['birth_place'],
                'birth_date' => $validated['birth_date'],
                'religion' => $validated['religion'] ?? 'Islam',
                'home_address' => $validated['home_address'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'previous_school' => $validated['previous_school'] ?? null,
                'graduation_year' => $validated['graduation_year'] ?? null,
                'status' => 'submitted',
                'current_stage' => 'data_validation',
                'data_validation_status' => 'pending',
                'submitted_at' => now(),
            ]);

            ParentInfo::create([
                'registration_id' => $registration->id,
                'father_name' => $validated['father_name'],
                'father_nik' => $validated['father_nik'] ?? null,
                'father_occupation' => $validated['father_occupation'] ?? null,
                'father_phone' => $validated['father_phone'] ?? null,
                'father_income' => $validated['father_income'] ?? null,
                'mother_name' => $validated['mother_name'],
                'mother_nik' => $validated['mother_nik'] ?? null,
                'mother_occupation' => $validated['mother_occupation'] ?? null,
                'mother_phone' => $validated['mother_phone'] ?? null,
                'mother_income' => $validated['mother_income'] ?? null,
            ]);

            return $registration;
        });

        return redirect()->route('registration.show', $registration)->with('success', 'Pendaftaran berhasil dikirim dan menunggu validasi TU.');
    }

    public function show(int $registration)
    {
        $registration = $this->ownedRegistration($registration)->load(['unit','parentInfo','documents','latestPayment','testResults.admissionTest','selection','announcement']);
        return view('registration.show', compact('registration'));
    }

    public function paymentForm(int $registration)
    {
        $registration = $this->ownedRegistration($registration)->load('latestPayment');
        abort_unless($registration->latestPayment && in_array($registration->current_stage, ['payment','payment_verification'], true), 403);
        return view('registration.payment', compact('registration'));
    }

    public function uploadPayment(Request $request, int $registration, RegistrationWorkflowService $workflow)
    {
        $registration = $this->ownedRegistration($registration)->load('latestPayment');
        abort_unless($registration->latestPayment && $registration->current_stage === 'payment', 403);
        $validated = $request->validate(['proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120','payment_method' => 'nullable|string|max:50']);
        $payment = $registration->latestPayment;
        if ($payment->proof_path) Storage::disk('public')->delete($payment->proof_path);
        $file = $validated['proof'];
        $path = $file->store('payments/'.$registration->id, 'public');
        $payment->update(['proof_path' => $path,'proof_original_name' => $file->getClientOriginalName(),'payment_method' => $validated['payment_method'] ?? null]);
        $workflow->markPaymentUploaded($payment);
        return redirect()->route('registration.show', $registration)->with('success', 'Bukti pembayaran berhasil diunggah dan menunggu verifikasi TU.');
    }

    public function documentsForm(int $registration)
    {
        $registration = $this->ownedRegistration($registration)->load('documents');
        abort_unless(in_array($registration->current_stage, ['documents','document_verification'], true), 403);
        return view('registration.documents', compact('registration'));
    }

    public function uploadDocuments(Request $request, int $registration)
    {
        $registration = $this->ownedRegistration($registration);
        abort_unless(in_array($registration->current_stage, ['documents','document_verification'], true), 403);
        $request->validate([
            'report_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'family_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'birth_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        foreach (['report_card','family_card','birth_certificate','photo','supporting_document'] as $type) {
            if (! $request->hasFile($type)) continue;
            $file = $request->file($type);
            $existing = $registration->documents()->where('type', $type)->first();
            if ($existing?->file_path) Storage::disk('public')->delete($existing->file_path);
            $path = $file->store('documents/'.$registration->id, 'public');
            Document::updateOrCreate(['registration_id' => $registration->id,'type' => $type], ['file_path' => $path,'original_name' => $file->getClientOriginalName(),'file_type' => $file->getClientOriginalExtension(),'file_size' => $file->getSize(),'is_verified' => false,'verified_at' => null,'verified_by' => null]);
        }

        $required = RegistrationWorkflowService::REQUIRED_DOCUMENTS;
        $uploaded = $registration->documents()->whereIn('type', $required)->pluck('type')->unique();
        $complete = collect($required)->every(fn (string $type) => $uploaded->contains($type));
        $registration->update(['current_stage' => $complete ? 'document_verification' : 'documents','documents_completed_at' => $complete ? now() : null]);
        return redirect()->route('registration.show', $registration)->with('success', $complete ? 'Berkas lengkap dan menunggu verifikasi TU.' : 'Berkas berhasil disimpan. Lengkapi dokumen wajib lainnya.');
    }

    public function card(int $registration)
    {
        $registration = $this->ownedRegistration($registration)->load('unit');
        abort_unless($registration->applicant_card_number, 404);
        return view('registration.card', compact('registration'));
    }

    private function ownedRegistration(int $id): Registration
    {
        return Registration::where('user_id', auth()->id())->findOrFail($id);
    }
}
