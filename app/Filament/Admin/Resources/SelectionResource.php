<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SelectionResource\Pages;
use App\Models\Selection;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SelectionResource extends Resource
{
    protected static ?string $model = Selection::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Penetapan Hasil';

    protected static ?string $modelLabel = 'Hasil Seleksi';

    protected static ?string $pluralModelLabel = 'Penetapan Hasil';

    protected static ?string $navigationGroup = 'Seleksi';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_id')
                ->relationship('registration', 'registration_number')
                ->label('Pendaftaran')
                ->searchable()
                ->preload()
                ->disabled(),
            Forms\Components\Select::make('decision')
                ->label('Keputusan')
                ->options([
                    'pending' => 'Belum Diputuskan',
                    'accepted' => 'Diterima',
                    'rejected' => 'Ditolak',
                    'waiting_list' => 'Daftar Tunggu',
                ])
                ->disabled(),
            Forms\Components\TextInput::make('final_score')
                ->label('Nilai Akhir')
                ->numeric()
                ->disabled(),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')
                    ->label('No. Registrasi'),
                Tables\Columns\TextColumn::make('registration.full_name')
                    ->label('Calon Siswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration.unit.name')
                    ->label('Unit')
                    ->badge(),
                Tables\Columns\TextColumn::make('final_score')
                    ->label('Nilai Akhir'),
                Tables\Columns\TextColumn::make('decision')
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
                Tables\Columns\TextColumn::make('registration.announcement.status')
                    ->label('Publikasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'published' => 'Sudah Dipublikasikan',
                        'draft' => 'Belum Dipublikasikan',
                        default => 'Belum Tersedia',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('decide')
                    ->label('Tetapkan Hasil')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Selection $record): bool =>
                        (bool) auth()->user()?->can('decide_selection')
                        && $record->registration?->current_stage === 'selection'
                    )
                    ->form([
                        Forms\Components\Select::make('decision')
                            ->label('Keputusan')
                            ->options([
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'waiting_list' => 'Daftar Tunggu',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('final_score')
                            ->label('Nilai Akhir')
                            ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan'),
                    ])
                    ->action(fn (Selection $record, array $data) => app(RegistrationWorkflowService::class)->decide(
                        $record->registration,
                        auth()->user(),
                        $data['decision'],
                        $data['final_score'] ?? null,
                        $data['notes'] ?? null,
                    )),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSelections::route('/'),
            'create' => Pages\CreateSelection::route('/create'),
            'edit' => Pages\EditSelection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'registration.unit',
            'registration.announcement',
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
