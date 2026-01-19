<?php

declare(strict_types=1);

namespace App\Enum\User;

enum UserEmailType: string
{
    case PROFILE_COMPLETENESS = 'profile_completeness';
    case ANNOUNCE_RENEWAL_REMINDER = 'announce_renewal_reminder';
    case WELCOME = 'welcome';
    case INACTIVITY_REMINDER = 'inactivity_reminder';
    case EMAIL_CONFIRMATION_REMINDER = 'email_confirmation_reminder';
}
