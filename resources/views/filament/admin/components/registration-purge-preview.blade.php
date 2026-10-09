<div class="space-y-4 text-sm">
    <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
        <p class="font-bold">PENGHAPUSAN PERMANEN — TIDAK DAPAT DIBATALKAN</p>
        <p class="mt-2">Anda akan menghapus pendaftaran <strong>{{ $registration->registration_number ?: $registration->uuid }}</strong> ({{ $registration->full_name }}) beserta seluruh relasi peserta yang tercantum di bawah. Proses pendaftaran, pembayaran, kartu, hasil tes, dan tautan dokumen tidak bisa digunakan lagi.</p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-left">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 font-semibold">Data yang dihapus dari database</th>
                    <th class="px-3 py-2 text-right font-semibold">Baris</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                <tr><td class="px-3 py-2 font-semibold">Pendaftaran utama</td><td class="px-3 py-2 text-right">1</td></tr>
                @foreach ($preview['counts'] as $item)
                    <tr>
                        <td class="px-3 py-2">{{ $item['label'] }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($item['count'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
        <p><strong>Berkas yang ikut dihapus:</strong> {{ number_format(count($preview['files']), 0, ',', '.') }} file milik pendaftaran ini, termasuk dokumen, foto, bukti pembayaran, dan berkas daftar ulang di direktori khusus pendaftaran.</p>
        <p class="mt-2"><strong>Pool VA:</strong> {{ $preview['virtual_accounts'] }} VA terkait akan dilepas dan ditandai <strong>Dibatalkan</strong>, bukan dikembalikan ke pool tersedia.</p>
        <p class="mt-2"><strong>Jejak audit:</strong> {{ $preview['audit_records'] }} catatan audit lama dipertahankan; referensi ke pendaftaran yang dihapus menjadi kosong. Tindakan penghapusan baru tetap dicatat.</p>
    </div>

    <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
        <strong>Tidak ikut dihapus:</strong> akun login orang tua/pendaftar (mungkin dipakai pada pendaftaran lain), pengaturan unit, gelombang, jalur, kuota, pool VA, serta berkas pra-pendaftaran milik akun bersama. Email terkirim, backup, dan data yang telah diekspor tidak dapat ditarik kembali.
    </div>
    <p class="font-semibold">Pastikan backup dan kewajiban retensi arsip/kuitansi telah dipenuhi sebelum melanjutkan.</p>
</div>
