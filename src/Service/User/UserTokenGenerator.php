<?php declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserTokenGenerator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function generate(User $user): void
    {
        $user->setToken(sha1(random_bytes(10)));
        $this->entityManager->flush();
    }
}
