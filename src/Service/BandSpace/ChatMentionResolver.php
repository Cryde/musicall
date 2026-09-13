<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\UserRepository;

/**
 * Who a chat message names (#964).
 *
 * A service rather than two calls inlined in the processor because `@tous` makes this a real question
 * with two answers, and because the sentinel then has exactly one definition instead of one per
 * caller. Everything else is delegated: the format is `MentionParserService`'s and the active-member
 * filter is the repository's.
 *
 * Only **active** members come back, from both branches. A mention of somebody who has left resolves
 * to nobody, which is concern 1 of #948 and the reason the task comment path uses the same repository
 * method. Note this is about who gets *notified*: the renderer still names a departed member, because
 * it reads the stored MessageMention rows rather than the roster.
 */
readonly class ChatMentionResolver
{
    /**
     * `@[tous]` rather than `@[everyone]`: the interface is French, and it sits in the id slot of the
     * existing format, where a username can never collide with it because ids there are uuids.
     */
    public const string EVERYONE_TOKEN = '@[tous]';

    public function __construct(
        private MentionParserService $mentionParserService,
        private UserRepository $userRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    /**
     * @return User[] the active members this content names, de-duplicated
     */
    public function resolve(BandSpace $bandSpace, string $content): array
    {
        if ($this->mentionsEveryone($content)) {
            return $this->activeMembersOf($bandSpace);
        }

        // Lower-cased before the lookup, as TaskCommentMentionRecorder does: the format takes a uuid in
        // either case, and leaning on the column's case-insensitive collation to paper over that is a
        // property of the schema rather than of this code.
        $mentionedIds = array_map(
            mb_strtolower(...),
            $this->mentionParserService->extractMentions($content),
        );
        if ($mentionedIds === []) {
            return [];
        }

        return $this->userRepository->findActiveBandSpaceMembersByIds($bandSpace, $mentionedIds);
    }

    /**
     * Case-insensitive to match the uuid half of the format, which the parser matches with `/i`, so
     * `@[TOUS]` cannot be a silent no-op for somebody typing in caps.
     */
    public function mentionsEveryone(string $content): bool
    {
        return mb_stripos($content, self::EVERYONE_TOKEN) !== false;
    }

    /**
     * @return User[]
     */
    private function activeMembersOf(BandSpace $bandSpace): array
    {
        $members = [];
        foreach ($this->bandSpaceMembershipRepository->findByBandSpace($bandSpace) as $membership) {
            $members[(string) $membership->user->id] = $membership->user;
        }

        return array_values($members);
    }
}
