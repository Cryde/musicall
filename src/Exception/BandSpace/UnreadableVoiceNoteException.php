<?php declare(strict_types=1);

namespace App\Exception\BandSpace;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UnreadableVoiceNoteException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('band_space.chat.voice_note_unreadable', $previous);
    }
}
