<?php

namespace App\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManager as BaseRefreshTokenManager;

class RefreshTokenManager extends BaseRefreshTokenManager
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
     * @return RefreshTokenInterface
     */
    public function get($refreshToken)
    {
        return $this->repository->findOneBy(['refreshToken' => $refreshToken]);
    }

    /**
     * @param string $username
     *
     * @return RefreshTokenInterface
     */
    public function getLastFromUsername($username)
    {
        return $this->repository->findOneBy(['username' => $username], ['valid' => 'DESC']);
    }

    /**
     * @param RefreshTokenInterface $refreshToken
     * @param bool|true             $andFlush
     */
    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true)
    {
        $this->objectManager->persist($refreshToken);
        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @param RefreshTokenInterface $refreshToken
     * @param bool                  $andFlush
     */
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true)
    {
        $this->objectManager->remove($refreshToken);
        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @param \DateTime $datetime
     * @param bool      $andFlush
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid($datetime = null, bool $andFlush = true)
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
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}
