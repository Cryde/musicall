<?php

declare(strict_types=1);

namespace App\Service\Message;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A stretch of a thread anchored on one message (#1039), the band chat's window and a conversation's
 * (#1380): which message it is anchored on, and how far it reads on each side.
 *
 * Exactly one of `around`, `before` and `after`:
 * - `around`: the anchor with up to 25 messages on each side, the landing window
 * - `before`: up to 50 messages older than the anchor
 * - `after`: up to 50 messages newer than the anchor
 */
final class MessageWindowReader
{
    public const int AROUND_EACH_SIDE = 25;

    public const int PAGE_SIZE = 50;

    private const array DIRECTIONS = ['around', 'before', 'after'];

    /**
     * The direction asked for and the anchor's id, already a uuid: the operation's Assert\Uuid has
     * refused anything else with a 422.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{0: string, 1: string}
     */
    public static function anchorOf(array $filters, string $refusalMessage): array
    {
        $given = array_filter(
            array_intersect_key($filters, array_flip(self::DIRECTIONS)),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );
        if (count($given) !== 1) {
            throw new BadRequestHttpException($refusalMessage);
        }
        $direction = (string) array_key_first($given);

        return [$direction, (string) $given[$direction]];
    }

    /**
     * One more row than asked for on each side that is read, which is how each side learns whether
     * the thread goes on past the window without a count of its own. A side that is not read answers
     * true, and truthfully: `before` and `after` never include the anchor, so the anchor itself is
     * there on the side they skip.
     *
     * @template T
     *
     * @param \Closure(int $limit, bool $inclusive): array<int, T> $older newest first
     * @param \Closure(int $limit): array<int, T>                  $newer oldest first
     *
     * @return array{messages: list<T>, hasOlder: bool, hasNewer: bool} oldest first
     */
    public static function read(string $direction, \Closure $older, \Closure $newer): array
    {
        [$olderCount, $newerCount, $includeAnchor] = match ($direction) {
            'before' => [self::PAGE_SIZE, 0, false],
            'after' => [0, self::PAGE_SIZE, false],
            default => [self::AROUND_EACH_SIDE, self::AROUND_EACH_SIDE, true],
        };

        $olderRows = [];
        $hasOlder = true;
        if ($olderCount > 0) {
            $wanted = $olderCount + ($includeAnchor ? 1 : 0);
            $olderRows = $older($wanted + 1, $includeAnchor);
            $hasOlder = count($olderRows) > $wanted;
            $olderRows = array_reverse(array_slice($olderRows, 0, $wanted));
        }

        $newerRows = [];
        $hasNewer = true;
        if ($newerCount > 0) {
            $newerRows = $newer($newerCount + 1);
            $hasNewer = count($newerRows) > $newerCount;
            $newerRows = array_slice($newerRows, 0, $newerCount);
        }

        return [
            'messages' => [...$olderRows, ...$newerRows],
            'hasOlder' => $hasOlder,
            'hasNewer' => $hasNewer,
        ];
    }
}
