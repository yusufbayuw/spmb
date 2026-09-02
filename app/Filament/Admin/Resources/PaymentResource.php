<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\Registration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static ?string $navigationGroup = 'Verifikasi';

    protected static ?int $navigationSort = 2;

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Menunggu Pembayaran',
            'paid' => 'Sudah Dibayar',
            'verified' => 'Terverifikasi',
            'rejected' => 'Ditolak',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pembayaran')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('registration_id')
                        ->label('Calon Siswa')
                        ->relationship('registration', 'registration_number', function (Builder $query): Builder {
                            $user = auth()->user();
                            return $user?->isTU() && $user->unit_id
                                ? $query->where('unit_id', $user->unit_id)
                                : $query;
                        })
                        ->getOptionLabelFromRecordUsing(fn (Registration $record) => ($record->registration_number ?: '#'.$record->id).' — '.$record->full_name)
                        ->searchable(['registration_number', 'full_name', 'nik'])
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('va_number')->label('Virtual Account')->maxLength(50),
                    Forms\Components\TextInput::make('amount')->label('Nominal')->numeric()->prefix('Rp')->minValue(0)->required(),
                    Forms\Components\Select::make('status')->label('Status')->options(static::statusOptions())->default('pending')->required(),
                    Forms\Components\DateTimePicker::make('payment_date')->label('Tanggal Pembayaran')->seconds(false),
                    Forms\Components\TextInput::make('payment_method')->label('Metode Pembayaran')->maxLength(50),
                    Forms\Components\Textarea::make('note')->label('Catatan')->rows(3)->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('va_number')->label('Virtual Account')->searchable()->default('-')->copyable(),
                Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('payment_date')->label('Tanggal Bayar')->dateTime('d M Y H:i')->default('-')->sortable(),
                Tables\Columns\TextColumn::make('verifier.name')->label('Verifier')->default('-')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(static::statusOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => $record->status !== 'verified')
                    ->action(fn (Payment $record) => $record->update([
                        'status' => 'verified',
                        'verified_at' => now(),
                        'verified_by' => auth()->id(),
                    ])),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => $record->status !== 'rejected')
                    ->action(fn (Payment $record) => $record->update([
                        'status' => 'rejected',
                        'verified_at' => null,
                        'verified_by' => auth()->id(),
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
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
        return (string) static::getEloquentQuery()->whereIn('status', ['pending', 'paid'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
