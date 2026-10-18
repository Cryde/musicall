<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageContactOrigin;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfile;
use App\Enum\Message\ContactOriginType;
use App\Translation\ReferenceSnapshotNames;

/**
 * One line's worth of what a direct message was sent from (#998): « recherche un batteur · Rock,
 * Blues · Lyon ». A plain array rather than an object, so it serializes without a generated JSON-LD
 * id, like the agenda metadata. The ids go null once the announce or profile is deleted; the rest is
 * the snapshot taken at send time.
 */
readonly class ContactOriginBuilder
{
    public function __construct(private ReferenceSnapshotNames $referenceSnapshotNames)
    {
    }

    /**
     * @return array{type: string, musician_announce_id: string|null, teacher_profile_id: string|null, announce_type: int|null, instruments: list<string>, styles: list<string>, location_name: string|null}
     */
    public function build(MessageContactOrigin $origin): array
    {
        // « Batteur » rather than « Batterie » for an announce: it names who is wanted, or who is offering.
        $instrumentName = $origin->type === ContactOriginType::MusicianAnnounce
            ? $this->referenceSnapshotNames->instrumentMusicianName(...)
            : $this->referenceSnapshotNames->instrumentName(...);

        return [
            'type' => $origin->type->value,
            'musician_announce_id' => $origin->musicianAnnounce instanceof MusicianAnnounce ? (string) $origin->musicianAnnounce->id : null,
            'teacher_profile_id' => $origin->teacherProfile instanceof TeacherProfile ? (string) $origin->teacherProfile->id : null,
            'announce_type' => $origin->announceType,
            'instruments' => array_map($instrumentName, $origin->instruments),
            'styles' => array_map($this->referenceSnapshotNames->styleName(...), $origin->styles),
            'location_name' => $origin->locationName,
        ];
    }
}
