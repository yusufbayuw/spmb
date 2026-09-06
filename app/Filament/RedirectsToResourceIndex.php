<?php

namespace App\Filament;

trait RedirectsToResourceIndex
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
