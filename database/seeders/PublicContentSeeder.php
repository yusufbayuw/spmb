<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Support\SpmbOperationalMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class PublicContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUnitProfilesAndFaqs();

        if (SpmbOperationalMode::allowsHigherEducation()) {
            $this->seedHigherEducationPrograms();
        }
    }

    private function seedUnitProfilesAndFaqs(): void
    {
        Unit::query()
            ->forOperationalMode()
            ->orderBy('id')
            ->each(function (Unit $unit): void {
                $this->fillMissing($unit, [
                    'public_headline' => $unit->isHigherEducation()
                        ? 'Penerimaan Mahasiswa Baru '.$unit->name
                        : 'Penerimaan '.$unit->name,
                    'public_body' => $unit->isHigherEducation()
                        ? '<p>Temukan program studi, jalur pendaftaran, jadwal, dan tahapan Penerimaan Mahasiswa Baru melalui portal resmi ini.</p><p>Pilih pembukaan yang sesuai, lengkapi data dengan benar, lalu pantau perkembangan pendaftaran melalui akun pendaftar.</p>'
                        : '<p>Temukan jadwal, jalur, persyaratan, dan tahapan penerimaan melalui portal resmi ini.</p><p>Pilih pembukaan yang tersedia, lengkapi data calon peserta, lalu pantau perkembangan pendaftaran melalui akun pendaftar.</p>',
                ]);

                $this->faq(
                    unit: $unit,
                    question: 'Bagaimana cara memulai pendaftaran?',
                    answer: '<p>Pilih pembukaan pendaftaran yang masih aktif, buat atau masuk ke akun pendaftar, kemudian lengkapi formulir sesuai petunjuk yang tampil pada setiap tahap.</p>',
                    sortOrder: 10,
                );

                $this->faq(
                    unit: $unit,
                    question: 'Dokumen apa yang perlu disiapkan?',
                    answer: '<p>Dokumen mengikuti konfigurasi unit dan jalur pendaftaran yang dipilih. Setelah pendaftaran dibuat, sistem akan menampilkan dokumen yang wajib maupun opsional beserta petunjuk unggahnya.</p>',
                    sortOrder: 20,
                );

                $this->faq(
                    unit: $unit,
                    question: 'Bagaimana memantau status pendaftaran?',
                    answer: '<p>Status dan tahapan pendaftaran dapat dipantau melalui dashboard pendaftar. Ikuti aksi dan petunjuk pada tahap yang sedang aktif agar proses dapat berlanjut.</p>',
                    sortOrder: 30,
                );

                $regular = RegistrationPathway::query()
                    ->where('unit_id', $unit->id)
                    ->where('name', 'Reguler')
                    ->first();

                if ($regular) {
                    $this->faq(
                        unit: $unit,
                        pathway: $regular,
                        question: 'Apa yang dimaksud jalur Reguler?',
                        answer: '<p>Jalur Reguler adalah jalur penerimaan umum pada unit ini. Ketentuan, jadwal, dokumen, tes, dan biaya yang berlaku mengikuti pembukaan pendaftaran yang dipilih.</p>',
                        sortOrder: 40,
                    );
                }
            });
    }

    private function seedHigherEducationPrograms(): void
    {
        $content = [
            'S1-MNJ' => [
                'headline' => 'Bangun fondasi manajemen, kewirausahaan, dan bisnis digital',
                'body' => '<p>Program Manajemen berfokus pada pemahaman organisasi, pengambilan keputusan, kewirausahaan, dan dinamika bisnis modern.</p><p>Halaman ini membantu calon mahasiswa memahami gambaran program sebelum memilih gelombang dan jalur pendaftaran.</p>',
                'highlights' => [
                    ['title' => 'Fondasi Manajemen', 'description' => 'Mengenal cara organisasi merencanakan, menjalankan, dan mengevaluasi aktivitas bisnis.'],
                    ['title' => 'Kewirausahaan', 'description' => 'Mengembangkan cara berpikir bisnis dan pemahaman peluang usaha.'],
                    ['title' => 'Bisnis Digital', 'description' => 'Memahami perubahan proses bisnis dalam lingkungan digital.'],
                ],
                'audiences' => [
                    ['title' => 'Tertarik pada bisnis', 'description' => 'Cocok bagi calon mahasiswa yang ingin memahami organisasi, pasar, dan pengelolaan bisnis.'],
                    ['title' => 'Ingin membangun usaha', 'description' => 'Relevan bagi calon mahasiswa yang ingin mengembangkan wawasan kewirausahaan.'],
                ],
            ],
            'S1-IF' => [
                'headline' => 'Bangun solusi digital melalui komputasi dan teknologi informasi',
                'body' => '<p>Program Informatika mempelajari komputasi dan teknologi informasi sebagai fondasi untuk membangun, mengembangkan, dan mengevaluasi solusi digital.</p><p>Calon mahasiswa dapat menggunakan halaman ini untuk memahami gambaran program sebelum melanjutkan ke proses pendaftaran.</p>',
                'highlights' => [
                    ['title' => 'Komputasi', 'description' => 'Membangun dasar berpikir komputasional dan pemecahan masalah.'],
                    ['title' => 'Pengembangan Sistem', 'description' => 'Mengenal proses membangun solusi perangkat lunak dan sistem digital.'],
                    ['title' => 'Teknologi Informasi', 'description' => 'Memahami penggunaan teknologi dalam berbagai kebutuhan organisasi dan masyarakat.'],
                ],
                'audiences' => [
                    ['title' => 'Suka memecahkan masalah', 'description' => 'Cocok bagi calon mahasiswa yang menikmati tantangan logika dan penyelesaian masalah.'],
                    ['title' => 'Tertarik teknologi', 'description' => 'Relevan bagi calon mahasiswa yang ingin memahami bagaimana solusi digital dibangun.'],
                ],
            ],
            'S1-SD' => [
                'headline' => 'Ubah data menjadi analisis dan dasar pengambilan keputusan',
                'body' => '<p>Program Sains Data berfokus pada analisis data, pengenalan pola, tren, dan penggunaan data untuk mendukung pengambilan keputusan.</p><p>Program ini menggabungkan cara berpikir kuantitatif, komputasi, dan interpretasi data.</p>',
                'highlights' => [
                    ['title' => 'Analisis Data', 'description' => 'Mempelajari cara mengolah dan membaca informasi dari data.'],
                    ['title' => 'Pola dan Tren', 'description' => 'Mengidentifikasi pola yang membantu memahami suatu fenomena.'],
                    ['title' => 'Keputusan Berbasis Data', 'description' => 'Menghubungkan hasil analisis dengan kebutuhan pengambilan keputusan.'],
                ],
                'audiences' => [
                    ['title' => 'Nyaman dengan angka dan pola', 'description' => 'Cocok bagi calon mahasiswa yang tertarik pada analisis kuantitatif.'],
                    ['title' => 'Tertarik pada data dan teknologi', 'description' => 'Relevan bagi calon mahasiswa yang ingin menggunakan data untuk memahami masalah nyata.'],
                ],
            ],
            'S1-RL' => [
                'headline' => 'Pelajari sistem logistik, rantai pasok, transportasi, dan distribusi',
                'body' => '<p>Program Rekayasa Logistik mempelajari bagaimana barang, informasi, transportasi, dan proses distribusi dikelola sebagai suatu sistem.</p><p>Calon mahasiswa dapat memahami gambaran program dan proses penerimaan melalui halaman ini.</p>',
                'highlights' => [
                    ['title' => 'Rantai Pasok', 'description' => 'Memahami hubungan antarproses dari pemasok hingga pengguna akhir.'],
                    ['title' => 'Transportasi', 'description' => 'Mengenal peran transportasi dalam sistem logistik.'],
                    ['title' => 'Distribusi', 'description' => 'Mempelajari pengelolaan aliran barang dan layanan distribusi.'],
                ],
                'audiences' => [
                    ['title' => 'Tertarik sistem operasional', 'description' => 'Cocok bagi calon mahasiswa yang ingin memahami proses bisnis dari ujung ke ujung.'],
                    ['title' => 'Suka optimasi proses', 'description' => 'Relevan bagi calon mahasiswa yang tertarik pada efisiensi dan pengelolaan aliran barang.'],
                ],
            ],
            'S1-SM' => [
                'headline' => 'Kembangkan kemampuan artistik, kreativitas, dan teknologi musik',
                'body' => '<p>Program Seni Musik mengembangkan kemampuan artistik dan kreativitas dengan tetap memperhatikan perkembangan teknologi dalam praktik musik.</p><p>Informasi pada halaman ini membantu calon mahasiswa memahami gambaran program dan jalur penerimaannya.</p>',
                'highlights' => [
                    ['title' => 'Kemampuan Artistik', 'description' => 'Mengembangkan kepekaan dan kemampuan dalam praktik seni musik.'],
                    ['title' => 'Kreativitas', 'description' => 'Mendorong eksplorasi gagasan dan karya musik.'],
                    ['title' => 'Teknologi Musik', 'description' => 'Mengenal pemanfaatan teknologi dalam kegiatan dan produksi musik.'],
                ],
                'audiences' => [
                    ['title' => 'Aktif bermusik', 'description' => 'Cocok bagi calon mahasiswa yang ingin mengembangkan kemampuan musik secara lebih terarah.'],
                    ['title' => 'Tertarik karya kreatif', 'description' => 'Relevan bagi calon mahasiswa yang ingin mengeksplorasi kreativitas melalui musik.'],
                ],
            ],
            'D3-SEK' => [
                'headline' => 'Siapkan kompetensi profesional administrasi dan pengelolaan perkantoran',
                'body' => '<p>Program Sekretari menyiapkan keterampilan profesional dalam administrasi, komunikasi, dan pengelolaan kegiatan perkantoran.</p><p>Calon mahasiswa dapat mempelajari gambaran program sekaligus melihat jalur dan gelombang penerimaan yang tersedia.</p>',
                'highlights' => [
                    ['title' => 'Administrasi Profesional', 'description' => 'Membangun keterampilan pengelolaan administrasi yang rapi dan sistematis.'],
                    ['title' => 'Komunikasi', 'description' => 'Mengembangkan kemampuan komunikasi dalam lingkungan profesional.'],
                    ['title' => 'Pengelolaan Perkantoran', 'description' => 'Memahami proses kerja dan dukungan operasional perkantoran.'],
                ],
                'audiences' => [
                    ['title' => 'Suka pekerjaan terstruktur', 'description' => 'Cocok bagi calon mahasiswa yang nyaman dengan koordinasi dan administrasi.'],
                    ['title' => 'Tertarik dunia profesional', 'description' => 'Relevan bagi calon mahasiswa yang ingin membangun keterampilan praktis untuk lingkungan kerja.'],
                ],
            ],
            'D3-PM' => [
                'headline' => 'Kembangkan keterampilan pertunjukan dan praktik profesional musik',
                'body' => '<p>Program Penyaji Musik berfokus pada keterampilan pertunjukan, produksi, dan praktik profesional di bidang musik.</p><p>Halaman ini memberikan gambaran program sebelum calon mahasiswa memilih jalur dan memulai proses pendaftaran.</p>',
                'highlights' => [
                    ['title' => 'Pertunjukan', 'description' => 'Mengembangkan kemampuan penyajian musik dalam konteks pertunjukan.'],
                    ['title' => 'Produksi', 'description' => 'Mengenal proses produksi yang mendukung karya dan pertunjukan musik.'],
                    ['title' => 'Praktik Profesional', 'description' => 'Membangun pemahaman terhadap kebutuhan praktik musik secara profesional.'],
                ],
                'audiences' => [
                    ['title' => 'Ingin fokus pada performa', 'description' => 'Cocok bagi calon mahasiswa yang ingin memperdalam keterampilan penyajian musik.'],
                    ['title' => 'Tertarik industri musik', 'description' => 'Relevan bagi calon mahasiswa yang ingin memahami praktik profesional dalam bidang musik.'],
                ],
            ],
        ];

        StudyProgram::query()
            ->forOperationalMode()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->each(function (StudyProgram $program) use ($content): void {
                $programContent = $content[$program->code] ?? null;

                if (! $programContent) {
                    return;
                }

                $this->fillMissing($program, [
                    'public_headline' => $programContent['headline'],
                    'public_body' => $programContent['body'],
                    'study_duration' => $program->degree_level === 'D3'
                        ? '6 semester / 3 tahun'
                        : '8 semester / 4 tahun',
                    'public_highlights' => $programContent['highlights'],
                    'public_target_audiences' => $programContent['audiences'],
                ]);

                $this->faq(
                    unit: $program->unit,
                    program: $program,
                    question: 'Apa gambaran utama '.$program->label().'?',
                    answer: '<p>'.$program->description.'</p><p>Untuk detail persyaratan dan jadwal, pilih pembukaan pendaftaran yang tersedia pada program studi ini.</p>',
                    sortOrder: 50,
                );
            });
    }

    private function fillMissing(Model $model, array $attributes): void
    {
        $changes = [];

        foreach ($attributes as $attribute => $value) {
            if (blank($model->getAttribute($attribute))) {
                $changes[$attribute] = $value;
            }
        }

        if ($changes !== []) {
            $model->fill($changes)->save();
        }
    }

    private function faq(
        Unit $unit,
        string $question,
        string $answer,
        int $sortOrder,
        ?StudyProgram $program = null,
        ?RegistrationPathway $pathway = null,
    ): void {
        Faq::query()->firstOrCreate(
            [
                'unit_id' => $unit->id,
                'study_program_id' => $program?->id,
                'registration_pathway_id' => $pathway?->id,
                'question' => $question,
            ],
            [
                'answer' => $answer,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }
}
