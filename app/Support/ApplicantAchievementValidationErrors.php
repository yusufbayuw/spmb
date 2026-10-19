<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ApplicantAchievementValidationErrors
{
    /**
     * Map supplementary validation errors from dehydrated list indexes back to
     * the keyed Filament repeater rows displayed on the applicant form.
     *
     * @param array<string|int, mixed> $repeaterState
     */
    public static function forForm(ValidationException $exception, array $repeaterState): ValidationException
    {
        $keys = array_keys($repeaterState);
        $mapped = [];

        foreach ($exception->errors() as $field => $messages) {
            if ($field === 'achievements' || $field === 'items') {
                $target = 'data.achievements';
            } elseif (preg_match('/^(?:achievements|items)\.(\d+)\.(.+)$/', $field, $parts)) {
                $index = (int) $parts[1];
                $target = 'data.achievements.'.($keys[$index] ?? $index).'.'.$parts[2];
            } else {
                $target = $field;
            }

            $mapped[$target] = array_merge($mapped[$target] ?? [], (array) $messages);
        }

        return ValidationException::withMessages($mapped);
    }
}
