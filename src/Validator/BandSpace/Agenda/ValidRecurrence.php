<?php

declare(strict_types=1);

namespace App\Validator\BandSpace\Agenda;

use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint on AgendaEntryCreate / AgendaEntryResource validating the recurrence
 * fields as a unit: when `recurrenceFrequency` is set, `recurrenceUntilDate` must be present, on or
 * after the first occurrence, and not too far out; when frequency is `monthly`,
 * `recurrenceMonthlyMode` is required.
 *
 * Whether the horizon is a date at all is not this constraint's business: that is `Assert\Date` on
 * the property, which also refuses an impossible calendar day. Doing it here as well would report
 * one mistake twice.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidRecurrence extends Constraint
{
    public const string INVALID_FREQUENCY_CODE = 'music_all_2b3d4f1a-8c2e-4cce-9c8d-0d2c70a8ab21';
    public const string MISSING_UNTIL_CODE = 'music_all_9a5d6c2b-71ff-4f0b-8a3a-22f9c1b5d4e9';
    public const string UNTIL_BEFORE_EVENT_CODE = 'music_all_77ab2cf8-5a0b-4b6f-9e1e-1e5d2a9b6c70';
    public const string UNTIL_TOO_FAR_CODE = 'music_all_61bd3e10-7c8b-4f73-8e5b-9a1d4c2e7a30';
    public const string MISSING_MONTHLY_MODE_CODE = 'music_all_55f1c20a-4d3c-4d6b-90ef-8a3b2d1e9c10';
    public const string INVALID_MONTHLY_MODE_CODE = 'music_all_18e0a3d6-2b4f-4a92-8d6e-7c9b5e0d4f30';

    public const int MAX_YEARS_HORIZON = 5;

    public string $invalidFrequencyMessage = 'band_space.agenda.recurrence.frequency_invalid';
    public string $missingUntilMessage = 'band_space.agenda.recurrence.until_required';
    public string $untilBeforeEventMessage = 'band_space.agenda.recurrence.until_before_event';
    public string $untilTooFarMessage = 'band_space.agenda.recurrence.until_too_far';
    public string $missingMonthlyModeMessage = 'band_space.agenda.recurrence.monthly_mode_required';
    public string $invalidMonthlyModeMessage = 'band_space.agenda.recurrence.monthly_mode_invalid';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
