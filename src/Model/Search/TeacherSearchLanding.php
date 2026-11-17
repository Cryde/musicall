<?php

declare(strict_types=1);

namespace App\Model\Search;

/**
 * The part of a teacher search address that names a page a search engine may index (#1314): « Prof de basse à
 * Bruxelles ». Only well formed values count, so a crafted address puts neither its own words in a title nor its own
 * query in a canonical URL.
 */
final readonly class TeacherSearchLanding
{
    private const string SLUG = '/^[a-z0-9-]{1,64}\z/';
    private const string CITY = "/^\\p{L}[\\p{L}\\p{M}' .-]{0,79}\\z/u";
    private const string COORDINATE = '/^-?\d{1,3}(\.\d{1,8})?\z/';

    private function __construct(
        public ?string $instrumentSlug,
        public ?string $city,
        private ?string $latitude,
        private ?string $longitude,
    ) {
    }

    /** @param array<mixed> $query */
    public static function fromQuery(array $query): self
    {
        $instrument = self::matching($query['instrument'] ?? null, self::SLUG);
        $latitude = self::matching($query['lat'] ?? null, self::COORDINATE);
        $longitude = self::matching($query['lng'] ?? null, self::COORDINATE);
        // A city only names the page with the point the search goes by.
        $city = $latitude !== null && $longitude !== null ? self::matching($query['location'] ?? null, self::CITY) : null;

        return $city === null
            ? new self($instrument, null, null, null)
            : new self($instrument, $city, $latitude, $longitude);
    }

    /** @return array<string, string> the query of its canonical address, in a fixed order */
    public function query(): array
    {
        return array_filter(
            ['instrument' => $this->instrumentSlug, 'location' => $this->city, 'lat' => $this->latitude, 'lng' => $this->longitude],
            static fn (?string $value): bool => $value !== null,
        );
    }

    private static function matching(mixed $value, string $pattern): ?string
    {
        return is_string($value) && preg_match($pattern, $value) === 1 ? $value : null;
    }
}
