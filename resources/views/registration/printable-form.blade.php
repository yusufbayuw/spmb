@extends('registration.print-layout')

@section('title', 'Formulir Pendaftaran')

@section('content')
    <p class="muted">Salinan data pendaftaran sesuai versi konfigurasi yang digunakan saat pendaftaran. Lampiran disimpan secara terpisah.</p>

    @foreach ($sections as $section)
        <section>
            <h2>{{ $section['title'] }}</h2>

            @if ($section['key'] === 'academic_scores')
                <table>
                    <thead>
                        <tr>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Komponen / Semester</th>
                            <th>Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['subject'] }}</td>
                                <td>{{ $row['grade'] }}</td>
                                <td>{{ $row['assessment'] }}</td>
                                <td>{{ $row['score'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif ($section['key'] === 'achievements')
                <table>
                    <thead>
                        <tr>
                            <th>Prestasi</th>
                            <th>Tingkat</th>
                            <th>Tahun</th>
                            <th>Penyelenggara</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['title'] }}</td>
                                <td>{{ $row['level'] }}</td>
                                <td>{{ $row['year'] }}</td>
                                <td>{{ $row['organizer'] }}</td>
                                <td>{{ $row['description'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                <th style="width: 34%; background: #fff;">{{ $row['label'] }}</th>
                                <td style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
@endsection
