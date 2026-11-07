<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Attribute\Style;
use App\Entity\Message\Message;
use App\Entity\Message\MessageContactOrigin;
use App\Entity\Musician\MusicianAnnounce;
use App\Enum\Message\ContactOriginType;
use App\Service\Message\TeacherProfileContact;

/** Instruments and styles are kept by slug (#1212), so each reader names them in their own language. */
class MessageContactOriginDirector
{
    public function create(Message $message, MusicianAnnounce|TeacherProfileContact $source): MessageContactOrigin
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
        $origin->teacherProfile = $source->profile;
        $origin->instruments = $source->instrumentSlugs();
        $origin->styles = self::styleSlugs($source->profile->styles->toArray());
        $origin->lessonRequest = $source->lessonRequest;

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
