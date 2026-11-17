<?php

declare(strict_types=1);

namespace App\Enum\Service;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a member offers (#683). Every per-kind rule is an exhaustive match here, so a new kind fails PHPStan until it
 * declares each of them.
 */
enum ServiceKind: string implements TranslatableInterface
{
    case Teaching = 'teaching';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }

    /** The type its views are counted under: teaching keeps the one its views were counted under as a teacher profile. */
    public function viewableType(): string
    {
        return match ($this) {
            self::Teaching => 'teacher_profile',
        };
    }

    /**
     * What its page shows, in the order the page reads: its completeness is the share of them filled in.
     *
     * @return list<ServicePart>
     */
    public function parts(): array
    {
        return match ($this) {
            self::Teaching => [
                ServicePart::Headline,
                ServicePart::Description,
                ServicePart::Instruments,
                ServicePart::StudentLevels,
                ServicePart::AgeGroups,
                ServicePart::LevelGoals,
                ServicePart::Songs,
                ServicePart::Places,
                ServicePart::Availability,
                ServicePart::Offers,
            ],
        };
    }

    /** @return list<ServicePart> what it needs before it can be published */
    public function requiredToPublish(): array
    {
        return match ($this) {
            self::Teaching => [ServicePart::Headline, ServicePart::Description, ServicePart::Places, ServicePart::Offers],
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Teaching => 'service_kind.teaching',
        }, [], 'reference', $locale);
    }
}
