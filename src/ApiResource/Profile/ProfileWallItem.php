<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Publication\PublicationListItem;
use App\ApiResource\User\Profile\PublicProfileAnnounce;
use App\Entity\User;
use App\Service\Profile\Wall\WallCursor;
use App\Service\Profile\Wall\WallFilter;
use App\State\Provider\Profile\ProfileWallProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One line of a wall (#1316): a post, or what its owner did elsewhere. Exactly one payload is set, the one `type`
 * names. A page holds PAGE_SIZE lines newest first, the pinned post aside; `before` is the last line's `cursor`,
 * and a shorter page is the last one. `totalItems` counts the page, not the wall.
 */
#[ApiResource(
    shortName: 'ProfileWallItem',
    operations: [
        new GetCollection(
            uriTemplate: '/user/profile/{username}/wall',
            uriVariables: [
                'username' => new Link(fromClass: User::class, identifiers: ['username']),
            ],
            requirements: ['username' => '[^/]+'],
            openapi: new Operation(tags: ['Profile Wall']),
            paginationEnabled: false,
            name: 'api_profile_wall_list',
            provider: ProfileWallProvider::class,
            parameters: [
                'before' => new QueryParameter(key: 'before', constraints: [new Assert\Sequentially([
                    new Assert\Type(type: 'string', message: 'profile.wall.cursor_invalid'),
                    new Assert\Regex(pattern: WallCursor::PATTERN, message: 'profile.wall.cursor_invalid'),
                ])]),
                // « Posts », « Médias », « Annonces » or « Cours » (#1317); without it, everything. Out of the Hydra
                // search template, which would read it as a filter on the lines' own `type`.
                'type' => new QueryParameter(key: 'type', hydra: false, constraints: [new Assert\Sequentially([
                    new Assert\Type(type: 'string', message: 'profile.wall.filter_invalid'),
                    new Assert\Choice(callback: [WallFilter::class, 'values'], message: 'profile.wall.filter_invalid'),
                ])]),
            ],
        ),
    ],
)]
class ProfileWallItem
{
    public const int PAGE_SIZE = 10;

    public const string TYPE_POST = 'post';
    public const string TYPE_ANNOUNCE = 'announce';
    public const string TYPE_PUBLICATION = 'publication';
    public const string TYPE_COURSE = 'course';
    public const string TYPE_MEDIA = 'media';

    /** This line's place in the wall: the next page starts after it. */
    #[ApiProperty(identifier: true)]
    public string $cursor;

    public string $type;

    public \DateTimeInterface $date;

    #[ApiProperty(genId: false)]
    public ?ProfilePostResource $post = null;

    #[ApiProperty(genId: false)]
    public ?PublicProfileAnnounce $announce = null;

    #[ApiProperty(genId: false)]
    public ?PublicationListItem $publication = null;

    #[ApiProperty(genId: false)]
    public ?ProfileWallMedia $media = null;
}
