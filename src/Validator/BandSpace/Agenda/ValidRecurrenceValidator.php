<?php

declare(strict_types=1);

namespace App\Validator\BandSpace\Agenda;

use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidRecurrenceValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidRecurrence) {
            throw new UnexpectedTypeException($constraint, ValidRecurrence::class);
        }

        if ($value === null) {
            return;
        }

        $frequencyRaw = $this->readProperty($value, 'recurrenceFrequency');
        if ($frequencyRaw === null || $frequencyRaw === '') {
            return;
        }

        $frequency = AgendaRecurrenceFrequency::tryFrom((string) $frequencyRaw);
        if ($frequency === null) {
            $this->context->buildViolation($constraint->invalidFrequencyMessage)
                ->atPath('recurrenceFrequency')
                ->setCode(ValidRecurrence::INVALID_FREQUENCY_CODE)
                ->addViolation();

            return;
        }

        $eventDatetime = $this->parseEventDatetime($this->readProperty($value, 'eventDatetime'));

        $untilRaw = $this->readProperty($value, 'recurrenceUntilDate');
        if ($untilRaw === null || $untilRaw === '') {
            $this->context->buildViolation($constraint->missingUntilMessage)
                ->atPath('recurrenceUntilDate')
                ->setCode(ValidRecurrence::MISSING_UNTIL_CODE)
                ->addViolation();
        } else {
            try {
                $until = new DateTimeImmutable((string) $untilRaw);
            } catch (\Exception) {
                $this->context->buildViolation($constraint->invalidUntilMessage)
                    ->atPath('recurrenceUntilDate')
                    ->setCode(ValidRecurrence::INVALID_UNTIL_CODE)
                    ->addViolation();
                $until = null;
            }

            if ($until instanceof DateTimeImmutable && $eventDatetime instanceof DateTimeImmutable) {
                $eventDate = new DateTimeImmutable($eventDatetime->format('Y-m-d'));
                $untilDate = new DateTimeImmutable($until->format('Y-m-d'));

                if ($untilDate < $eventDate) {
                    $this->context->buildViolation($constraint->untilBeforeEventMessage)
                        ->atPath('recurrenceUntilDate')
                        ->setCode(ValidRecurrence::UNTIL_BEFORE_EVENT_CODE)
                        ->addViolation();
                } else {
                    $maxUntil = $eventDate->modify('+' . ValidRecurrence::MAX_YEARS_HORIZON . ' years');
                    if ($untilDate > $maxUntil) {
                        $this->context->buildViolation($constraint->untilTooFarMessage)
                            ->atPath('recurrenceUntilDate')
                            ->setCode(ValidRecurrence::UNTIL_TOO_FAR_CODE)
                            ->addViolation();
                    }
                }
            }
        }

        if ($frequency === AgendaRecurrenceFrequency::Monthly) {
            $modeRaw = $this->readProperty($value, 'recurrenceMonthlyMode');
            if ($modeRaw === null || $modeRaw === '') {
                $this->context->buildViolation($constraint->missingMonthlyModeMessage)
                    ->atPath('recurrenceMonthlyMode')
                    ->setCode(ValidRecurrence::MISSING_MONTHLY_MODE_CODE)
                    ->addViolation();
            } elseif (AgendaRecurrenceMonthlyMode::tryFrom((string) $modeRaw) === null) {
                $this->context->buildViolation($constraint->invalidMonthlyModeMessage)
                    ->atPath('recurrenceMonthlyMode')
                    ->setCode(ValidRecurrence::INVALID_MONTHLY_MODE_CODE)
                    ->addViolation();
            }
        }
    }

    private function readProperty(object $value, string $name): mixed
    {
        return property_exists($value, $name) ? $value->{$name} : null;
    }

    private function parseEventDatetime(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
