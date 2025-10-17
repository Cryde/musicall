<?php declare(strict_types=1);

namespace App\State\Processor\Comment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\Procedure\Comment\CreateCommentProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class PostCommentProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private CreateCommentProcedure $createCommentProcedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->createCommentProcedure->process($user, $data);
    }
}
