<?php declare(strict_types=1);

namespace App\ApiResource\Comment;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Comment\Comment;
use App\Entity\Comment\CommentThread;
use App\State\Processor\Comment\PostCommentProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/comments',
    openapi: new Operation(tags: ['Comment']),
    normalizationContext: ['groups' => [Comment::ITEM]],
    output: Comment::class,
    name: 'api_comments_post_collection',
    processor: PostCommentProcessor::class,
)]
class CommentCreation
{
    #[Assert\NotNull]
    public CommentThread $thread;
    #[Assert\NotBlank(message: 'Le commentaire est vide')]
    public string $content;
}
