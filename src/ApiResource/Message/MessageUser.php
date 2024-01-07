<?php

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Processor\Message\MessagePostToUserProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/messages/user',
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
    private User $recipient;
    #[Assert\NotBlank]
    #[Groups([MessageUser::POST])]
    private string $content;

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function setRecipient(User $recipient): MessageUser
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): MessageUser
    {
        $this->content = $content;

        return $this;
    }
}