<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceFileActivityType: string
{
    case Uploaded = 'uploaded';
    case Archived = 'archived';
    case Restored = 'restored';
    /** Destroyed on demand from the trash, rather than by app:band-space:purge on its schedule. */
    case Purged = 'purged';
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
    /**
     * The file was detached because the task, note or finance entry it hung on was deleted, not because
     * a member detached it. Distinct from Detached so the feed can say why the link disappeared instead
     * of crediting the deleter with an action they never took.
     */
    case SourceDeleted = 'source_deleted';
}
