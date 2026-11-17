<?php

declare(strict_types=1);

namespace App\ApiResource\Service\View;

/** One half of « Avant, après » (#1410). The sound itself is at `api_service_audio_get`, by service id and side. */
class ServiceAudioView
{
    /** Changes with every upload: the page keys its player on it, and asks the sound by it so no cached copy plays. */
    public string $audioId;

    /** A BeforeAfterSide value. */
    public string $side;

    public ?string $title = null;

    public ?string $credit = null;

    public int $durationSeconds;

    /** @var list<int> 48 loudness values, 0 to 255 */
    public array $peaks = [];
}
