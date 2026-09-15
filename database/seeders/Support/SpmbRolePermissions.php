<?php

namespace Database\Seeders\Support;

final class SpmbRolePermissions
{
    /**
     * Permission set historically owned by TU before Admin Unit was introduced.
     * Admin Unit intentionally keeps this exact baseline.
     *
     * @return list<string>
     */
    public static function adminUnit(): array
    {
        return [
            'view_registration', 'view_any_registration', 'update_registration', 'validate_data_registration', 'send_va_registration', 'issue_card_registration',
            'view_registrationopening', 'view_any_registrationopening', 'create_registrationopening', 'update_registrationopening',
            'view_registrationpathway', 'view_any_registrationpathway', 'create_registrationpathway', 'update_registrationpathway',
            'view_studyprogram', 'view_any_studyprogram', 'create_studyprogram', 'update_studyprogram',
            'view_parentinfo', 'view_any_parentinfo', 'update_parentinfo',
            'view_document', 'view_any_document', 'update_document', 'verify_document_document',
            'view_payment', 'view_any_payment', 'create_payment', 'update_payment', 'verify_payment_payment',
            'view_virtualaccount', 'view_any_virtualaccount', 'create_virtualaccount', 'update_virtualaccount',
            'view_unit', 'view_any_unit',
            'view_admissiontest', 'view_any_admissiontest', 'create_admissiontest', 'update_admissiontest',
            'view_admissiontestresult', 'view_any_admissiontestresult', 'create_admissiontestresult', 'update_admissiontestresult', 'record_result_admissiontestresult',
            'view_selection', 'view_any_selection', 'create_selection', 'update_selection', 'decide_selection',
            'view_selectionbatch', 'view_any_selectionbatch', 'create_selectionbatch', 'update_selectionbatch', 'finalize_selectionbatch',
            'view_admissionquota', 'view_any_admissionquota', 'create_admissionquota', 'update_admissionquota',
            'view_reregistrationitem', 'view_any_reregistrationitem', 'update_reregistrationitem', 'enroll_registration',
            'view_announcement', 'view_any_announcement', 'create_announcement', 'update_announcement', 'publish_announcement',
            'view_auditlog', 'view_any_auditlog',
        ];
    }

    /**
     * TU is an operational role. Configuration SPMB and Sistem & Akses are
     * deliberately excluded while the day-to-day admission workflow remains.
     *
     * @return list<string>
     */
    public static function tu(): array
    {
        return [
            'view_registration', 'view_any_registration', 'update_registration', 'validate_data_registration', 'send_va_registration', 'issue_card_registration',
            'view_document', 'view_any_document', 'update_document', 'verify_document_document',
            'view_payment', 'view_any_payment', 'create_payment', 'update_payment', 'verify_payment_payment',
            'view_admissiontestresult', 'view_any_admissiontestresult', 'create_admissiontestresult', 'update_admissiontestresult', 'record_result_admissiontestresult',
            'view_selection', 'view_any_selection', 'create_selection', 'update_selection', 'decide_selection',
            'view_selectionbatch', 'view_any_selectionbatch', 'create_selectionbatch', 'update_selectionbatch', 'finalize_selectionbatch',
            'view_reregistrationitem', 'view_any_reregistrationitem', 'update_reregistrationitem', 'enroll_registration',
            'view_announcement', 'view_any_announcement', 'create_announcement', 'update_announcement', 'publish_announcement',
        ];
    }

    /** @return list<string> */
    public static function required(): array
    {
        return array_values(array_unique([
            ...self::adminUnit(),
            ...self::tu(),
        ]));
    }
}
