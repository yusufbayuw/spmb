<?php

namespace Database\Seeders\Support;

trait GuardsDemoEnvironment
{
    protected function shouldSkipDemoData(): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo seeder dilewati di environment '.app()->environment().'.');

            return true;
        }

        return false;
    }
}
