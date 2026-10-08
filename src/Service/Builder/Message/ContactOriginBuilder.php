<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageContactOrigin;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfile;

/**
 * One line's worth of what a direct message was sent from (#998): « recherche un batteur · Rock,
 * Blues · Lyon ». A plain array rather than an object, so it serializes without a generated JSON-LD
 * id, like the agenda metadata. The ids go null once the announce or profile is deleted; the rest is
 * the snapshot taken at send time.
 */
class ContactOriginBuilder
{
    /**
     * @return array{type: string, musician_announce_id: string|null, teacher_profile_id: string|null, announce_type: int|null, instruments: list<string>, styles: list<string>, location_name: string|null}
     */
    public function build(MessageContactOrigin $origin): array
    {
        return [
            'type' => $origin->type->value,
            'musician_announce_id' => $origin->musicianAnnounce instanceof MusicianAnnounce ? (string) $origin->musicianAnnounce->id : null,
            'teacher_profile_id' => $origin->teacherProfile instanceof TeacherProfile ? (string) $origin->teacherProfile->id : null,
            'announce_type' => $origin->announceType,
            'instruments' => $origin->instruments,
            'styles' => $origin->styles,
            'location_name' => $origin->locationName,
        ];
    }
}
