<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\Attribute\Instrument;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\Teacher\TeacherProfileRepository;

/**
 * The announce or teacher profile a direct message says it was sent from (#998), only the
 * recipient's own: a message cannot claim to be about somebody else's announce.
 */
readonly class ContactOriginResolver
{
    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private TeacherProfileRepository $teacherProfileRepository,
    ) {
    }

    public function musicianAnnounceOf(string $announceId, User $recipient): ?MusicianAnnounce
    {
        $announce = $this->musicianAnnounceRepository->find($announceId);

        return $announce instanceof MusicianAnnounce && (string) $announce->author->id === (string) $recipient->id ? $announce : null;
    }

    public function teacherProfileOf(User $recipient): ?TeacherProfile
    {
        return $this->teacherProfileRepository->findOneBy(['user' => $recipient]);
    }

    /** The instrument a lesson request is about (#1322), only one the teacher teaches. */
    public function taughtInstrumentOf(TeacherProfile $profile, string $instrumentId): ?Instrument
    {
        foreach ($profile->instruments as $taught) {
            if ((string) $taught->instrument->id === $instrumentId) {
                return $taught->instrument;
            }
        }

        return null;
    }
}
