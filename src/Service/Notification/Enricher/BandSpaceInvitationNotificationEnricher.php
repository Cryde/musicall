<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceInvitationRepository;

/**
 * Resolves the *live* status of each band space invitation referenced by an
 * invitation notification, so the frontend drops stale Accepter/Décliner once the
 * invite is accepted/declined/cancelled/expired. Batched: one `token IN (...)`
 * query for the whole page.
 */
readonly class BandSpaceInvitationNotificationEnricher implements NotificationEnricherInterface
{
    public function __construct(private BandSpaceInvitationRepository $bandSpaceInvitationRepository)
    {
    }

    public function getType(): NotificationType
    {
        return NotificationType::BandSpaceInvitation;
    }

    public function enrich(array $notifications): void
    {
        $tokens = [];
        foreach ($notifications as $notification) {
            $token = $notification->payload['invitation_token'] ?? null;
            if (is_string($token)) {
                $tokens[] = $token;
            }
        }

        if ($tokens === []) {
            return;
        }

        $invitationsByToken = [];
        foreach ($this->bandSpaceInvitationRepository->findByTokens($tokens) as $invitation) {
            $invitationsByToken[$invitation->token] = $invitation;
        }

        foreach ($notifications as $notification) {
            $token = $notification->payload['invitation_token'] ?? null;
            $invitation = is_string($token) ? ($invitationsByToken[$token] ?? null) : null;
            $notification->payload['invitation_status'] = $this->resolveStatus($invitation);
        }
    }

    private function resolveStatus(?BandSpaceInvitation $invitation): string
    {
        // Unknown/deleted token -> not actionable.
        if (!$invitation instanceof BandSpaceInvitation) {
            return InvitationStatus::Expired->value;
        }

        // Time-expired invites may still carry status Pending until the prune/expire
        // command runs; treat them as expired so the action disappears immediately.
        if ($invitation->status === InvitationStatus::Pending && $invitation->isExpired()) {
            return InvitationStatus::Expired->value;
        }

        return $invitation->status->value;
    }
}
