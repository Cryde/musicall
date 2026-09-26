<?php

declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\Song\ChordPro\ChordProParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SongSingersAreMembersValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90';

    public function __construct(
        private readonly ChordProParser $parser,
        private readonly BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SongSingersAreMembers) {
            throw new UnexpectedTypeException($constraint, SongSingersAreMembers::class);
        }

        if (!$value instanceof SongLyrics) {
            return;
        }

        $singerIds = array_values(array_filter(
            $this->parser->singerIds($value->lyrics),
            static fn (string $id): bool => $id !== ChordProParser::SINGER_ALL,
        ));
        if ($singerIds === []) {
            return;
        }

        // A malformed id can never be a member, and must not reach a uuid column.
        $wellFormed = array_values(array_filter($singerIds, static fn (string $id): bool => uuid_is_valid($id)));
        $memberIds = array_map(
            static fn (BandSpaceMembership $membership): string => $membership->user->id,
            $this->membershipRepository->findByBandSpaceIdAndUserIds($value->bandSpaceId, $wellFormed),
        );

        if (array_diff($singerIds, $memberIds) !== []) {
            $this->context->buildViolation($constraint->message)
                ->atPath('lyrics')
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
