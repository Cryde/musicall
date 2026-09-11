<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Message\MessageThreadMetaPatchProcessor;
use App\State\Provider\Message\MessageThreadMetaCollectionProvider;
use App\State\Provider\Message\MessageThreadMetaItemProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MessageThreadMeta',
    requirements: ['id' => Requirement::UUID],
    operations: [
        new GetCollection(
            uriTemplate: '/message_thread_metas',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMetaResource::LIST]],
            name: 'api_message_thread_meta_get_collection',
            provider: MessageThreadMetaCollectionProvider::class,
        ),
        new Patch(
            uriTemplate: '/message_thread_metas/{id}',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMetaResource::ITEM]],
            denormalizationContext: ['groups' => [MessageThreadMetaResource::PATCH]],
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_message_thread_meta_patch',
            provider: MessageThreadMetaItemProvider::class,
            processor: MessageThreadMetaPatchProcessor::class,
        ),
    ],
)]
class MessageThreadMetaResource
{
    public const string LIST = 'message_thread_meta:list';
    public const string ITEM = 'message_thread_meta:item';
    public const string PATCH = 'message_thread_meta:patch';

    #[ApiProperty(identifier: true)]
    #[Groups([MessageThreadMetaResource::LIST, MessageThreadMetaResource::ITEM])]
    public string $id;

    /** How many messages in this thread the user has not read. Read only. */
    #[Groups([MessageThreadMetaResource::LIST, MessageThreadMetaResource::ITEM])]
    public int $unreadCount;

    /**
     * Write only, and a command rather than a state: true marks the thread read as of now, false puts
     * the read position back to nothing. It is the boolean that used to be stored (#954), kept on the
     * input side because "mark this read" is what a client actually wants to say, and because the
     * alternative is making every caller compute a position the server already knows.
     *
     * Nullable with a NotNull rather than a bare `bool`, and that is load bearing. Nothing populates
     * it on the read side any more, so a merge-patch body that omits the key would leave a typed
     * property uninitialised, and reading it raises an `Error` rather than an exception: a 500 where
     * this used to be a harmless no-op. The constraint turns the same request into a 422 naming the
     * field, decided before the processor runs. A default of `false` would be worse than either,
     * silently running the destructive half of the command.
     */
    #[Assert\NotNull(message: 'Veuillez indiquer si le fil est lu')]
    #[Groups([MessageThreadMetaResource::PATCH])]
    public ?bool $isRead = null;

    #[Groups([MessageThreadMetaResource::LIST])]
    public MessageThreadResource $thread;
}
