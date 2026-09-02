<?php

namespace App\Filament\Applicant\Pages;

use App\Models\ParentInfo;
use App\Models\Registration;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Get;
use Illuminate\Support\Facades\DB;

class RegistrationCreate extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Daftar Calon Siswa';
    protected static ?string $title = 'Pendaftaran Calon Siswa';
    protected static ?string $slug = 'daftar-baru';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.applicant.pages.registration-create';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'registrant_type' => 'parent',
            'religion' => 'Islam',
        ]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pendaftaran')
                    ->description('Pilih siapa yang melakukan pendaftaran dan unit pendidikan tujuan.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Select::make('registrant_type')
                            ->label('Pendaftar')
                            ->options([
                                'parent' => 'Orang tua / wali mendaftarkan anak',
                                'self' => 'Calon siswa mendaftar sendiri',
                            ])
                            ->required()
                            ->live(),
                        Select::make('registrant_relationship')
                            ->label('Hubungan dengan calon siswa')
                            ->options([
                                'father' => 'Ayah',
                                'mother' => 'Ibu',
                                'guardian' => 'Wali',
                                'other' => 'Lainnya',
                            ])
                            ->required(fn (Get $get): bool => $get('registrant_type') === 'parent')
                            ->visible(fn (Get $get): bool => $get('registrant_type') === 'parent'),
                        Select::make('unit_id')
                            ->label('Unit pendidikan tujuan')
                            ->options(fn (): array => Unit::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),

                Section::make('Data Calon Siswa')
                    ->description('Isi sesuai dokumen identitas calon siswa.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('nik')->label('NIK')->required()->length(16)->rule('digits:16')->unique(Registration::class, 'nik'),
                        TextInput::make('full_name')->label('Nama lengkap')->required()->maxLength(150),
                        TextInput::make('nickname')->label('Nama panggilan')->maxLength(50),
                        Select::make('gender')->label('Jenis kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])->required(),
                        TextInput::make('birth_place')->label('Tempat lahir')->required()->maxLength(100),
                        DatePicker::make('birth_date')->label('Tanggal lahir')->required()->native(false)->maxDate(now()->subDay()),
                        TextInput::make('religion')->label('Agama')->maxLength(50),
                        TextInput::make('phone')->label('Telepon calon siswa')->tel()->maxLength(20),
                        TextInput::make('email')->label('Email calon siswa')->email()->maxLength(100),
                        TextInput::make('previous_school')->label('Sekolah asal')->maxLength(150),
                        TextInput::make('graduation_year')->label('Tahun lulus')->numeric()->minValue(2000)->maxValue(now()->year + 2),
                        Textarea::make('home_address')->label('Alamat rumah')->required()->rows(3)->columnSpanFull(),
                    ])->columns(2),

                Section::make('Data Orang Tua')
                    ->description('Data ini digunakan untuk administrasi dan komunikasi selama proses SPMB.')
                    ->icon('heroicon-o-users')
                    ->schema([
                        TextInput::make('father_name')->label('Nama ayah')->required()->maxLength(150),
                        TextInput::make('father_nik')->label('NIK ayah')->maxLength(16),
                        TextInput::make('father_occupation')->label('Pekerjaan ayah')->maxLength(100),
                        TextInput::make('father_phone')->label('Telepon ayah')->tel()->maxLength(20),
                        TextInput::make('father_income')->label('Penghasilan ayah')->numeric()->minValue(0)->prefix('Rp'),
                        TextInput::make('mother_name')->label('Nama ibu')->required()->maxLength(150),
                        TextInput::make('mother_nik')->label('NIK ibu')->maxLength(16),
                        TextInput::make('mother_occupation')->label('Pekerjaan ibu')->maxLength(100),
                        TextInput::make('mother_phone')->label('Telepon ibu')->tel()->maxLength(20),
                        TextInput::make('mother_income')->label('Penghasilan ibu')->numeric()->minValue(0)->prefix('Rp'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $registration = DB::transaction(function () use ($data): Registration {
            $registration = Registration::create([
                'user_id' => auth()->id(),
                'unit_id' => $data['unit_id'],
                'registrant_type' => $data['registrant_type'],
                'registrant_relationship' => $data['registrant_type'] === 'self' ? 'self' : ($data['registrant_relationship'] ?? null),
                'nik' => $data['nik'],
                'full_name' => $data['full_name'],
                'nickname' => $data['nickname'] ?? null,
                'gender' => $data['gender'],
                'birth_place' => $data['birth_place'],
                'birth_date' => $data['birth_date'],
                'religion' => $data['religion'] ?? 'Islam',
                'home_address' => $data['home_address'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'previous_school' => $data['previous_school'] ?? null,
                'graduation_year' => $data['graduation_year'] ?? null,
                'status' => 'submitted',
                'current_stage' => 'data_validation',
                'data_validation_status' => 'pending',
                'submitted_at' => now(),
            ]);

            ParentInfo::create([
                'registration_id' => $registration->id,
                'father_name' => $data['father_name'],
                'father_nik' => $data['father_nik'] ?? null,
                'father_occupation' => $data['father_occupation'] ?? null,
                'father_phone' => $data['father_phone'] ?? null,
                'father_income' => $data['father_income'] ?? null,
                'mother_name' => $data['mother_name'],
                'mother_nik' => $data['mother_nik'] ?? null,
                'mother_occupation' => $data['mother_occupation'] ?? null,
                'mother_phone' => $data['mother_phone'] ?? null,
                'mother_income' => $data['mother_income'] ?? null,
            ]);

            return $registration;
        });

        Notification::make()
            ->title('Pendaftaran berhasil dikirim')
            ->body('Data calon siswa menunggu validasi Tata Usaha.')
            ->success()
            ->send();

        $this->redirect(RegistrationStatus::getUrl(['registration' => $registration->id]));
    }
}
