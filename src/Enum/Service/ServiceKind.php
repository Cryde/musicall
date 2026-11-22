<?php

declare(strict_types=1);

namespace App\Enum\Service;

use App\Enum\Message\ContactOriginType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a member offers (#683). Every per-kind rule is an exhaustive match here, so a new kind fails PHPStan until it
 * declares each of them.
 */
enum ServiceKind: string implements TranslatableInterface
{
    case Teaching = 'teaching';
    case SoundEngineering = 'sound_engineering';

    /** The budget « Demander un devis » offers whatever the kind (#1408), in euros. */
    private const array QUOTE_BUDGETS = ['under_100', '100_to_300', '300_to_1000', 'over_1000'];

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
            self::SoundEngineering => 'service',
        };
    }

    /** What a message sent from its page records (#1408): teaching keeps the type installed app builds read. */
    public function contactOriginType(): ContactOriginType
    {
        return match ($this) {
            self::Teaching => ContactOriginType::TeacherProfile,
            self::SoundEngineering => ContactOriginType::Service,
        };
    }

    /** @return list<OfferUnit> what its offers may be priced in */
    public function offerUnits(): array
    {
        return match ($this) {
            self::Teaching => [OfferUnit::Lesson, OfferUnit::Package],
            self::SoundEngineering => [OfferUnit::Track, OfferUnit::Hour, OfferUnit::Day, OfferUnit::Flat],
        };
    }

    /** The unit its lowest price is quoted in: « dès 25 € les 30 min », « dès 80 € le titre » (#1415). */
    public function headlineUnit(): OfferUnit
    {
        return match ($this) {
            self::Teaching => OfferUnit::Lesson,
            self::SoundEngineering => OfferUnit::Track,
        };
    }

    /**
     * Its page on the site, in the default locale: `/u/{username}/teacher`. PageVersionsResolver matches the same
     * segments.
     */
    public function pagePath(string $username): string
    {
        return sprintf('/u/%s/%s', rawurlencode($username), match ($this) {
            self::Teaching => 'teacher',
            self::SoundEngineering => 'sound-engineer',
        });
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
            self::SoundEngineering => [
                ServicePart::Headline,
                ServicePart::Description,
                ServicePart::Specialities,
                ServicePart::BeforeAfter,
                ServicePart::Works,
                ServicePart::Offers,
                ServicePart::Places,
                ServicePart::Gear,
                ServicePart::Styles,
                ServicePart::Turnaround,
                ServicePart::Formats,
            ],
        };
    }

    /** @return list<ServicePart> what it needs before it can be published */
    public function requiredToPublish(): array
    {
        return match ($this) {
            self::Teaching => [ServicePart::Headline, ServicePart::Description, ServicePart::Places, ServicePart::Offers],
            self::SoundEngineering => [ServicePart::Headline, ServicePart::Description, ServicePart::Specialities, ServicePart::Places, ServicePart::Offers],
        };
    }

    /**
     * The answers « Demander un devis » takes (#1408), in asking order: only `need` is required. Null when the kind
     * takes no quote request: teaching keeps its lesson request.
     */
    public function quoteQuestions(): ?Assert\Collection
    {
        return match ($this) {
            self::Teaching => null,
            self::SoundEngineering => new Assert\Collection(fields: [
                'need' => new Assert\Required(new Assert\Sequentially([new Assert\NotBlank(), new Assert\Choice(choices: ['mixing', 'mastering', 'recording', 'live'])])),
                'tracks' => new Assert\Optional([new Assert\Type('int'), new Assert\Range(min: 1, max: 100)]),
                'link' => new Assert\Optional(new Assert\Sequentially([new Assert\Type('string'), new Assert\Url(requireTld: true), new Assert\Length(max: 255)])),
                'deadline' => new Assert\Optional(new Assert\Sequentially([new Assert\Type('string'), new Assert\Date()])),
                'budget' => new Assert\Optional([new Assert\Choice(choices: self::QUOTE_BUDGETS)]),
            ]),
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Teaching => 'service_kind.teaching',
            self::SoundEngineering => 'service_kind.sound_engineering',
        }, [], 'reference', $locale);
    }
}
