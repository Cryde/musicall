<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * The PDF half of the setlist duration format. Mirrors formatDuration() in
 * assets/js/utils/setlistDuration.js, and the two have to stay in step: the point of the filter is
 * that a total printed on a sheet reads exactly like the same total in the editor.
 *
 * A filter rather than a pre-formatted string in the render context, because the large layout needs
 * the format in three places (header total, per item cell, footer total row) and the arithmetic
 * cannot be inlined in Twig without the hour rollover being forgotten again, which is precisely how
 * the header came to print "97 min 30 s".
 *
 * Static, so the class is never instantiated as a Twig runtime.
 */
final class DurationExtension
{
    private const int SECONDS_PER_MINUTE = 60;
    private const int SECONDS_PER_HOUR = 3600;

    /**
     * Hours appear only once there are any: 3:47, 37:30, 1:37:30. An absent duration formats as an
     * empty string so the template picks its own placeholder; a real zero formats as 0:00.
     */
    #[AsTwigFilter('duration_format')]
    public static function format(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '';
        }

        $hours = intdiv($seconds, self::SECONDS_PER_HOUR);
        $minutes = intdiv($seconds % self::SECONDS_PER_HOUR, self::SECONDS_PER_MINUTE);
        $remainingSeconds = $seconds % self::SECONDS_PER_MINUTE;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
}
