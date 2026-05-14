<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Forum\ForumPost;
use App\State\Processor\Forum\ForumPostEditProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/forum/posts/{id}/edit',
    uriVariables: ['id'],
    openapi: new Operation(tags: ['Forum']),
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    output: ForumPostResource::class,
    name: 'api_forum_post_edit',
    processor: ForumPostEditProcessor::class,
)]
class ForumPostEdit
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[Assert\NotBlank]
    #[Assert\Length(min: ForumPost::MIN_MESSAGE_LENGTH)]
    public string $content;
}
