<?php declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class MusicianAnnouncePostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param MusicianAnnounce $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $data->setAuthor($user);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
