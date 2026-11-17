<?php

declare(strict_types=1);

namespace App\Validator\Service;

use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ServiceKindNotOfferedValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_5b0d6c9e-3f1a-4e27-9c84-7a2e1d3b6f50';

    public function __construct(
        private readonly Security $security,
        private readonly ServiceRepository $serviceRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ServiceKindNotOffered) {
            throw new UnexpectedTypeException($constraint, ServiceKindNotOffered::class);
        }
        $user = $this->security->getUser();
        if (!is_string($value) || !($kind = ServiceKind::tryFrom($value)) instanceof ServiceKind || !$user instanceof User) {
            return;
        }

        if ($this->serviceRepository->findOneByUserAndKind($user, $kind) instanceof Service) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ kind }}', $kind->trans($this->translator))
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
