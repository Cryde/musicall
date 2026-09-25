<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\BandSpaceChatReadEvent;
use App\Mercure\MercureTopic;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the other members of a space that somebody read its chat, so « Vu par » updates live (#977).
 *
 * Same shape as MessagePostedListener: a tag on each recipient's private topic, one publish, and a
 * hub failure logged rather than thrown. Recipients are the space's active memberships, which is
 * what ThreadMemberResolver::activeMembersOf() answers for a channel, so a former member or somebody
 * from another space never hears it.
 */
#[AsEventListener]
readonly class BandSpaceChatReadListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    public function __invoke(BandSpaceChatReadEvent $event): void
    {
        $readerId = (string) $event->reader->id;
        $topics = [];
        foreach ($this->bandSpaceMembershipRepository->findByBandSpace($event->bandSpace) as $membership) {
            $memberId = (string) $membership->user->id;
            // Not the reader: their own read changes nothing they need to refetch, and a tab that
            // refetched on its own read would be one step away from feeding itself.
            if ($memberId !== $readerId) {
                $topics[] = MercureTopic::userNotifications($memberId);
            }
        }

        if ($topics === []) {
            return;
        }

        try {
            // A tag, with no reader and no position: the browser re-reads the page through the API.
            $this->hub->publish(new Update(
                $topics,
                json_encode(
                    ['type' => 'band_space_chat_read', 'band_space_id' => (string) $event->bandSpace->id],
                    JSON_THROW_ON_ERROR,
                ),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not publish a chat read signal, « Vu par » will update on the next load', [
                'exception' => $throwable,
                'band_space_id' => (string) $event->bandSpace->id,
            ]);
        }
    }
}
