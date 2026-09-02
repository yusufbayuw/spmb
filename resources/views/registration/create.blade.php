@extends('layouts.app')

@section('title', 'Form Pendaftaran - SPMB Taruna Bakti')

@section('content')
<div class="max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Form Pendaftaran Murid Baru</h2>
    
    <form action="{{ route('registration.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- Step 1: Pilih Unit -->
        <div class="bg-white rounded-xl p-6 card-shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">1. Pilih Unit Sekolah</h3>
            <div>
                <label class="label-field">Unit Sekolah *</label>
                <select name="unit_id" required class="input-field">
                    <option value="">-- Pilih Unit --</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        
        <!-- Step 2: Data Calon Siswa -->
        <div class="bg-white rounded-xl p-6 card-shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">2. Data Calon Siswa</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label-field">NIK *</label>
                    <input type="text" name="nik" value="{{ old('nik') }}" required class="input-field" maxlength="16">
                </div>
                <div>
                    <label class="label-field">Nama Lengkap *</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required class="input-field">
                </div>
                <div>
                    <label class="label-field">Nama Panggilan</label>
                    <input type="text" name="nickname" value="{{ old('nickname') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Jenis Kelamin *</label>
                    <select name="gender" required class="input-field">
                        <option value="">-- Pilih --</option>
                        <option value="L" {{ old('gender') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="label-field">Tempat Lahir *</label>
                    <input type="text" name="birth_place" value="{{ old('birth_place') }}" required class="input-field">
                </div>
                <div>
                    <label class="label-field">Tanggal Lahir *</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}" required class="input-field">
                </div>
                <div>
                    <label class="label-field">Agama</label>
                    <input type="text" name="religion" value="{{ old('religion', 'Islam') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Anak Ke-</label>
                    <input type="number" name="child_order" value="{{ old('child_order') }}" class="input-field" min="1">
                </div>
                <div>
                    <label class="label-field">Jumlah Saudara</label>
                    <input type="number" name="siblings_count" value="{{ old('siblings_count', 0) }}" class="input-field" min="0">
                </div>
                <div class="md:col-span-2">
                    <label class="label-field">Alamat Rumah *</label>
                    <textarea name="home_address" required class="input-field" rows="3">{{ old('home_address') }}</textarea>
                </div>
                <div>
                    <label class="label-field">RT</label>
                    <input type="text" name="rt" value="{{ old('rt') }}" class="input-field" maxlength="5">
                </div>
                <div>
                    <label class="label-field">RW</label>
                    <input type="text" name="rw" value="{{ old('rw') }}" class="input-field" maxlength="5">
                </div>
                <div>
                    <label class="label-field">Kelurahan/Desa</label>
                    <input type="text" name="village" value="{{ old('village') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Kecamatan</label>
                    <input type="text" name="district" value="{{ old('district') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Kota</label>
                    <input type="text" name="city" value="{{ old('city') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Provinsi</label>
                    <input type="text" name="province" value="{{ old('province') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Kode Pos</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code') }}" class="input-field" maxlength="10">
                </div>
                <div>
                    <label class="label-field">No. Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="input-field" maxlength="20">
                </div>
                <div>
                    <label class="label-field">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="input-field">
                </div>
            </div>
        </div>
        
        <!-- Step 3: Data Orang Tua -->
        <div class="bg-white rounded-xl p-6 card-shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">3. Data Orang Tua</h3>
            
            <h4 class="font-medium text-gray-700 mb-3">Data Ayah</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="label-field">Nama Ayah *</label>
                    <input type="text" name="father_name" value="{{ old('father_name') }}" required class="input-field">
                </div>
                <div>
                    <label class="label-field">NIK Ayah</label>
                    <input type="text" name="father_nik" value="{{ old('father_nik') }}" class="input-field" maxlength="16">
                </div>
                <div>
                    <label class="label-field">Pendidikan Ayah</label>
                    <input type="text" name="father_education" value="{{ old('father_education') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Pekerjaan Ayah</label>
                    <input type="text" name="father_occupation" value="{{ old('father_occupation') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">No. HP Ayah</label>
                    <input type="text" name="father_phone" value="{{ old('father_phone') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Penghasilan Ayah</label>
                    <input type="number" name="father_income" value="{{ old('father_income') }}" class="input-field">
                </div>
            </div>
            
            <h4 class="font-medium text-gray-700 mb-3">Data Ibu</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label-field">Nama Ibu *</label>
                    <input type="text" name="mother_name" value="{{ old('mother_name') }}" required class="input-field">
                </div>
                <div>
                    <label class="label-field">NIK Ibu</label>
                    <input type="text" name="mother_nik" value="{{ old('mother_nik') }}" class="input-field" maxlength="16">
                </div>
                <div>
                    <label class="label-field">Pendidikan Ibu</label>
                    <input type="text" name="mother_education" value="{{ old('mother_education') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Pekerjaan Ibu</label>
                    <input type="text" name="mother_occupation" value="{{ old('mother_occupation') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">No. HP Ibu</label>
                    <input type="text" name="mother_phone" value="{{ old('mother_phone') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Penghasilan Ibu</label>
                    <input type="number" name="mother_income" value="{{ old('mother_income') }}" class="input-field">
                </div>
            </div>
        </div>
        
        <!-- Step 4: Data Sekolah Asal -->
        <div class="bg-white rounded-xl p-6 card-shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">4. Data Sekolah Asal</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label-field">Nama Sekolah Asal</label>
                    <input type="text" name="previous_school" value="{{ old('previous_school') }}" class="input-field">
                </div>
                <div>
                    <label class="label-field">Tahun Lulus</label>
                    <input type="number" name="graduation_year" value="{{ old('graduation_year') }}" class="input-field" min="2000" max="2030">
                </div>
                <div class="md:col-span-2">
                    <label class="label-field">Alamat Sekolah Asal</label>
                    <textarea name="previous_school_address" class="input-field" rows="3">{{ old('previous_school_address') }}</textarea>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end">
            <button type="submit" class="btn-primary">
                Simpan dan Lanjut Upload Dokumen →
            </button>
        </div>
    </form>
</div>
@endsection