@extends('layouts.app')

@section('title', 'Dashboard - SPMB Taruna Bakti')

@section('content')
<div class="flex flex-col md:flex-row gap-6">
    <!-- Sidebar -->
    <div class="md:w-1/4">
        <div class="sidebar p-6">
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="text-blue-800 text-2xl font-bold">{{ substr(auth()->user()->name, 0, 1) }}</span>
                </div>
                <h3 class="font-semibold text-gray-800">{{ auth()->user()->name }}</h3>
                <p class="text-sm text-gray-500">{{ auth()->user()->email }}</p>
            </div>
            
            <nav>
                <a href="{{ route('dashboard') }}" class="sidebar-link active">
                    📋 Pendaftaran Saya
                </a>
                <a href="{{ route('registration.create') }}" class="sidebar-link">
                    ➕ Daftar Baru
                </a>
                <a href="{{ route('profile.edit') }}" class="sidebar-link">
                    👤 Profil
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:w-3/4">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Pendaftaran Saya</h2>
            <a href="{{ route('registration.create') }}" class="btn-primary inline-block">
                + Daftar Baru
            </a>
        </div>

        @if($registrations->isEmpty())
            <div class="bg-white rounded-xl p-12 text-center card-shadow">
                <div class="text-6xl mb-4">📝</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Pendaftaran</h3>
                <p class="text-gray-500 mb-6">Anda belum mendaftarkan anak atau diri Anda.</p>
                <a href="{{ route('registration.create') }}" class="btn-primary inline-block">
                    Mulai Pendaftaran
                </a>
            </div>
        @else
            <div class="space-y-4">
                @foreach($registrations as $reg)
                <div class="bg-white rounded-xl p-6 card-shadow">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">{{ $reg->full_name }}</h3>
                            <p class="text-sm text-gray-500">NIK: {{ $reg->nik }}</p>
                        </div>
                        @php
                            $statusColors = [
                                'draft' => 'badge-info',
                                'submitted' => 'badge-warning',
                                'verified' => 'badge-info',
                                'payment_pending' => 'badge-warning',
                                'payment_uploaded' => 'badge-info',
                                'payment_verified' => 'badge-success',
                                'accepted' => 'badge-success',
                                'rejected' => 'badge-danger',
                                'waiting_list' => 'badge-warning',
                            ];
                            $statusLabels = [
                                'draft' => 'Draft',
                                'submitted' => 'Menunggu Verifikasi',
                                'verified' => 'Terverifikasi',
                                'payment_pending' => 'Menunggu Pembayaran',
                                'payment_uploaded' => 'Bukti Terupload',
                                'payment_verified' => 'Pembayaran Terverifikasi',
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Waiting List',
                            ];
                        @endphp
                        <span class="{{ $statusColors[$reg->status] ?? 'badge-info' }}">
                            {{ $statusLabels[$reg->status] ?? $reg->status }}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <span class="text-sm text-gray-500">Unit:</span>
                            <span class="font-medium">{{ $reg->unit->name }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">No. Registrasi:</span>
                            <span class="font-medium">{{ $reg->registration_number ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Tanggal Daftar:</span>
                            <span class="font-medium">{{ $reg->created_at->format('d M Y') }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Jenis Kelamin:</span>
                            <span class="font-medium">{{ $reg->gender == 'L' ? 'Laki-laki' : 'Perempuan' }}</span>
                        </div>
                    </div>
                    
                    @if($reg->status === 'verified')
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <h4 class="font-semibold text-blue-800 mb-2">💳 Pembayaran</h4>
                            @if($reg->payments->isNotEmpty())
                                @foreach($reg->payments as $payment)
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-sm">Nomor VA: <strong>{{ $payment->va_number }}</strong></p>
                                            <p class="text-sm">Jumlah: <strong>Rp {{ number_format($payment->amount, 0, ',', '.') }}</strong></p>
                                        </div>
                                        <span class="badge-info">{{ $payment->status }}</span>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-sm text-blue-700">Menunggu nomor VA dari pihak sekolah.</p>
                            @endif
                        </div>
                    @endif
                    
                    @if($reg->status === 'accepted')
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-2">🎉 Selamat!</h4>
                            <p class="text-sm text-green-700">Anda diterima di {{ $reg->unit->name }} Taruna Bakti.</p>
                            <p class="text-sm text-green-700 mt-2">Nomor Registrasi: <strong>{{ $reg->registration_number }}</strong></p>
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection