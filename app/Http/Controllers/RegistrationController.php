<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Unit;
use App\Models\ParentInfo;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RegistrationController extends Controller
{
    public function create()
    {
        $units = Unit::where('is_active', true)->get();
        return view('registration.create', compact('units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'nik' => 'required|string|max:16|unique:registrations,nik',
            'full_name' => 'required|string|max:150',
            'nickname' => 'nullable|string|max:50',
            'gender' => 'required|in:L,P',
            'birth_place' => 'required|string|max:100',
            'birth_date' => 'required|date',
            'religion' => 'nullable|string|max:50',
            'child_order' => 'nullable|integer',
            'siblings_count' => 'nullable|integer',
            'home_address' => 'required|string',
            'rt' => 'nullable|string|max:5',
            'rw' => 'nullable|string|max:5',
            'village' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'previous_school' => 'nullable|string|max:150',
            'previous_school_address' => 'nullable|string',
            'graduation_year' => 'nullable|integer|min:2000|max:2030',
            
            // Data Ayah
            'father_name' => 'required|string|max:150',
            'father_nik' => 'nullable|string|max:16',
            'father_birth_place' => 'nullable|string|max:100',
            'father_birth_date' => 'nullable|date',
            'father_education' => 'nullable|string|max:50',
            'father_occupation' => 'nullable|string|max:100',
            'father_phone' => 'nullable|string|max:20',
            'father_email' => 'nullable|email|max:100',
            'father_income' => 'nullable|numeric',
            
            // Data Ibu
            'mother_name' => 'required|string|max:150',
            'mother_nik' => 'nullable|string|max:16',
            'mother_birth_place' => 'nullable|string|max:100',
            'mother_birth_date' => 'nullable|date',
            'mother_education' => 'nullable|string|max:50',
            'mother_occupation' => 'nullable|string|max:100',
            'mother_phone' => 'nullable|string|max:20',
            'mother_email' => 'nullable|email|max:100',
            'mother_income' => 'nullable|numeric',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['status'] = 'submitted';
        $validated['submitted_at'] = now();

        $registration = Registration::create($validated);

        // Simpan data orang tua
        ParentInfo::create([
            'registration_id' => $registration->id,
            'father_name' => $request->father_name,
            'father_nik' => $request->father_nik,
            'father_birth_place' => $request->father_birth_place,
            'father_birth_date' => $request->father_birth_date,
            'father_education' => $request->father_education,
            'father_occupation' => $request->father_occupation,
            'father_phone' => $request->father_phone,
            'father_email' => $request->father_email,
            'father_income' => $request->father_income,
            'mother_name' => $request->mother_name,
            'mother_nik' => $request->mother_nik,
            'mother_birth_place' => $request->mother_birth_place,
            'mother_birth_date' => $request->mother_birth_date,
            'mother_education' => $request->mother_education,
            'mother_occupation' => $request->mother_occupation,
            'mother_phone' => $request->mother_phone,
            'mother_email' => $request->mother_email,
            'mother_income' => $request->mother_income,
        ]);

        return redirect()->route('registration.documents', $registration->id)
            ->with('success', 'Data pendaftaran berhasil disimpan. Silakan upload dokumen.');
    }

    public function uploadDocuments(Request $request, $id)
    {
        $registration = Registration::findOrFail($id);
        
        $request->validate([
            'report_card' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'family_card' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $documents = [
            'report_card' => 'Raport Nilai',
            'family_card' => 'Kartu Keluarga',
            'supporting_document' => 'Dokumen Pendukung',
        ];

        foreach ($documents as $type => $label) {
            if ($request->hasFile($type)) {
                $file = $request->file($type);
                $path = $file->store('documents/' . $registration->id, 'public');
                
                Document::create([
                    'registration_id' => $registration->id,
                    'type' => $type,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'is_verified' => false,
                ]);
            }
        }

        return redirect()->route('dashboard')
            ->with('success', 'Dokumen berhasil diupload. Pendaftaran sedang diverifikasi.');
    }
    public function uploadDocumentsForm($id)
{
    $registration = Registration::findOrFail($id);
    return view('registration.documents', compact('registration'));
}
}