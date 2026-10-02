<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TrainingProgramResource\Pages;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TrainingProgramResource extends Resource
{
    protected static ?string $model = TrainingProgram::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Kelola Training';
    protected static ?string $modelLabel = 'Program Training';
    protected static ?string $pluralModelLabel = 'Program Training';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Program')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(50)
                        ->helperText('Kode boleh digunakan kembali pada versi baru. Kombinasi kode + versi harus unik.'),
                    Forms\Components\TextInput::make('version')->label('Versi')->required()->default('1.0')->maxLength(30),
                    Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(180),
                    Forms\Components\Select::make('target_role')->label('Role Sasaran')->options(TrainingProgram::ROLE_LABELS)->required(),
                    Forms\Components\Textarea::make('description')->label('Deskripsi')->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')->label('Urutan')->integer()->default(0),
                    Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
            Forms\Components\Repeater::make('modules')
                ->label('Modul & Materi')
                ->relationship('modules')
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->schema([
                    Forms\Components\TextInput::make('title')->label('Nama Modul')->required()->maxLength(180),
                    Forms\Components\Textarea::make('description')->label('Deskripsi')->rows(2),
                    Forms\Components\Toggle::make('is_required')->label('Wajib')->default(true),
                    Forms\Components\Repeater::make('lessons')
                        ->label('Materi')
                        ->relationship('lessons')
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(180),
                            Forms\Components\Select::make('type')->label('Jenis')->options(TrainingLesson::TYPES)->default('content')->required(),
                            Forms\Components\TextInput::make('duration_minutes')->label('Durasi (menit)')->integer()->minValue(1),
                            Forms\Components\Toggle::make('is_required')->label('Wajib')->default(true),
                            Forms\Components\TextInput::make('video_url')->label('URL Video')->url()->columnSpanFull(),
                            Forms\Components\RichEditor::make('content')
                                ->label('Isi Materi')
                                ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'undo', 'redo'])
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'md' => 2]),
                ])
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->badge()->searchable(),
            Tables\Columns\TextColumn::make('version')->label('Versi')->badge(),
            Tables\Columns\TextColumn::make('name')->label('Program')->searchable(),
            Tables\Columns\TextColumn::make('target_role')->label('Role')->badge()
                ->formatStateUsing(fn (string $state): string => TrainingProgram::ROLE_LABELS[$state] ?? $state),
            Tables\Columns\TextColumn::make('modules_count')->counts('modules')->label('Modul'),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingPrograms::route('/'),
            'create' => Pages\CreateTrainingProgram::route('/create'),
            'edit' => Pages\EditTrainingProgram::route('/{record}/edit'),
        ];
    }
}
