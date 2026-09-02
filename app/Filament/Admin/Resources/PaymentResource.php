<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\ApplicantUploadSecurity;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Pembayaran';
    protected static ?string $navigationGroup = 'Verifikasi';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_id')->relationship('registration', 'registration_number')->label('Pendaftaran')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('va_number')->label('VA')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('amount')->label('Nominal dari Pembukaan')->prefix('Rp')->disabled()->dehydrated(false),
            Forms\Components\Select::make('status')->options([
                'pending'=>'Menunggu','paid'=>'Bukti Diunggah','verified'=>'Terverifikasi','rejected'=>'Ditolak',
            ])->disabled()->dehydrated(false),
            Forms\Components\Textarea::make('note')->label('Catatan Internal'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi')->searchable(),
                Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),
                Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('va_number')->label('VA')->copyable(),
                Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('proof_malware_scan_status')
                    ->label('Security')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'clean'=>'AV Clean','unavailable'=>'AV Opsional','scan_error'=>'AV Error',default=>'Belum Scan',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'clean'=>'success','unavailable'=>'warning','scan_error'=>'danger',default=>'gray',
                    }),
                Tables\Columns\TextColumn::make('proof_original_name')
                    ->label('Bukti')
                    ->url(fn (Payment $record): ?string => $record->proof_path ? route('files.applicant.payments.proof', $record) : null)
                    ->default('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending'=>'Menunggu','paid'=>'Bukti Diunggah','verified'=>'Terverifikasi','rejected'=>'Ditolak',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verifikasi')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                    ->visible(fn (Payment $record) => auth()->user()?->can('verify_payment_payment') && $record->status === 'paid' && $record->registration?->isOperational())
                    ->action(function (Payment $record): void {
                        if ($record->proof_path && (! $record->proof_security_scanned_at || ! $record->proof_sha256)) {
                            $inspection = app(ApplicantUploadSecurity::class)->inspect($record->proof_path);
                            $record->update([
                                'proof_mime_type' => $inspection['mime_type'],
                                'proof_sha256' => $inspection['sha256'],
                                'proof_malware_scan_status' => $inspection['malware_scan_status'],
                                'proof_security_scanned_at' => $inspection['security_scanned_at'],
                            ]);
                        }

                        app(RegistrationWorkflowService::class)->verifyPayment($record, auth()->user(), true);
                        Notification::make()->title('Pembayaran aman dan terverifikasi')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')->color('danger')
                    ->visible(fn (Payment $record) => auth()->user()?->can('verify_payment_payment') && $record->status === 'paid' && $record->registration?->isOperational())
                    ->form([Forms\Components\Textarea::make('reason')->label('Alasan')->required()])
                    ->action(function (Payment $record, array $data): void {
                        app(RegistrationWorkflowService::class)->verifyPayment($record, auth()->user(), false, $data['reason']);
                        Notification::make()->title('Pembayaran ditolak')->warning()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['registration.unit', 'registration.opening', 'verifier']);
        if (auth()->user()?->isTU()) {
            $query->whereHas('registration', fn (Builder $registration) => $registration->where('unit_id', auth()->user()->unit_id));
        }
        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('status', 'paid')
            ->whereHas('registration', fn (Builder $q) => $q->where('lifecycle_status', 'active'))
            ->count();
    }

    public static function canCreate(): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
