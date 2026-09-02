<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SPMB Taruna Bakti')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); min-height: 100vh; }
        .card-shadow { box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.06); transition: all 0.3s ease; }
        .card-shadow:hover { box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15), 0 6px 15px rgba(0, 0, 0, 0.08); transform: translateY(-2px); }
        .gradient-blue { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
        .btn-primary { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); }
        .btn-primary:hover { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5); transform: translateY(-1px); }
        .input-field { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; transition: all 0.3s; background: white; }
        .input-field:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .label-field { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px; }
        .sidebar { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); overflow: hidden; }
        .sidebar-link { display: block; padding: 12px 20px; color: #374151; font-weight: 500; transition: all 0.3s; border-left: 3px solid transparent; }
        .sidebar-link:hover, .sidebar-link.active { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; }
        .badge-success { background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-warning { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-danger { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-info { background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="gradient-blue text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('home') }}" class="flex items-center">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center mr-3">
                        <span class="text-blue-800 font-bold text-xl">TB</span>
                    </div>
                    <div>
                        <div class="font-bold text-lg">SPMB Taruna Bakti</div>
                        <div class="text-xs text-blue-200">Sistem Penerimaan Murid Baru</div>
                    </div>
                </a>

                <div class="flex items-center space-x-4">
                    @auth
                        @php
                            $portalUrl = auth()->user()->hasAnyRole(['super_admin', 'tu']) ? url('/admin') : url('/pendaftar');
                        @endphp
                        <a href="{{ $portalUrl }}" class="text-white hover:text-blue-200 font-medium">
                            Dashboard
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-white hover:text-blue-200 font-medium">Logout</button>
                        </form>
                    @else
                        <a href="{{ url('/pendaftar/login') }}" class="text-white hover:text-blue-200 font-medium">Login</a>
                        <a href="{{ url('/pendaftar/register') }}" class="bg-white text-blue-800 px-4 py-2 rounded-lg font-semibold hover:bg-blue-50 transition">Daftar</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 shadow-sm">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 shadow-sm">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-gray-500 text-sm">&copy; {{ date('Y') }} SPMB Taruna Bakti. All rights reserved.</div>
        </div>
    </footer>
</body>
</html>
