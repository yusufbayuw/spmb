<?php

namespace App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages;

use App\Filament\Admin\Resources\OnlineExamQuestionResource;
use App\Models\Unit;
use App\Services\OnlineExamQuestionSpreadsheetService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ListOnlineExamQuestions extends ListRecords
{
    protected static string $resource = OnlineExamQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('downloadTemplate')
                ->label('Template Soal XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->form([$this->unitSelector()])
                ->action(fn (array $data) => app(OnlineExamQuestionSpreadsheetService::class)
                    ->download((int) $data['unit_id'], auth()->user(), true)),

            Actions\Action::make('exportQuestions')
                ->label('Ekspor Bank Soal XLSX')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    $this->unitSelector(),
                    Forms\Components\Placeholder::make('grading_warning')
                        ->label('Kerahasiaan Kunci Jawaban')
                        ->content('File ekspor memuat kunci jawaban semua soal unit ini. Simpan secara privat; jangan bagikan kepada peserta.'),
                ])
                ->action(fn (array $data) => app(OnlineExamQuestionSpreadsheetService::class)
                    ->download((int) $data['unit_id'], auth()->user())),

            Actions\Action::make('importQuestions')
                ->label('Impor Bank Soal XLSX')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    $this->unitSelector(),
                    Forms\Components\Placeholder::make('format_info')
                        ->label('Aturan Impor')
                        ->content('Download template terlebih dahulu. Maksimal 500 soal/5 MB. Impor hanya menambah soal, tidak menimpa yang ada. Seluruh file dibatalkan jika satu baris tidak valid. File berisi kunci jawaban rahasia.'),
                    Forms\Components\FileUpload::make('file')
                        ->label('File Bank Soal XLSX')
                        ->disk('local')
                        ->directory('imports/online-exam-questions')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(5120)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = app(OnlineExamQuestionSpreadsheetService::class)
                            ->import($data['file'], (int) $data['unit_id'], auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Impor dibatalkan')
                            ->body(collect($exception->errors())->flatten()->take(8)->implode("\n"))
                            ->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Bank soal berhasil diimpor')
                        ->body($result['imported'].' soal baru ditambahkan. Tidak ada soal lama yang diubah.')
                        ->success()->send();
                }),
        ];
    }

    private function unitSelector(): Forms\Components\Select
    {
        return Forms\Components\Select::make('unit_id')
            ->label('Unit Bank Soal')
            ->options(fn (): array => Unit::query()->operational()
                ->when(auth()->user()?->isAdminUnit(),
                    fn (Builder $q) => $q->whereKey(auth()->user()->unit_id))
                ->orderBy('name')->pluck('name', 'id')->all())
            ->default(fn () => auth()->user()?->isAdminUnit()
                ? auth()->user()->unit_id : null)
            ->disabled(fn (): bool => auth()->user()?->isAdminUnit() ?? false)
            ->dehydrated()->searchable()->required();
    }
}
