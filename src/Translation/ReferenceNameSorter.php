<?php

declare(strict_types=1);

namespace App\Translation;

use App\Entity\Attribute\Instrument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Orders reference records by their translated name in the request locale. The database already
 * orders the French names it holds, so French keeps that order untouched.
 */
readonly class ReferenceNameSorter
{
    private const string DATABASE_LANGUAGE = 'fr';

    public function __construct(
        #[Autowire(service: 'translator')]
        private TranslatorInterface $translator,
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    /**
     * @template T
     * @param list<T> $items in the database order
     * @param callable(T): string $name the name shown in the request locale
     * @return list<T>
     */
    public function sort(array $items, callable $name): array
    {
        $locale = $this->translator->getLocale();
        if (count($items) < 2 || \Locale::getPrimaryLanguage($locale) === self::DATABASE_LANGUAGE) {
            return $items;
        }

        $names = array_map($name, $items);
        $collator = new \Collator($locale);
        $indexes = array_keys($items);
        // Collator::asort() is not stable, and two records may share a name: the database order breaks the tie.
        usort($indexes, static fn (int $a, int $b): int => ((int) $collator->compare($names[$a], $names[$b])) ?: $a <=> $b);

        return array_map(static fn (int $index): mixed => $items[$index], $indexes);
    }

    /**
     * Each instrument's id and name in the request locale, in that locale's order. Translated once per instrument.
     *
     * @param iterable<Instrument> $instruments in the database order
     * @return list<array{id: string, name: string}>
     */
    public function instrumentNames(iterable $instruments): array
    {
        $named = [];
        foreach ($instruments as $instrument) {
            $named[] = ['id' => (string) $instrument->id, 'name' => $this->referenceNames->instrument($instrument)];
        }

        return $this->sort($named, static fn (array $instrument): string => $instrument['name']);
    }
}
