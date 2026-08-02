<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceSettingsActivityType: string
{
    case BandCreated = 'band_created';
    case MemberRoleChanged = 'member_role_changed';
    case MemberRemoved = 'member_removed';
    case MemberLeft = 'member_left';
    case MemberProfileUpdated = 'member_profile_updated';
    case InvitationSent = 'invitation_sent';
    case InvitationAccepted = 'invitation_accepted';
    case InvitationDeclined = 'invitation_declined';
    case InvitationRevoked = 'invitation_revoked';
    case InvitationExpired = 'invitation_expired';
    case DeletionScheduled = 'deletion_scheduled';
    case DeletionCancelled = 'deletion_cancelled';
}
