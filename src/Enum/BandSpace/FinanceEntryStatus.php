<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum FinanceEntryStatus: string
{
    case Planned = 'planned';
    case Committed = 'committed';
    case Paid = 'paid';

    /**
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::Committed, self::Paid],
            self::Committed => [self::Planned, self::Paid],
            self::Paid => [self::Committed],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Prévu',
            self::Committed => 'Engagé',
            self::Paid => 'Payé',
        };
    }
}
