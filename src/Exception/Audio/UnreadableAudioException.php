<?php

declare(strict_types=1);

namespace App\Exception\Audio;

/** ffmpeg could not read the upload, or it holds no sound: each caller turns it into its own 422. */
class UnreadableAudioException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('The sound could not be read', 0, $previous);
    }
}
