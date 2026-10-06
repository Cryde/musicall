<?php declare(strict_types=1);

namespace App\Enum\Report;

/** How a moderator closed a report. Removing the content itself is #1122. */
enum ReportOutcome: string
{
    case Dismissed = 'dismissed';
    case AccountSuspended = 'account_suspended';
}
