<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use App\Validator\Message\NotDeletedThreadRecipient;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input DTO for `POST /api/messages`. Splits the input contract from the
 * response so the IRI denormalization of `thread` resolves through
 * `MessageThreadResource` (DTO) instead of forcing the entity into
 * `MessageResource->thread`.
 */
#[NotDeletedThreadRecipient]
class MessageCreation
{
    #[Assert\NotNull]
    public MessageThreadResource $thread;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    public string $content;
}
