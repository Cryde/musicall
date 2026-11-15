<?php declare(strict_types=1);

namespace App\ApiResource\Admin\User;

use DateTimeInterface;

/** A moderation email the account was sent (#1126): a statement of reasons, or the lift of a suspension. */
class AdminUserModerationEmail
{
    /** A UserEmailType value among UserEmailType::moderation(). */
    public string $type;

    public DateTimeInterface $sentDatetime;
}
