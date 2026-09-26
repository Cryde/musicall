<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song\ChordPro;

/**
 * A singer's colour is picked by the order the lyrics first name them, so the drawer and the PDF
 * agree without storing anything. Dark enough to carry white text; the tint behind the words is the
 * same colour, faded. Mirrors SINGER_PALETTE in chordpro.js.
 */
final class SingerPalette
{
    public const array SINGERS = ['#4f46e5', '#b45309', '#047857', '#e11d48', '#0369a1', '#7c3aed', '#4d7c0f', '#c2410c'];
    public const string ALL = '#64748b';

    public static function forIndex(int $index): string
    {
        return self::SINGERS[$index % count(self::SINGERS)];
    }
}
