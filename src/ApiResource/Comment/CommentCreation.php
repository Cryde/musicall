<?php declare(strict_types=1);

namespace App\ApiResource\Comment;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Comment\CommentThread;
use App\State\Processor\Comment\PostCommentProcessor;
use App\Validator\Comment\ValidReplyParent;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/comments',
    openapi: new Operation(tags: ['Comment']),
    normalizationContext: ['skip_null_values' => false],
    output: CommentResource::class,
    name: 'api_comments_post_collection',
    processor: PostCommentProcessor::class,
)]
#[ValidReplyParent]
class CommentCreation
{
    #[Assert\NotNull]
    public CommentThread $thread;
    #[Assert\NotBlank(message: 'Le commentaire est vide')]
    public string $content;

    /**
     * Optional parent comment id for single-level threading. Must reference a root comment
     * (one without its own parent) in the same thread.
     */
    public ?int $parentId = null;
}
