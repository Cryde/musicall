<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Parameters;
use ApiPlatform\State\ParameterNotFound;
use App\Model\Search\ServiceSearch;

/** What every kind's search reads from its query, already validated by the operation's declarations (#1314, #1410). */
final readonly class ServiceSearchParameters
{
    private function __construct(private ?Parameters $parameters)
    {
    }

    public static function of(?Parameters $parameters): self
    {
        return new self($parameters);
    }

    public function value(string $key): mixed
    {
        $value = $this->parameters?->get($key)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }

    /** Where, how far, online too, and the page. */
    public function fill(ServiceSearch $search, int $limit): void
    {
        $search->limit = $limit;
        $latitude = $this->value('lat');
        $longitude = $this->value('lng');
        // A point needs both: one alone says nowhere.
        if (is_numeric($latitude) && is_numeric($longitude)) {
            $search->latitude = (float) $latitude;
            $search->longitude = (float) $longitude;
        }
        $radius = $this->value('radius');
        $search->radius = is_numeric($radius) ? (int) $radius : null;
        $search->online = $this->value('online') === '1';
        $page = $this->value('page');
        $search->page = is_numeric($page) ? (int) $page : 1;
    }
}
