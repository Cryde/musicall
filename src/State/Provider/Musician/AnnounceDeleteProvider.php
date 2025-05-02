<?php

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\Musician\MusicianAnnounceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class AnnounceDeleteProvider implements ProviderInterface
{
    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private Security                   $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        $currentUser = $this->security->getUser();
        if (!$announce = $this->musicianAnnounceRepository->findOneBy(['id' => $uriVariables['id'], 'author' => $currentUser])) {
            throw new NotFoundHttpException('Announce not found.');
        }

        return $announce;
    }
}