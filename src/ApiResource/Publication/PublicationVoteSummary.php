<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Publication\PublicationVoteProcessor;

#[Post(
    uriTemplate: '/publications/{slug}/vote',
    openapi: new Operation(tags: ['Publications']),
    normalizationContext: ['skip_null_values' => false],
    input: PublicationVoteInput::class,
    name: 'api_publication_vote_post',
    processor: PublicationVoteProcessor::class,
)]
class PublicationVoteSummary
{
    #[ApiProperty(identifier: true, readable: false)]
    public string $slug;
    public int $upvotes = 0;
    public int $downvotes = 0;
    public ?int $userVote = null;
}
