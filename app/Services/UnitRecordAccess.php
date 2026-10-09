<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Defense-in-depth for Filament record policies. Resource query filters are
 * useful for lists, but must never be the only tenant boundary for a record.
 */
class UnitRecordAccess
{
    public function allows(User $actor, Model $record): bool
    {
        if (! $actor->is_active || ! $actor->hasOperationalUnitAccess()) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if (! $actor->isTU() || ! $actor->unit_id) {
            return false;
        }

        $unitId = $this->unitId($record);

        // Unknown or ownerless records must fail closed. Global resources
        // need a dedicated policy, not an implicit tenant bypass.
        return $unitId !== null && $unitId > 0 && $unitId === (int) $actor->unit_id;
    }

    private function unitId(Model $record): ?int
    {
        $table = $record->getTable();

        if ($table === 'units') {
            return (int) $record->getKey();
        }

        $direct = [
            'users', 'registrations', 'registration_openings', 'registration_pathways',
            'study_programs', 'admission_tests', 'virtual_accounts',
            'continuation_candidates', 'faqs', 'unit_configurations', 'audit_logs',
        ];

        if (in_array($table, $direct, true)) {
            return (int) ($record->getAttribute('unit_id') ?? 0);
        }

        $byRegistration = [
            'parent_infos', 'documents', 'payments', 'admission_test_results',
            'selections', 'announcements', 'admission_offers',
            're_registration_items', 'test_bookings', 'registration_consents',
            'registration_achievements', 'registration_academic_scores',
            'continuation_registration_links',
        ];

        if (in_array($table, $byRegistration, true)) {
            $id = $record->getAttribute('registration_id');

            return $id ? (int) DB::table('registrations')->where('id', $id)->value('unit_id') : 0;
        }

        if (in_array($table, ['admission_quotas', 'selection_batches'], true)) {
            $id = $record->getAttribute('registration_opening_id');

            return $id ? (int) DB::table('registration_openings')->where('id', $id)->value('unit_id') : 0;
        }

        if ($table === 'test_sessions') {
            $id = $record->getAttribute('admission_test_id');

            return $id ? (int) DB::table('admission_tests')->where('id', $id)->value('unit_id') : 0;
        }

        if ($table === 'payment_receipts') {
            $paymentId = $record->getAttribute('payment_id');
            $registrationId = $paymentId ? DB::table('payments')->where('id', $paymentId)->value('registration_id') : null;

            return $registrationId ? (int) DB::table('registrations')->where('id', $registrationId)->value('unit_id') : 0;
        }

        if ($table === 'virtual_account_batches') {
            $units = DB::table('virtual_accounts')->where('batch_id', $record->getKey())
                ->distinct()->pluck('unit_id');

            return $units->count() === 1 ? (int) $units->first() : 0;
        }

        // A newly added table cannot inherit access accidentally.
        return null;
    }
}
