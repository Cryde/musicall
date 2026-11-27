<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;

/**
 * What every card of a search by speciality shows (#1410, #1411, #1412): every kind but teaching. Each kind's result
 * adds its details and declares its search.
 */
abstract class SpecialitySearchResult
{
    public const int PAGE_SIZE = 12;
    protected const int MAX_PAGE = 1000;

    #[ApiProperty(identifier: true)]
    public string $username;

    /** What the site calls the member: their display name, or their username. */
    public string $name;

    public ?string $profilePictureUrl = null;

    public ?string $headline = null;

    /**
     * The kind's speciality values, the searched one first.
     *
     * @var list<string>
     */
    public array $specialities = [];

    /** In km to the nearest place in reach; null without a point, or for a member found remotely only. */
    public ?float $distance = null;

    /** The offer the card leads with, in cents (Service::leadOffer()): « dès 80 € le titre ». */
    public ?int $price = null;

    /** The OfferUnit value of that price. */
    public ?string $priceUnit = null;

    /** @var array<TeacherProfileStyle> */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** The first media, which plays without leaving the list. */
    #[ApiProperty(genId: false)]
    public ?TeacherSearchMedia $media = null;
}
