<?php
namespace App\Filament\Admin\Resources\RegistrationResource\Pages;use App\Filament\Admin\Resources\RegistrationResource;use Filament\Actions;use Filament\Resources\Pages\EditRecord;
class EditRegistration extends EditRecord{protected static string $resource=RegistrationResource::class;protected function getHeaderActions():array{return [Actions\DeleteAction::make()];}}
