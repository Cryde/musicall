<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Event\ChatPresenceChangedEvent;
use App\Service\User\UserNotificationPreferenceChecker;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Who has a conversation open right now: a band's chat (#1040) or a direct one (#1246). Ephemeral by
 * design: one cache entry per member and thread, gone on its own once the heartbeats stop, and never a
 * row in MariaDB.
 *
 * The app cache pool, so Valkey's database 0 behind a key prefix, never database 1 where the sessions
 * are. Losing it (a `cache:pool:clear`, a FLUSHDB) only blanks presence until the next heartbeats.
 */
readonly class ChatPresenceTracker
{
    /**
     * Online for this long after a heartbeat. The page beats every 30 seconds, so one late or lost
     * beat does not make anybody flicker out.
     */
    public const int TTL_SECONDS = 75;

    public function __construct(
        private CacheItemPoolInterface $cache,
        private ThreadMemberResolver $threadMemberResolver,
        private UserNotificationPreferenceChecker $preferenceChecker,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Marks this tab of the member here and answers with who else is. A member who opted out is never
     * marked, and leaves at once if they just turned it off.
     *
     * One entry per member holds their open tabs, each with its own expiry: closing one of two tabs
     * must not make them flicker offline to the others while the other is still open.
     *
     * @return list<string> the other online members' user ids
     */
    public function beat(MessageThread $thread, User $member, string $tab): array
    {
        $item = $this->cache->getItem($this->key($thread, $member));
        $tabs = $this->liveTabs($item->isHit() ? $item->get() : null);
        $wasHere = $tabs !== [];

        if ($this->preferenceChecker->showsOnlinePresence($member)) {
            $tabs[$tab] = time() + self::TTL_SECONDS;
            $this->cache->save($item->set($tabs)->expiresAfter(self::TTL_SECONDS));
            if (!$wasHere) {
                $this->changed($thread, $member);
            }
        } elseif ($wasHere) {
            $this->cache->deleteItem($this->key($thread, $member));
            $this->changed($thread, $member);
        }

        return $this->othersOnline($thread, $member);
    }

    /** Best effort, sent as the tab goes away; the TTL catches the tabs that never say it. */
    public function leave(MessageThread $thread, User $member, string $tab): void
    {
        $key = $this->key($thread, $member);
        $item = $this->cache->getItem($key);
        $tabs = $this->liveTabs($item->isHit() ? $item->get() : null);
        if (!isset($tabs[$tab])) {
            return;
        }

        unset($tabs[$tab]);
        if ($tabs !== []) {
            // Still here in another tab: nothing changed for the others.
            $this->cache->save($item->set($tabs)->expiresAfter(self::TTL_SECONDS));

            return;
        }

        $this->cache->deleteItem($key);
        $this->changed($thread, $member);
    }

    /**
     * The tabs whose last beat is recent enough, from whatever the entry holds.
     *
     * @return array<string, int> tab id to the second it stops counting
     */
    private function liveTabs(mixed $stored): array
    {
        if (!is_array($stored)) {
            return [];
        }
        $now = time();

        return array_filter(
            $stored,
            static fn (mixed $until, mixed $tab): bool => is_string($tab) && is_int($until) && $until > $now,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The roster at this moment, so somebody who left or was kicked is gone from the next answer. The
     * preference is checked again here too, so a member who opted out is absent even before their
     * entry expires.
     *
     * @return list<string>
     */
    private function othersOnline(MessageThread $thread, User $member): array
    {
        $others = array_values(array_filter(
            $this->threadMemberResolver->activeMembersOf($thread),
            fn (User $other): bool => (string) $other->id !== (string) $member->id
                && !$other->isDeleted()
                && $this->preferenceChecker->showsOnlinePresence($other),
        ));
        if ($others === []) {
            return [];
        }

        $byKey = [];
        foreach ($others as $other) {
            $byKey[$this->key($thread, $other)] = (string) $other->id;
        }

        $online = [];
        foreach ($this->cache->getItems(array_keys($byKey)) as $key => $item) {
            if ($item->isHit() && $this->liveTabs($item->get()) !== []) {
                $online[] = $byKey[$key];
            }
        }

        return $online;
    }

    private function changed(MessageThread $thread, User $member): void
    {
        $this->eventDispatcher->dispatch(new ChatPresenceChangedEvent($thread, $member));
    }

    private function key(MessageThread $thread, User $member): string
    {
        return 'chat_presence_' . $thread->id . '_' . $member->id;
    }
}
