<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Moderation\ModerationAction;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/** A moderator suspended an account; the action carries the reason, the basis and the report, if any. */
class AccountSuspendedEvent extends Event
{
    public function __construct(
        public readonly User $account,
        public readonly ModerationAction $action,
    ) {
    }
}
