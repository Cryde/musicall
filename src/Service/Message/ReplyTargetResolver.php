<?php

declare(strict_types=1);

namespace App\Service\Message;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Repository\Message\MessageRepository;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The message a new one answers, from the `reply_to` a client sent, in the band chat (#1377) and in a
 * conversation (#1380).
 */
readonly class ReplyTargetResolver
{
    public const string INVALID_ERROR_CODE = 'music_all_e51d39ee-c6c8-407c-ac27-52a6db756708';

    public function __construct(
        private MessageRepository $messageRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * A live message of this thread, or a 422: an id from another conversation is refused the same
     * way as an unknown one, so it cannot be used to probe it.
     */
    public function resolve(?string $replyTo, MessageThread $thread): ?Message
    {
        // Empty is no reply: a multipart client may send every field of its form, filled or not.
        if ($replyTo === null || $replyTo === '') {
            return null;
        }

        $message = Uuid::isValid($replyTo) ? $this->messageRepository->findOneInThread($replyTo, $thread) : null;
        if ($message instanceof Message && !$message->isDeleted()) {
            return $message;
        }

        $messageKey = 'band_space.chat.reply_to_invalid';
        // Built by hand, so it skips the validator, which is what translates a constraint message.
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation(
                $this->translator->trans($messageKey, [], 'validators'),
                $messageKey,
                [],
                null,
                'replyTo',
                $replyTo,
                code: self::INVALID_ERROR_CODE,
            ),
        ]));
    }
}
