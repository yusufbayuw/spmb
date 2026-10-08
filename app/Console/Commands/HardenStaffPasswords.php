<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HardenStaffPasswords extends Command
{
    protected $signature = 'spmb:harden-staff-passwords {--execute : Apply rotation after confirmation}';

    protected $description = 'Rotate administrator and TU passwords in non-production environments';

    public function handle(): int
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->components->error('Password rotation through this development command is blocked in production.');
            return self::FAILURE;
        }

        $staff = User::query()->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['super_admin', 'admin_unit', 'tu']))->get();
        $this->components->info("{$staff->count()} administrator/TU accounts would be rotated.");
        if (! $this->option('execute')) {
            $this->line('Dry run only. Add --execute to rotate passwords and revoke database sessions.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Rotate all staff passwords, revoke sessions, and require password recovery?', false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($staff): void {
            foreach ($staff as $user) {
                $user->forceFill([
                    'password' => Hash::make(Str::random(96)),
                    'remember_token' => Str::random(60),
                    'auth_version' => (int) $user->auth_version + 1,
                ])->save();
            }

            DB::table('password_reset_tokens')->whereIn('email', $staff->pluck('email'))->delete();
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $staff->pluck('id'))->delete();
            }
        });

        $this->components->warn('Passwords rotated; previous sessions are revoked by credential generation checks on authenticated routes. Staff must use password recovery.');
        return self::SUCCESS;
    }
}
