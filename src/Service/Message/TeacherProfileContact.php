<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\Attribute\Instrument;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileInstrument;
use App\Enum\Message\LessonRequest;

/**
 * A message sent from a teacher profile (#998), and for a lesson request (#1322) the instrument chosen and what
 * is asked. A plain question carries neither.
 */
final readonly class TeacherProfileContact
{
    public function __construct(
        public TeacherProfile $profile,
        public ?Instrument $instrument = null,
        public ?LessonRequest $lessonRequest = null,
    ) {
    }

    /**
     * The instruments the card names: the one chosen, otherwise all those taught.
     *
     * @return list<string>
     */
    public function instrumentSlugs(): array
    {
        if ($this->instrument instanceof Instrument) {
            return [$this->instrument->slug];
        }

        return array_values(array_map(
            static fn (TeacherProfileInstrument $taught): string => $taught->instrument->slug,
            $this->profile->instruments->toArray(),
        ));
    }
}
