<?php declare(strict_types=1);

namespace App\Validator\Message;

use Symfony\Component\Validator\Constraint;

/**
 * Every Band Space object a chat message references must live in the space the message is going to.
 *
 * One message for a malformed identifier, for one that names nothing, and for one that names
 * something in another space: answering them differently would turn the endpoint into a way of
 * asking whether a given id exists somewhere else.
 */
#[\Attribute]
class ValidChatAttachments extends Constraint
{
    public string $message = 'message.attachment.not_found';

    public string $duplicateMessage = 'message.attachment.duplicate';
}
