<?php

declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Musician\MusicianProfileEdit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MusicianProfileEdit, null>
 */
readonly class MusicianProfileDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getMusicianProfile();

        if (!$profile) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }

        $user->setMusicianProfile(null);
        $this->entityManager->remove($profile);
        $this->entityManager->flush();

        return null;
    }
}
