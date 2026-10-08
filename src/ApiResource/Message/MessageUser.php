<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\User;
use App\State\Processor\Message\MessagePostToUserProcessor;
use App\Validator\Message\NotDeletedRecipient;
use App\Validator\Message\NotSelfRecipient;
use App\Validator\Message\ValidContactOrigin;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/messages/user',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageResource::ITEM]],
            denormalizationContext: ['groups' => [MessageUser::POST]],
            output: MessageResource::class,
            name: 'api_message_post_to_user',
            processor: MessagePostToUserProcessor::class
        ),
    ]
)]
#[NotSelfRecipient]
#[NotDeletedRecipient]
#[ValidContactOrigin]
class MessageUser
{
    const POST = 'MESSAGE_USER_POST';
    #[Assert\NotBlank]
    #[Groups([MessageUser::POST])]
    public User $recipient;
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[Groups([MessageUser::POST])]
    public string $content;

    /** The recipient's announce the message is sent from, if any (#998). */
    #[Groups([MessageUser::POST])]
    public ?string $musicianAnnounceId = null;

    /** Sent from the recipient's teacher profile (#998). A user has at most one, so no id is needed. */
    #[Groups([MessageUser::POST])]
    public bool $fromTeacherProfile = false;
}
