<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Attribute\Style;
use App\Entity\Message\Message;
use App\Entity\Message\MessageContactOrigin;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument;
use App\Enum\Message\ContactOriginType;

/** Instruments and styles are kept by slug (#1212), so each reader names them in their own language. */
class MessageContactOriginDirector
{
    public function create(Message $message, MusicianAnnounce|TeacherProfile $source): MessageContactOrigin
    {
        $origin = new MessageContactOrigin();
        $origin->message = $message;

        if ($source instanceof MusicianAnnounce) {
            $origin->type = ContactOriginType::MusicianAnnounce;
            $origin->musicianAnnounce = $source;
            $origin->announceType = $source->type;
            $origin->instruments = [$source->instrument->slug];
            $origin->styles = self::styleSlugs($source->styles->toArray());
            $origin->locationName = $source->locationName;

            return $origin;
        }

        $origin->type = ContactOriginType::TeacherProfile;
        $origin->teacherProfile = $source;
        $origin->instruments = array_values(array_map(
            static fn (TeacherProfileInstrument $taught): string => $taught->instrument->slug,
            $source->instruments->toArray(),
        ));
        $origin->styles = self::styleSlugs($source->styles->toArray());

        return $origin;
    }

    /**
     * @param Style[] $styles
     *
     * @return list<string>
     */
    private static function styleSlugs(array $styles): array
    {
        return array_values(array_map(static fn (Style $style): string => $style->slug, $styles));
    }
}
