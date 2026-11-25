<?php

declare(strict_types=1);

namespace App\Enum\Service;

use App\Enum\Message\ContactOriginType;
use App\Enum\Musician\MediaPlatform;
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
    case Photography = 'photography';
    case Video = 'video';
    case GraphicDesign = 'graphic_design';
    case Web = 'web';

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
            self::SoundEngineering, self::Photography, self::Video, self::GraphicDesign, self::Web => 'service',
        };
    }

    /** What a message sent from its page records (#1408): teaching keeps the type installed app builds read. */
    public function contactOriginType(): ContactOriginType
    {
        return match ($this) {
            self::Teaching => ContactOriginType::TeacherProfile,
            self::SoundEngineering, self::Photography, self::Video, self::GraphicDesign, self::Web => ContactOriginType::Service,
        };
    }

    /** @return list<ServiceSpeciality> its specialities, its main search filter */
    public function specialities(): array
    {
        return match ($this) {
            self::Teaching => [],
            self::SoundEngineering => SoundEngineeringSpeciality::cases(),
            self::Photography => PhotographySpeciality::cases(),
            self::Video => VideoSpeciality::cases(),
            self::GraphicDesign => GraphicDesignSpeciality::cases(),
            self::Web => WebSpeciality::cases(),
        };
    }

    public function speciality(string $value): ?ServiceSpeciality
    {
        return array_find($this->specialities(), static fn (ServiceSpeciality $speciality): bool => $speciality->value === $value);
    }

    /**
     * Whether it may be given remotely: its form offers it and its search card says so. A photographer or a
     * videographer comes to the client or works in their studio (#1411); a graphic designer or a webmaster mostly
     * works remotely (#1412).
     */
    public function worksRemotely(): bool
    {
        return match ($this) {
            self::Teaching, self::SoundEngineering, self::GraphicDesign, self::Web => true,
            self::Photography, self::Video => false,
        };
    }

    /**
     * The unit the price its page and card lead with is read in: the cheapest offer in it, or the cheapest of any unit
     * without one (Service::headlineOffer()). Null where no unit says it better than another.
     */
    public function leadOfferUnit(): ?OfferUnit
    {
        return match ($this) {
            self::Teaching => OfferUnit::Lesson,
            self::SoundEngineering => OfferUnit::Track,
            self::Photography, self::Video, self::GraphicDesign, self::Web => null,
        };
    }

    /** @return list<OfferUnit> what its offers may be priced in */
    public function offerUnits(): array
    {
        return match ($this) {
            self::Teaching => [OfferUnit::Lesson, OfferUnit::Package],
            self::SoundEngineering => [OfferUnit::Track, OfferUnit::Hour, OfferUnit::Day, OfferUnit::Flat],
            // No price per photo (#1411).
            self::Photography, self::Video => [OfferUnit::Hour, OfferUnit::Day, OfferUnit::Event, OfferUnit::Flat],
            // Priced by the project, or by the time it takes (#1412).
            self::GraphicDesign, self::Web => [OfferUnit::Flat, OfferUnit::Hour, OfferUnit::Day],
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
            self::Photography => 'photographer',
            self::Video => 'videographer',
            self::GraphicDesign => 'graphic-designer',
            self::Web => 'webmaster',
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
            // Shown by what they made (#1411, #1412): « Réalisations » first.
            self::Photography, self::Video, self::GraphicDesign, self::Web => [
                ServicePart::Headline,
                ServicePart::Description,
                ServicePart::Specialities,
                ServicePart::Works,
                ServicePart::Offers,
                ServicePart::Places,
                ServicePart::Styles,
                ServicePart::Delivery,
            ],
        };
    }

    /** @return list<ServicePart> what it needs before it can be published */
    public function requiredToPublish(): array
    {
        return match ($this) {
            self::Teaching => [ServicePart::Headline, ServicePart::Description, ServicePart::Places, ServicePart::Offers],
            self::SoundEngineering, self::Photography, self::Video, self::GraphicDesign, self::Web => [ServicePart::Headline, ServicePart::Description, ServicePart::Specialities, ServicePart::Places, ServicePart::Offers],
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
                'need' => self::needQuestion(['mixing', 'mastering', 'recording', 'live']),
                'tracks' => new Assert\Optional([new Assert\Type('int'), new Assert\Range(min: 1, max: 100)]),
                'link' => self::linkQuestion(),
                'deadline' => self::dateQuestion(),
                'budget' => self::budgetQuestion(),
            ]),
            // What (#1408), a date and a city.
            self::Photography => self::eventQuote([PhotographySpeciality::Concert, PhotographySpeciality::Promo, PhotographySpeciality::Cover]),
            self::Video => self::eventQuote([VideoSpeciality::Clip, VideoSpeciality::LiveSession, VideoSpeciality::ConcertFilm]),
            self::GraphicDesign => self::projectQuote(GraphicDesignSpeciality::cases()),
            self::Web => self::projectQuote(WebSpeciality::cases()),
        };
    }

    /**
     * What a link on its page may embed: Vimeo only where no installed app build reads it (#1411, #1412).
     *
     * @return list<MediaPlatform>
     */
    public function mediaPlatforms(): array
    {
        return match ($this) {
            self::Teaching, self::SoundEngineering => MediaPlatform::READ_BY_APP,
            self::Photography, self::Video, self::GraphicDesign, self::Web => [...MediaPlatform::READ_BY_APP, MediaPlatform::VIMEO],
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::Teaching => 'service_kind.teaching',
            self::SoundEngineering => 'service_kind.sound_engineering',
            self::Photography => 'service_kind.photography',
            self::Video => 'service_kind.video',
            self::GraphicDesign => 'service_kind.graphic_design',
            self::Web => 'service_kind.web',
        }, [], 'reference', $locale);
    }

    /** @param list<ServiceSpeciality> $needs */
    private static function eventQuote(array $needs): Assert\Collection
    {
        return new Assert\Collection(fields: [
            'need' => self::needQuestion(self::valuesOf($needs)),
            'date' => self::dateQuestion(),
            'city' => new Assert\Optional(new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 100)])),
            'budget' => self::budgetQuestion(),
        ]);
    }

    /**
     * What (#1412), a link to the music it is made for, and when: the work is done remotely, so no place is asked.
     *
     * @param list<ServiceSpeciality> $needs
     */
    private static function projectQuote(array $needs): Assert\Collection
    {
        return new Assert\Collection(fields: [
            'need' => self::needQuestion(self::valuesOf($needs)),
            'link' => self::linkQuestion(),
            'deadline' => self::dateQuestion(),
            'budget' => self::budgetQuestion(),
        ]);
    }

    /** @param list<string|int> $choices */
    private static function needQuestion(array $choices): Assert\Required
    {
        return new Assert\Required(new Assert\Sequentially([new Assert\NotBlank(), new Assert\Choice(choices: $choices)]));
    }

    /** Shown to the member as it was typed, never opened by the server. */
    private static function linkQuestion(): Assert\Optional
    {
        return new Assert\Optional(new Assert\Sequentially([new Assert\Type('string'), new Assert\Url(requireTld: true), new Assert\Length(max: 255)]));
    }

    private static function dateQuestion(): Assert\Optional
    {
        return new Assert\Optional(new Assert\Sequentially([new Assert\Type('string'), new Assert\Date()]));
    }

    private static function budgetQuestion(): Assert\Optional
    {
        return new Assert\Optional([new Assert\Choice(choices: self::QUOTE_BUDGETS)]);
    }

    /**
     * @param list<ServiceSpeciality> $specialities
     *
     * @return list<string|int>
     */
    private static function valuesOf(array $specialities): array
    {
        return array_map(static fn (ServiceSpeciality $speciality): string|int => $speciality->value, $specialities);
    }
}
