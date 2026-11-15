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
    case EMAIL_VERIFICATION_OTP = 'email_verification_otp';
    case BAND_SPACE_INVITATION = 'band_space_invitation';
    // The moderation emails an admin sees on the user page (#1126).
    case ACCOUNT_SUSPENDED_STATEMENT = 'account_suspended_statement';
    case SUSPENSION_LIFTED = 'suspension_lifted';

    /** @return list<self> */
    public static function moderation(): array
    {
        return [self::ACCOUNT_SUSPENDED_STATEMENT, self::SUSPENSION_LIFTED];
    }
}
