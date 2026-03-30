<?php declare(strict_types=1);

namespace App\Service\BandSpace;

readonly class ColorAssignmentService
{
    private const array PALETTE = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#F0A500', '#74B9FF', '#A29BFE', '#FD79A8',
    ];

    /**
     * @param string[] $usedColors
     */
    public function assignColor(array $usedColors): string
    {
        $normalized = array_map(
            static fn(string $color): string => strtoupper($color),
            $usedColors
        );

        foreach (self::PALETTE as $color) {
            if (!in_array(strtoupper($color), $normalized, true)) {
                return $color;
            }
        }

        return self::PALETTE[count($usedColors) % count(self::PALETTE)];
    }
}
