<?php

declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\Song\ChordPro\ChordProParser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/** The Band Space comes from the route, never from the payload, so a body cannot choose where members are looked up. */
class LyricsSingersAreMembersValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90';

    public function __construct(
        private readonly ChordProParser $parser,
        private readonly BandSpaceMembershipRepository $membershipRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof LyricsSingersAreMembers) {
            throw new UnexpectedTypeException($constraint, LyricsSingersAreMembers::class);
        }

        $bandSpaceId = $this->requestStack->getCurrentRequest()?->attributes->get('_route_params')['bandSpaceId'] ?? null;
        if (!is_string($value) || !is_string($bandSpaceId)) {
            return;
        }

        $singerIds = array_values(array_filter(
            $this->parser->singerIds($value),
            static fn (string $id): bool => $id !== ChordProParser::SINGER_ALL,
        ));
        if ($singerIds === []) {
            return;
        }

        $memberIds = array_map(
            static fn (BandSpaceMembership $membership): string => $membership->user->id,
            $this->membershipRepository->findByBandSpaceIdAndUserIds($bandSpaceId, $singerIds),
        );

        if (array_diff($singerIds, $memberIds) !== []) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
