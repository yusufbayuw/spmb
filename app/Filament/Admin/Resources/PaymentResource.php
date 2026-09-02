<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PaymentResource extends Resource
{
    protected static ?string $model=Payment::class;
    protected static ?string $navigationIcon='heroicon-o-banknotes';
    protected static ?string $navigationLabel='Pembayaran';
    protected static ?string $navigationGroup='Verifikasi';
    protected static ?int $navigationSort=1;

    public static function form(Form $form): Form { return $form->schema([Forms\Components\Select::make('registration_id')->relationship('registration','registration_number')->label('Pendaftaran')->searchable()->preload()->required(),Forms\Components\TextInput::make('va_number')->label('VA')->required(),Forms\Components\TextInput::make('amount')->label('Nominal')->prefix('Rp')->numeric()->required(),Forms\Components\Select::make('status')->options(['pending'=>'Menunggu','paid'=>'Bukti Diunggah','verified'=>'Terverifikasi','rejected'=>'Ditolak'])->required(),Forms\Components\Textarea::make('note')->label('Catatan')]); }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at','desc')->columns([
            Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi')->searchable(),
            Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),
            Tables\Columns\TextColumn::make('registration.unit.name')->label('Unit')->badge(),
            Tables\Columns\TextColumn::make('va_number')->label('VA')->copyable(),
            Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('proof_original_name')->label('Bukti')->url(fn(Payment $record)=>$record->proof_path?Storage::disk('public')->url($record->proof_path):null)->openUrlInNewTab()->default('-'),
        ])->filters([Tables\Filters\SelectFilter::make('status')->options(['pending'=>'Menunggu','paid'=>'Bukti Diunggah','verified'=>'Terverifikasi','rejected'=>'Ditolak'])])->actions([
            Tables\Actions\Action::make('verify')->label('Verifikasi')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()->visible(fn(Payment $record)=>auth()->user()?->can('verify_payment_payment')&&$record->status==='paid')->action(function(Payment $record){app(RegistrationWorkflowService::class)->verifyPayment($record,auth()->user(),true);Notification::make()->title('Pembayaran terverifikasi')->success()->send();}),
            Tables\Actions\Action::make('reject')->label('Tolak')->color('danger')->visible(fn(Payment $record)=>auth()->user()?->can('verify_payment_payment')&&$record->status==='paid')->form([Forms\Components\Textarea::make('reason')->label('Alasan')->required()])->action(function(Payment $record,array $data){app(RegistrationWorkflowService::class)->verifyPayment($record,auth()->user(),false,$data['reason']);Notification::make()->title('Pembayaran ditolak')->warning()->send();}),
            Tables\Actions\EditAction::make(),Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array{return ['index'=>Pages\ListPayments::route('/'),'create'=>Pages\CreatePayment::route('/create'),'edit'=>Pages\EditPayment::route('/{record}/edit')];}
    public static function getEloquentQuery():Builder{$q=parent::getEloquentQuery()->with(['registration.unit','verifier']);if(auth()->user()?->isTU())$q->whereHas('registration',fn(Builder $x)=>$x->where('unit_id',auth()->user()->unit_id));return $q;}
    public static function getNavigationBadge():?string{return (string)static::getEloquentQuery()->where('status','paid')->count();}
}
