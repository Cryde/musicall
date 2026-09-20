<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\State\Processor\BandSpace\Chat\ChatMessageTaskCreateProcessor;

/**
 * « Faut penser à ramener le câble XLR » becomes a task, and the task keeps a way back to where it
 * was said (#979).
 *
 * A command with nothing to send, like the pin endpoints: the title and the description are worked
 * out from the stored message, server side, because that is the only place the plain text exists.
 * `content` in the read payload is sanitized HTML with its mentions already turned into spans, and
 * `editable_content`, which is the stored shape, only ever reaches the author while anybody may turn
 * a message into a task. A client posting text it had scraped out of a bubble could also post text
 * the message never contained.
 *
 * It answers with the task rather than with the message, because the task is what was created and
 * what the member is then sent to. The card the message gains is the same attachment row the picker
 * writes (#970), so the chat needs no new shape to render it.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}/task',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: ChatMessageResource::class, identifiers: ['id']),
            ],
            status: 201,
            openapi: new Operation(tags: ['Band Space Chat']),
            normalizationContext: ['skip_null_values' => false],
            security: "is_granted('ROLE_USER')",
            input: false,
            output: TaskResource::class,
            read: false,
            name: 'api_band_space_chat_messages_task_post',
            processor: ChatMessageTaskCreateProcessor::class,
        ),
    ],
)]
class ChatMessageTaskCreate
{
}
