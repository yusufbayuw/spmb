<h2>Virtual Account Pendaftaran SPMB</h2>
<p>Yth. {{ $payment->registration->user->name }},</p>
<p>Data calon siswa <strong>{{ $payment->registration->full_name }}</strong> telah tervalidasi.</p>
<p>No. Registrasi: <strong>{{ $payment->registration->registration_number }}</strong></p>
<p>Virtual Account: <strong>{{ $payment->va_number }}</strong></p>
<p>Nominal: <strong>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></p>
<p>Silakan lakukan pembayaran dan unggah bukti pembayaran melalui dashboard SPMB.</p>
