<?php

declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\User;
use App\Service\User\Relation\UserContactPolicy;
use App\Service\User\UserNotificationPreferenceChecker;

/**
 * Whether two people in a direct conversation see each other's « Vu » (#1243). Symmetric on purpose:
 * somebody who hides that they read gives up seeing that the other did.
 */
readonly class ReadReceiptPolicy
{
    public function __construct(
        private UserNotificationPreferenceChecker $preferenceChecker,
        private UserContactPolicy $contactPolicy,
    ) {
    }

    public function appliesBetween(User $first, User $second): bool
    {
        return !$first->isDeleted()
            && !$second->isDeleted()
            && $this->preferenceChecker->showsReadReceipts($first)
            && $this->preferenceChecker->showsReadReceipts($second)
            && $this->contactPolicy->canContact($first, $second);
    }
}
