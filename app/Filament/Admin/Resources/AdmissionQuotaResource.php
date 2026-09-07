<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdmissionQuotaResource\Pages;
use App\Models\AdmissionQuota;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionQuotaResource extends Resource
{
    protected static ?string $model = AdmissionQuota::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Daya Tampung';

    protected static ?string $navigationGroup = 'Konfigurasi SPMB';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_opening_id')
                ->label('Pembukaan Pendaftaran')
                ->options(fn (): array => RegistrationOpening::query()->when(auth()->user()?->isTU(), fn (Builder $q) => $q->where('unit_id', auth()->user()->unit_id))->with('studyProgram')->get()->mapWithKeys(fn (RegistrationOpening $opening): array => [$opening->id => $opening->label()])->all())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('registration_pathway_id')
                ->label('Jalur Pendaftaran')
                ->options(fn (Forms\Get $get): array => RegistrationPathway::query()->where('unit_id', RegistrationOpening::query()->whereKey($get('registration_opening_id'))->value('unit_id'))->active()->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\TextInput::make('capacity')->label('Daya tampung penerimaan')->integer()->minValue(0)->required(),
            Forms\Components\TextInput::make('offer_expires_in_hours')->label('Batas konfirmasi (jam)')->integer()->minValue(1)->required(),
            Forms\Components\TextInput::make('re_registration_due_in_days')->label('Target daftar ulang (hari)')->integer()->minValue(1)->required(),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('opening.academic_year')->label('Tahun'),
            Tables\Columns\TextColumn::make('opening.wave')->label('Gelombang'),
            Tables\Columns\TextColumn::make('pathway.name')->label('Jalur')->placeholder('Semua jalur'),
            Tables\Columns\TextColumn::make('capacity')->label('Daya Tampung')->numeric(),
            Tables\Columns\TextColumn::make('offer_expires_in_hours')->label('Konfirmasi')->suffix(' jam'),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['opening.studyProgram', 'pathway'])
            ->when(auth()->user()?->isTU(), fn (Builder $q) => $q->whereHas('opening', fn (Builder $opening) => $opening->where('unit_id', auth()->user()->unit_id)));
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdmissionQuotas::route('/'), 'create' => Pages\CreateAdmissionQuota::route('/create'), 'edit' => Pages\EditAdmissionQuota::route('/{record}/edit')];
    }
}
