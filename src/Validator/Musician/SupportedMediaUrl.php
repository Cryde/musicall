<?php

declare(strict_types=1);

namespace App\Validator\Musician;

use App\Enum\Musician\MediaPlatform;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SupportedMediaUrl extends Constraint
{
    /** Names the platforms taken: all but the last in `{{ platforms }}`, the last in `{{ last }}`. */
    public string $message = 'profile.media.url_not_supported';

    /** @var list<MediaPlatform> two at least, as the message lists them */
    public array $platforms;

    /** @param list<MediaPlatform> $platforms what the link may embed */
    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
        array $platforms = MediaPlatform::READ_BY_APP,
    ) {
        $this->message = $message ?? $this->message;
        $this->platforms = $platforms;
        parent::__construct(null, $groups, $payload);
    }
}
