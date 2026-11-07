<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Profile\ProfileMusicianViewProcessor;

/**
 * A visit to a member's « Médias » (#1317), counted as the musician page's visits were before it folded into the
 * profile. The page sends it when that view opens; the server keeps the old rules (not the owner, once a period).
 */
#[ApiResource(
    shortName: 'ProfileMusicianView',
    operations: [
        new Post(
            uriTemplate: '/user/profile/{username}/musician/views',
            uriVariables: ['username' => new Link(fromClass: self::class, identifiers: ['username'])],
            requirements: ['username' => '[^/]+'],
            status: 204,
            openapi: new Operation(tags: ['Profile']),
            input: false,
            output: false,
            read: false,
            name: 'api_profile_musician_view_post',
            processor: ProfileMusicianViewProcessor::class,
        ),
    ],
)]
class ProfileMusicianView
{
    #[ApiProperty(identifier: true)]
    public string $username;
}
