<?php
namespace App\Filament\Admin\Resources\AdmissionTestResource\Pages;
use App\Filament\Admin\Resources\AdmissionTestResource;use Filament\Actions;use Filament\Resources\Pages\EditRecord;
class EditAdmissionTest extends EditRecord{protected static string $resource=AdmissionTestResource::class;protected function getHeaderActions():array{return [Actions\DeleteAction::make()];}}
