<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Event\BandSpaceChatPresenceChangedEvent;
use App\Service\Message\ThreadMemberResolver;
use App\Service\User\UserNotificationPreferenceChecker;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Who has the band's chat open right now (#1040). Ephemeral by design: one cache entry per member,
 * gone on its own once the heartbeats stop, and never a row in MariaDB.
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
     * must not make them flicker offline to the band while the other is still open.
     *
     * @return list<string> the other online members' user ids
     */
    public function beat(BandSpace $bandSpace, MessageThread $channel, User $member, string $tab): array
    {
        $item = $this->cache->getItem($this->key($bandSpace, $member));
        $tabs = $this->liveTabs($item->isHit() ? $item->get() : null);
        $wasHere = $tabs !== [];

        if ($this->preferenceChecker->showsOnlinePresence($member)) {
            $tabs[$tab] = time() + self::TTL_SECONDS;
            $this->cache->save($item->set($tabs)->expiresAfter(self::TTL_SECONDS));
            if (!$wasHere) {
                $this->changed($bandSpace, $channel, $member);
            }
        } elseif ($wasHere) {
            $this->cache->deleteItem($this->key($bandSpace, $member));
            $this->changed($bandSpace, $channel, $member);
        }

        return $this->othersOnline($bandSpace, $channel, $member);
    }

    /** Best effort, sent as the tab goes away; the TTL catches the tabs that never say it. */
    public function leave(BandSpace $bandSpace, MessageThread $channel, User $member, string $tab): void
    {
        $key = $this->key($bandSpace, $member);
        $item = $this->cache->getItem($key);
        $tabs = $this->liveTabs($item->isHit() ? $item->get() : null);
        if (!isset($tabs[$tab])) {
            return;
        }

        unset($tabs[$tab]);
        if ($tabs !== []) {
            // Still here in another tab: nothing changed for the band.
            $this->cache->save($item->set($tabs)->expiresAfter(self::TTL_SECONDS));

            return;
        }

        $this->cache->deleteItem($key);
        $this->changed($bandSpace, $channel, $member);
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
    private function othersOnline(BandSpace $bandSpace, MessageThread $channel, User $member): array
    {
        $others = array_values(array_filter(
            $this->threadMemberResolver->activeMembersOf($channel),
            fn (User $other): bool => (string) $other->id !== (string) $member->id
                && !$other->isDeleted()
                && $this->preferenceChecker->showsOnlinePresence($other),
        ));
        if ($others === []) {
            return [];
        }

        $byKey = [];
        foreach ($others as $other) {
            $byKey[$this->key($bandSpace, $other)] = (string) $other->id;
        }

        $online = [];
        foreach ($this->cache->getItems(array_keys($byKey)) as $key => $item) {
            if ($item->isHit() && $this->liveTabs($item->get()) !== []) {
                $online[] = $byKey[$key];
            }
        }

        return $online;
    }

    private function changed(BandSpace $bandSpace, MessageThread $channel, User $member): void
    {
        $this->eventDispatcher->dispatch(new BandSpaceChatPresenceChangedEvent($bandSpace, $channel, $member));
    }

    private function key(BandSpace $bandSpace, User $member): string
    {
        return 'chat_presence_' . $bandSpace->id . '_' . $member->id;
    }
}
