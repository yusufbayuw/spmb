<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Publikasi Hasil';

    protected static ?string $modelLabel = 'Publikasi Hasil';

    protected static ?string $pluralModelLabel = 'Publikasi Hasil';

    protected static ?string $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_id')
                ->relationship('registration', 'registration_number')
                ->label('Pendaftaran')
                ->searchable()
                ->preload()
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('title')
                ->label('Judul'),
            Forms\Components\Textarea::make('message')
                ->label('Pesan')
                ->rows(5),
            Forms\Components\Select::make('status')
                ->label('Status Publikasi')
                ->options([
                    'draft' => 'Belum Dipublikasikan',
                    'published' => 'Sudah Dipublikasikan',
                ])
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')
                    ->label('No. Registrasi'),
                Tables\Columns\TextColumn::make('registration.full_name')
                    ->label('Calon Siswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration.selection.decision')
                    ->label('Keputusan')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'accepted' => 'Diterima',
                        'rejected' => 'Ditolak',
                        'waiting_list' => 'Daftar Tunggu',
                        default => 'Belum Diputuskan',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'waiting_list' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Publikasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'published'
                        ? 'Sudah Dipublikasikan'
                        : 'Belum Dipublikasikan')
                    ->color(fn (?string $state): string => $state === 'published' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Dipublikasikan Pada')
                    ->dateTime('d/m/Y H:i', timezone: config('app.timezone'))
                    ->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label('Publikasikan Hasil')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publikasikan hasil seleksi?')
                    ->modalDescription('Setelah dipublikasikan, keputusan menjadi hasil resmi yang dapat dilihat calon siswa dan proses pendaftaran diselesaikan.')
                    ->visible(fn (Announcement $record): bool => (bool) auth()->user()?->can('publish_announcement')
                        && $record->status !== 'published'
                        && $record->registration?->current_stage === 'announcement'
                    )
                    ->action(fn (Announcement $record) => app(RegistrationWorkflowService::class)->publish(
                        $record->registration,
                        auth()->user(),
                        $record->title,
                        $record->message,
                    )),
                Tables\Actions\EditAction::make()
                    ->label('Edit Draft')
                    ->visible(fn (Announcement $record): bool => $record->status !== 'published'
                        && $record->registration?->current_stage === 'announcement'
                    ),
                Tables\Actions\Action::make('correctPublishedDecision')
                    ->label('Koreksi Hasil')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (Announcement $record): bool => (bool) auth()->user()?->can('decide_selection')
                        && (bool) auth()->user()?->can('publish_announcement')
                        && app(RegistrationWorkflowService::class)->canCorrectPublished($record)
                    )
                    ->fillForm(fn (Announcement $record): array => [
                        'decision' => $record->registration?->selection?->decision,
                        'reason' => null,
                        'acknowledge' => false,
                    ])
                    ->form([
                        Forms\Components\Select::make('decision')
                            ->label('Keputusan Baru')
                            ->options([
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Daftar Tunggu',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Koreksi')
                            ->helperText('Alasan wajib diisi dan dicatat pada Audit Log.')
                            ->required(),
                        Forms\Components\Checkbox::make('acknowledge')
                            ->label('Saya memahami bahwa hasil ini sudah dipublikasikan dan koreksi akan diberitahukan kembali kepada pendaftar.')
                            ->accepted()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Koreksi hasil yang sudah dipublikasikan?')
                    ->modalDescription('Koreksi hanya diizinkan sebelum peserta memasuki daftar ulang/enrollment atau menerima penawaran. Sistem akan menyelaraskan tahap, kuota, dan pengumuman.')
                    ->action(function (Announcement $record, array $data): void {
                        app(RegistrationWorkflowService::class)->correctPublishedDecision(
                            $record,
                            auth()->user(),
                            $data['decision'],
                            $data['reason'],
                        );

                        Notification::make()
                            ->title('Hasil terpublikasi berhasil dikoreksi')
                            ->body('Pendaftar akan menerima pengumuman koreksi terbaru.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('publishSelected')
                    ->label('Publikasikan Terpilih')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('publish_announcement'))
                    ->requiresConfirmation()
                    ->modalHeading('Publikasikan semua hasil terpilih?')
                    ->modalDescription('Semua record terpilih harus masih berstatus draft dan memiliki keputusan final yang valid.')
                    ->action(function (Collection $records): void {
                        $count = app(RegistrationWorkflowService::class)->bulkPublishAnnouncements(
                            $records,
                            auth()->user(),
                        );

                        Notification::make()
                            ->title($count.' pengumuman berhasil dipublikasikan')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('status', 'draft')
            ->whereHas('registration', fn (Builder $query) => $query
                ->where('lifecycle_status', 'active')
                ->where('current_stage', 'announcement'))
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Announcement
            && $record->status !== 'published'
            && $record->registration?->current_stage === 'announcement'
            && parent::canEdit($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'registration.unit',
            'registration.selection',
        ]);

        if (auth()->user()?->isTU()) {
            $query->whereHas(
                'registration',
                fn (Builder $registrationQuery) => $registrationQuery->where('unit_id', auth()->user()->unit_id),
            );
        }

        return $query;
    }
}
