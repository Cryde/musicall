<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Enum\Message\MessageReactionEmoji;

/**
 * The reaction row of a message, the same in the band chat and a direct conversation (#968, #1245).
 */
readonly class MessageReactionsBuilder
{
    /**
     * Driven by the enum rather than by the rows, which is what makes the order the declaration order
     * whatever the database returns, and what keeps an emoji nobody used out of the payload.
     *
     * @param array<string, array{count: int, hasReacted: bool}> $reactionTallies emoji slug => tally,
     *                                                                            from MessageReactionRepository::findAggregatedByMessageIds()
     *
     * @return list<array{key: string, emoji: string, count: int, has_reacted: bool}>
     */
    public function build(array $reactionTallies): array
    {
        $reactions = [];
        foreach (MessageReactionEmoji::cases() as $emoji) {
            $tally = $reactionTallies[$emoji->value] ?? null;
            if ($tally === null) {
                continue;
            }

            $reactions[] = [
                'key' => $emoji->value,
                'emoji' => $emoji->character(),
                'count' => $tally['count'],
                'has_reacted' => $tally['hasReacted'],
            ];
        }

        return $reactions;
    }
}
