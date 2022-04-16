<?php

namespace App\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

class RefreshTokenManager implements RefreshTokenManagerInterface
{
    protected EntityManagerInterface $objectManager;
    protected string $class;
    protected RefreshTokenRepository $repository;

    public function __construct(EntityManagerInterface $om, string $class)
    {
        $this->objectManager = $om;
        /** @var RefreshTokenRepository $repository */
        $repository = $om->getRepository($class);
        $this->repository = $repository;
        $metadata = $om->getClassMetadata($class);
        $this->class = $metadata->getName();
    }

    /**
     * @param string $refreshToken
     *
     * @return RefreshToken|RefreshTokenInterface|mixed|object|null
     */
    public function get($refreshToken)
    {
        return $this->repository->findOneBy(['refreshToken' => $refreshToken]);
    }

    /**
     * @param string $username
     *
     * @return RefreshToken|RefreshTokenInterface|mixed|object|null
     */
    public function getLastFromUsername($username)
    {
        return $this->repository->findOneBy(['username' => $username], ['valid' => 'DESC']);
    }

    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $this->objectManager->persist($refreshToken);
        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $this->objectManager->remove($refreshToken);
        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid($datetime = null, bool $andFlush = true): array
    {
        $invalidTokens = $this->repository->findInvalid($datetime);
        foreach ($invalidTokens as $invalidToken) {
            $this->objectManager->remove($invalidToken);
        }
        if ($andFlush) {
            $this->objectManager->flush();
        }

        return $invalidTokens;
    }

    /**
     * Returns the RefreshToken fully qualified class name.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function create()
    {
        $class = $this->getClass();

        return new $class();
    }
}
