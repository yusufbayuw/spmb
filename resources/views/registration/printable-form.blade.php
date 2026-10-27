<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Formulir Pendaftaran - {{ $registration->registration_number ?: $registration->uuid }}</title>
<style>
@page { size: A4; margin: 16mm; }
body { font: 12px/1.5 Arial, sans-serif; color: #111; margin: 25px auto; max-width: 800px; }
h1 { text-align: center; font-size: 20px; margin-bottom: 4px; }
h2 { font-size: 14px; border-bottom: 1px solid #999; padding-bottom: 5px; margin-top: 24px; }
.sub { text-align: center; color: #555; }
.row { display: grid; grid-template-columns: 190px 1fr; gap: 10px; padding: 5px 0; border-bottom: 1px dotted #ddd; break-inside: avoid; }
.label { color: #555; } .value { white-space: pre-wrap; overflow-wrap: anywhere; }
button { padding: 8px 14px; cursor: pointer; } .toolbar { text-align: right; margin-bottom: 20px; }
@media print { .toolbar { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan sebagai PDF</button></div>
<h1>FORMULIR PENDAFTARAN</h1>
<p class="sub">{{ $registration->unit?->name }} &mdash; {{ $registration->opening?->academic_year }}</p>
<h2>Informasi Pendaftaran</h2>
@foreach(['Nomor Registrasi' => $registration->registration_number, 'Gelombang' => $registration->opening?->wave, 'Program Studi' => $registration->opening?->studyProgram?->name, 'Jalur' => $registration->pathway?->name] as $label => $value)
<div class="row"><div class="label">{{ $label }}</div><div class="value">{{ filled($value) ? $value : '—' }}</div></div>
@endforeach
<h2>Identitas Pendaftar</h2>
@foreach(['Nama Lengkap' => $registration->full_name, 'NIK' => $registration->nik, 'NISN' => $registration->nisn, 'Jenis Kelamin' => $registration->gender, 'Tempat Lahir' => $registration->birth_place, 'Tanggal Lahir' => $registration->birth_date?->format('d/m/Y'), 'Email' => $registration->email, 'Telepon' => $registration->phone, 'Alamat' => $registration->home_address, 'Sekolah Asal' => $registration->previous_school] as $label => $value)
<div class="row"><div class="label">{{ $label }}</div><div class="value">{{ filled($value) ? $value : '—' }}</div></div>
@endforeach
@if($registration->configuration)
<h2>Isian Formulir Sesuai Konfigurasi Pendaftaran</h2>
@foreach($registration->configuration->fields ?? [] as $field)
@if(is_array($field) && filled($field['key'] ?? null) && array_key_exists($field['key'], $registration->custom_answers ?? []))
@php $answer = $registration->custom_answers[$field['key']]; @endphp
<div class="row"><div class="label">{{ $field['label'] ?? $field['key'] }}</div><div class="value">@if(($field['type'] ?? '') === 'file')Dokumen terlampir (tersimpan secara terpisah)@elseif(is_array($answer)){{ implode(', ', array_filter($answer, 'is_scalar')) }}@elseif(is_bool($answer)){{ $answer ? 'Ya' : 'Tidak' }}@else{{ filled($answer) ? $answer : '—' }}@endif</div></div>
@endif
@endforeach
@endif
@if($registration->academicScores->isNotEmpty())
<h2>Nilai Akademik</h2>
@foreach($registration->academicScores as $score)
<div class="row"><div class="label">Nilai #{{ $loop->iteration }}</div><div class="value">{{ json_encode($score->getAttributes(), JSON_UNESCAPED_UNICODE) }}</div></div>
@endforeach
@endif
@if($registration->achievements->isNotEmpty())
<h2>Prestasi</h2>
@foreach($registration->achievements as $achievement)
<div class="row"><div class="label">Prestasi #{{ $loop->iteration }}</div><div class="value">{{ $achievement->name ?? $achievement->title ?? '—' }}</div></div>
@endforeach
@endif
</body>
</html>
