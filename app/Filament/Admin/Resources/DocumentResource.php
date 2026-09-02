<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Verifikasi Dokumen';

    protected static ?string $modelLabel = 'Dokumen';

    protected static ?string $pluralModelLabel = 'Dokumen';

    protected static ?string $navigationGroup = 'Verifikasi';

    protected static ?int $navigationSort = 1;

    public static function typeOptions(): array
    {
        return [
            'report_card' => 'Raport Nilai',
            'family_card' => 'Kartu Keluarga',
            'birth_certificate' => 'Akta Kelahiran',
            'payment_proof' => 'Bukti Pembayaran',
            'supporting_document' => 'Dokumen Pendukung',
            'photo' => 'Foto Siswa',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dokumen')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('registration.registration_number')->label('No. Registrasi')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('registration.full_name')->label('Calon Siswa')->disabled()->dehydrated(false),
                    Forms\Components\Select::make('type')->label('Jenis Dokumen')->options(static::typeOptions())->required(),
                    Forms\Components\TextInput::make('original_name')->label('Nama File')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('file_type')->label('Tipe File')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('file_size')->label('Ukuran (byte)')->numeric()->disabled()->dehydrated(false),
                    Forms\Components\Toggle::make('is_verified')->label('Dokumen Terverifikasi')->live(),
                    Forms\Components\DateTimePicker::make('verified_at')->label('Diverifikasi pada')->disabled()->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi')->searchable()->default('-'),
                Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),
                Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn (string $state) => static::typeOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('original_name')
                    ->label('File')
                    ->limit(30)
                    ->url(fn (Document $record) => Storage::disk('public')->url($record->file_path))
                    ->openUrlInNewTab(),
                Tables\Columns\IconColumn::make('is_verified')->label('Terverifikasi')->boolean(),
                Tables\Columns\TextColumn::make('verifier.name')->label('Verifier')->default('-')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Diupload')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Jenis Dokumen')->options(static::typeOptions()),
                Tables\Filters\TernaryFilter::make('is_verified')->label('Status Verifikasi'),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record) => ! $record->is_verified)
                    ->action(fn (Document $record) => $record->update([
                        'is_verified' => true,
                        'verified_at' => now(),
                        'verified_by' => auth()->id(),
                    ])),
                Tables\Actions\Action::make('resetVerification')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record) => $record->is_verified)
                    ->action(fn (Document $record) => $record->update([
                        'is_verified' => false,
                        'verified_at' => null,
                        'verified_by' => null,
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['registration.unit', 'verifier']);
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->whereHas('registration', fn (Builder $query) => $query->where('unit_id', $user->unit_id));
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('is_verified', false)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
