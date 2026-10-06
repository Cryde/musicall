<?php declare(strict_types=1);

namespace App\Enum\Moderation;

enum ModerationActionType: string
{
    case ReportDismissed = 'report_dismissed';
    case AccountSuspended = 'account_suspended';
    case SuspensionLifted = 'suspension_lifted';
}
