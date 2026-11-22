<?php

declare(strict_types=1);

namespace App\Service\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceWork;
use App\Entity\Service\ServiceWorkImage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** A list's new order, sent as its ids: a member's services (#1415), a service's works or a work's images (#1407). */
final class PositionOrder
{
    /**
     * Every id, each once: an order missing one, or naming one twice, would leave positions nobody chose.
     *
     * @param array<Service|ServiceWork|ServiceWorkImage> $items
     * @param list<string>                                $ids
     */
    public static function apply(array $items, array $ids): void
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[(string) $item->id] = $item;
        }
        $sorted = $ids;
        sort($sorted);
        $known = array_map(strval(...), array_keys($byId));
        sort($known);
        if ($sorted !== $known) {
            throw new UnprocessableEntityHttpException('service.order_invalid');
        }
        foreach ($ids as $position => $id) {
            $byId[$id]->position = $position;
        }
    }
}
