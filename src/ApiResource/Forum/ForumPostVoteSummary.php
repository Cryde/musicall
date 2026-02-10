<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Forum\ForumPostVoteProcessor;

#[Post(
    uriTemplate: '/forums/posts/{id}/vote',
    openapi: new Operation(tags: ['Forum']),
    normalizationContext: ['skip_null_values' => false],
    input: ForumPostVoteInput::class,
    name: 'api_forum_post_vote_post',
    processor: ForumPostVoteProcessor::class,
)]
class ForumPostVoteSummary
{
    #[ApiProperty(identifier: true, readable: false)]
    public string $id;
    public int $upvotes = 0;
    public int $downvotes = 0;
    public ?int $userVote = null;
}
