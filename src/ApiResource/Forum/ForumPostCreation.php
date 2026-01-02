<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\State\Processor\Forum\ForumPostCreationProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/forum/posts',
    openapi: new Operation(tags: ['Forum']),
    normalizationContext: ['groups' => [ForumPost::ITEM], 'skip_null_values' => false],
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    output: ForumPost::class,
    name: 'api_forum_posts_post',
    processor: ForumPostCreationProcessor::class,
)]
class ForumPostCreation
{
    #[Assert\NotNull]
    public ForumTopic $topic;

    #[Assert\NotBlank]
    #[Assert\Length(min: ForumPost::MIN_MESSAGE_LENGTH)]
    public string $content;
}
