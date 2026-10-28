<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineExamAssignmentResource\Pages;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\OnlineExamAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnlineExamAssignmentResource extends Resource
{
    protected static ?string $model = OnlineExamAssignment::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Peserta Tes Online';
    protected static ?string $navigationGroup = 'Tes Online';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool { return OnlineExamResource::canViewAny(); }
    public static function canCreate(): bool { return static::canViewAny(); }

    public static function canView(Model $record): bool
    {
        return $record->exam !== null
            && app(OnlineExamAccess::class)->canManage(auth()->user(), $record->exam);
    }

    public static function canEdit(Model $record): bool { return static::canView($record); }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('exam', fn (Builder $q) =>
            app(OnlineExamAccess::class)->staffScope($q, auth()->user())
                ->whereHas('unit', fn (Builder $unit) => $unit->operational())
        )->with(['exam.unit', 'user'])->withCount('attempts');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('online_exam_id')
                ->label('Paket Tes')
                ->options(fn (): array => OnlineExam::query()
                    ->whereHas('unit', fn (Builder $q) => $q->operational())
                    ->when(auth()->user()?->isAdminUnit(), fn (Builder $q) => $q->where('unit_id', auth()->user()->unit_id))
                    ->orderBy('title')->pluck('title', 'id')->all())
                ->searchable()->required(),
            Forms\Components\Select::make('user_id')
                ->label('Akun Pendaftar')
                ->helperText('Penugasan tidak bergantung pada tahap SPMB. Akun harus aktif dan memiliki role pendaftar.')
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where('is_active', true)
                    ->whereHas('roles', fn (Builder $q) => $q->where('name', 'pendaftar'))
                    ->where(fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'))
                    ->limit(30)->get()
                    ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' · '.$u->email])->all())
                ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->email)
                ->searchable()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('exam.unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('exam.title')->label('Paket Tes'),
            Tables\Columns\TextColumn::make('user.name')->label('Peserta')->searchable(),
            Tables\Columns\TextColumn::make('user.email')->label('Email'),
            Tables\Columns\TextColumn::make('attempts_count')->label('Percobaan')->badge(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('toggleActive')
                ->label(fn (OnlineExamAssignment $record): string => $record->is_active ? 'Cabut Akses' : 'Aktifkan Akses')
                ->requiresConfirmation()
                ->visible(fn (OnlineExamAssignment $record): bool => static::canEdit($record))
                ->action(function (OnlineExamAssignment $record): void {
                    abort_unless(static::canEdit($record), 403);
                    $previouslyActive = $record->is_active;
                    $record->update(['is_active' => ! $previouslyActive]);

                    app(AuditTrail::class)->record(
                        $previouslyActive ? 'online_exam.assignment_revoked' : 'online_exam.assignment_reactivated',
                        $record,
                        actor: auth()->user(),
                        unitId: $record->exam->unit_id,
                        description: $previouslyActive
                            ? 'Admin mencabut akses Tes Online peserta.'
                            : 'Admin mengaktifkan kembali akses Tes Online peserta.',
                    );
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlineExamAssignments::route('/'),
            'create' => Pages\CreateOnlineExamAssignment::route('/create'),
            'bulk' => Pages\BulkAssignOnlineExamAssignments::route('/bulk'),
        ];
    }
}
