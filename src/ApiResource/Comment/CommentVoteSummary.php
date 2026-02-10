<?php

declare(strict_types=1);

namespace App\ApiResource\Comment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Comment\CommentVoteProcessor;

#[Post(
    uriTemplate: '/comments/{id}/vote',
    openapi: new Operation(tags: ['Comment']),
    normalizationContext: ['skip_null_values' => false],
    input: CommentVoteInput::class,
    name: 'api_comment_vote_post',
    processor: CommentVoteProcessor::class,
)]
class CommentVoteSummary
{
    #[ApiProperty(identifier: true, readable: false)]
    public int $id;
    public int $upvotes = 0;
    public int $downvotes = 0;
    public ?int $userVote = null;
}
