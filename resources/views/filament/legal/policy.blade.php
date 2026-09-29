<x-filament-panels::page.simple>
    <x-filament::section>
        <div class="legal-policy-content">
            {!! $policyContent !!}
        </div>
    </x-filament::section>

    <div class="flex justify-start">
        <x-filament::button
            tag="a"
            href="{{ url('/pendaftar/register') }}"
            color="gray"
            icon="heroicon-m-arrow-left"
        >
            Kembali ke pendaftaran akun
        </x-filament::button>
    </div>

    <style>
        .legal-policy-content {
            color: #111827;
            font-size: 0.95rem;
            line-height: 1.85;
        }

        .legal-policy-content p {
            margin: 0 0 1rem;
        }

        .legal-policy-content h2 {
            margin: 1.75rem 0 0.75rem;
            color: #111827;
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .legal-policy-content h3 {
            margin: 1.5rem 0 0.625rem;
            color: #111827;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .legal-policy-content ol,
        .legal-policy-content ul {
            margin: 0 0 1rem;
            padding-left: 1.5rem;
        }

        .legal-policy-content ol {
            list-style: decimal;
        }

        .legal-policy-content ul {
            list-style: disc;
        }

        .legal-policy-content li {
            margin-bottom: 0.625rem;
            padding-left: 0.2rem;
        }

        .legal-policy-content a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .legal-policy-content blockquote {
            margin: 1rem 0;
            border-left: 4px solid #d1d5db;
            padding-left: 1rem;
            color: #374151;
        }

        .dark .legal-policy-content,
        .dark .legal-policy-content h2,
        .dark .legal-policy-content h3 {
            color: #f3f4f6;
        }

        .dark .legal-policy-content a {
            color: #60a5fa;
        }

        .dark .legal-policy-content blockquote {
            border-left-color: #4b5563;
            color: #d1d5db;
        }
    </style>
</x-filament-panels::page.simple>
