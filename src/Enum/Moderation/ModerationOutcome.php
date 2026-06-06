<?php

declare(strict_types=1);

namespace App\Enum\Moderation;

/**
 * Outcome of a moderator's decision on a pending publication or gallery. Used only to discriminate
 * the moderation events ({@see \App\Event\PublicationModeratedEvent}, {@see \App\Event\GalleryModeratedEvent})
 * so a single event/listener pair handles both outcomes - it is never persisted.
 */
enum ModerationOutcome
{
    case Approved;
    case Rejected;
}
