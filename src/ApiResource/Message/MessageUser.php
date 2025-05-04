<?php

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Message\Message;
use App\Entity\User;
use App\State\Processor\Message\MessagePostToUserProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/messages/user',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [Message::ITEM]],
            denormalizationContext: ['groups' => [MessageUser::POST]],
            output: Message::class,
            name: 'api_message_post_to_user',
            processor: MessagePostToUserProcessor::class
        ),
    ]
)]
class MessageUser
{
    const POST = 'MESSAGE_USER_POST';
    #[Assert\NotBlank]
    #[Groups([MessageUser::POST])]
    public User $recipient;
    #[Assert\NotBlank]
    #[Groups([MessageUser::POST])]
    public string $content;
}