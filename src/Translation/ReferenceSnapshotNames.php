<?php

declare(strict_types=1);

namespace App\Translation;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Names the instruments and styles a snapshot stores by slug (#1212), in the request locale. A value
 * matching neither a slug nor a French name, a row the migration could not convert, is shown as stored.
 * Both tables are small, so each is read whole once per request.
 */
class ReferenceSnapshotNames implements ResetInterface
{
    /** @var array<string, Instrument>|null slug => instrument */
    private ?array $instrumentsBySlug = null;

    /** @var array<string, Style>|null slug => style */
    private ?array $stylesBySlug = null;

    public function __construct(
        private readonly InstrumentRepository $instrumentRepository,
        private readonly StyleRepository $styleRepository,
        private readonly ReferenceNameTranslator $referenceNameTranslator,
    ) {
    }

    /** What a teacher profile names: the instrument (« Batterie »). */
    public function instrumentName(string $stored): string
    {
        $instrument = $this->instrument($stored, static fn (Instrument $instrument): string => $instrument->name);

        return $instrument instanceof Instrument ? $this->referenceNameTranslator->instrument($instrument) : $stored;
    }

    /** What an announce names: the player (« Batteur »). */
    public function instrumentMusicianName(string $stored): string
    {
        $instrument = $this->instrument($stored, static fn (Instrument $instrument): string => $instrument->musicianName);

        return $instrument instanceof Instrument ? $this->referenceNameTranslator->instrumentMusicianName($instrument) : $stored;
    }

    /** For the moderation screens, which are French whatever the request locale. */
    public function frenchInstrumentMusicianName(string $stored): string
    {
        return $this->instrument($stored, static fn (Instrument $instrument): string => $instrument->musicianName)->musicianName ?? $stored;
    }

    public function styleName(string $stored): string
    {
        $this->stylesBySlug ??= self::bySlug($this->styleRepository->findAll());
        $style = self::find($this->stylesBySlug, $stored, static fn (Style $style): string => $style->name);

        return $style instanceof Style ? $this->referenceNameTranslator->style($style) : $stored;
    }

    public function reset(): void
    {
        $this->instrumentsBySlug = null;
        $this->stylesBySlug = null;
    }

    /** @param \Closure(Instrument): string $frenchName */
    private function instrument(string $stored, \Closure $frenchName): ?Instrument
    {
        $this->instrumentsBySlug ??= self::bySlug($this->instrumentRepository->findAll());

        return self::find($this->instrumentsBySlug, $stored, $frenchName);
    }

    /**
     * A row the old code wrote during the deploy window, after the migration ran, still holds the French
     * name, so that is the second match.
     *
     * @template T of Instrument|Style
     *
     * @param array<string, T>  $bySlug
     * @param \Closure(T): string $frenchName
     *
     * @return T|null
     */
    private static function find(array $bySlug, string $stored, \Closure $frenchName): Instrument|Style|null
    {
        if (isset($bySlug[$stored])) {
            return $bySlug[$stored];
        }
        foreach ($bySlug as $record) {
            if ($frenchName($record) === $stored) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @template T of Instrument|Style
     *
     * @param array<T> $records
     *
     * @return array<string, T>
     */
    private static function bySlug(array $records): array
    {
        $bySlug = [];
        foreach ($records as $record) {
            $bySlug[$record->slug] = $record;
        }

        return $bySlug;
    }
}
