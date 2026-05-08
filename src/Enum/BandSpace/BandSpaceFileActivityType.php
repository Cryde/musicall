<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceFileActivityType: string
{
    case Uploaded = 'uploaded';
    case Archived = 'archived';
    case Restored = 'restored';
    case Renamed = 'renamed';
    case Moved = 'moved';
    case Tagged = 'tagged';
    case Untagged = 'untagged';
    case VersionAdded = 'version_added';
    case RolledBack = 'rolled_back';
    case Shared = 'shared';
    case ShareRevoked = 'share_revoked';
    case PublicAccessed = 'public_accessed';
    case Attached = 'attached';
    case Detached = 'detached';
}
