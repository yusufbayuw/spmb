@extends('layouts.app')

@section('title', 'Selamat Datang di SPMB Taruna Bakti')

@section('content')
<div class="text-center py-12">
    <div class="max-w-3xl mx-auto">
        <div class="w-24 h-24 bg-blue-800 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl">
            <span class="text-white text-4xl font-bold">TB</span>
        </div>

        <h1 class="text-4xl font-bold text-gray-800 mb-4">Selamat Datang di SPMB Taruna Bakti</h1>

        <p class="text-lg text-gray-600 mb-8">
            Sistem Penerimaan Murid Baru untuk Daycare, KB, TK, SD, SMP, dan SMA Taruna Bakti
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12">
            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Daftar Online</h3>
                <p class="text-gray-600 text-sm">Satu akun dapat digunakan untuk mendaftarkan satu atau beberapa calon siswa.</p>
            </div>

            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Progres Terpadu</h3>
                <p class="text-gray-600 text-sm">Pantau validasi data, pembayaran, dokumen, tes, seleksi, hingga pengumuman dari satu dashboard.</p>
            </div>

            <div class="bg-white rounded-xl p-6 card-shadow">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Multi Unit</h3>
                <p class="text-gray-600 text-sm">Mendukung pendaftaran seluruh unit pendidikan Taruna Bakti dalam sistem yang sama.</p>
            </div>
        </div>

        <div class="mt-12 space-x-4">
            @auth
                @php
                    $portalUrl = auth()->user()->hasAnyRole(['super_admin', 'tu']) ? url('/admin') : url('/pendaftar');
                @endphp
                <a href="{{ $portalUrl }}" class="btn-primary inline-block">Buka Dashboard</a>
            @else
                <a href="{{ url('/pendaftar/register') }}" class="btn-primary inline-block">Daftar Sekarang</a>
                <a href="{{ url('/pendaftar/login') }}" class="inline-block px-6 py-3 bg-white text-blue-800 rounded-lg font-semibold hover:bg-gray-50 transition shadow-md">Login</a>
            @endauth
        </div>
    </div>
</div>
@endsection
