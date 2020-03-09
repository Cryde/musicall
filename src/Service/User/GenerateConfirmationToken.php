<?php

namespace App\Service\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class GenerateConfirmationToken
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function generate(User $user)
    {
        $user->setConfirmationToken(sha1(random_bytes(10)));
        $this->entityManager->flush();
    }
}
