<?php declare(strict_types=1);

namespace App\Validator\Message;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\Message\MessageAttachmentResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidChatAttachmentsValidator extends ConstraintValidator
{
    // Set so the 422 carries a stable identifier: with no code, API Platform builds the error's own
    // id from an object hash, which differs between two identical requests.
    public const string ERROR_CODE = 'music_all_e69cb7be-db44-487b-9142-942258a8fd0d';

    public const string DUPLICATE_ERROR_CODE = 'music_all_bdae4142-b7b4-40a9-b79e-c90d7d4aa79e';

    public function __construct(
        private readonly MessageAttachmentResolver $messageAttachmentResolver,
        private readonly BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidChatAttachments) {
            throw new UnexpectedTypeException($constraint, ValidChatAttachments::class);
        }

        if (!is_array($value) || $value === []) {
            return;
        }

        // Validation runs before the processor, so before the membership check. Saying nothing when
        // the sender is not an active member is what keeps a non-member's answer a 403 instead of a
        // 422 that would tell them whether an id exists in a space they cannot read.
        $viewer = $this->viewerMembership();
        if (!$viewer instanceof BandSpaceMembership) {
            return;
        }

        $resolved = $this->messageAttachmentResolver->resolveIdentifiers(
            $value,
            (string) $viewer->bandSpace->id,
            $viewer,
        );

        $alreadyReferenced = [];
        foreach ($resolved as $index => $target) {
            if ($target === null) {
                $this->addViolation($constraint->message, self::ERROR_CODE, $index);

                continue;
            }

            // On the resolved target rather than on the string sent, so two spellings of one uuid are
            // caught here instead of by the unique index, which would answer with a 500.
            $key = $target['type']->value . '-' . $target['targetId'];
            if (isset($alreadyReferenced[$key])) {
                $this->addViolation($constraint->duplicateMessage, self::DUPLICATE_ERROR_CODE, $index);
            }
            $alreadyReferenced[$key] = true;
        }
    }

    private function addViolation(string $message, string $code, int $index): void
    {
        $this->context->buildViolation($message)
            ->setCode($code)
            ->atPath('[' . $index . ']')
            ->addViolation();
    }

    /**
     * The sender's membership of the space the message is addressed to, looked up by the id in the
     * URI because a validator has no uriVariables of its own.
     */
    private function viewerMembership(): ?BandSpaceMembership
    {
        $routeParams = $this->requestStack->getCurrentRequest()?->attributes->get('_route_params');
        $bandSpaceId = is_array($routeParams) ? ($routeParams['bandSpaceId'] ?? null) : null;
        $user = $this->security->getUser();

        if (!is_string($bandSpaceId) || !$user instanceof User) {
            return null;
        }

        return $this->bandSpaceMembershipRepository->findMembershipByBandSpaceId($bandSpaceId, $user);
    }
}
