<?php declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Musician\MusicianAnnounce;
use App\ApiResource\Musician\MusicianAnnounceCreate;
use App\Entity\Musician\MusicianAnnounce as MusicianAnnounceEntity;
use App\Entity\User;
use App\Service\Builder\Musician\MusicianAnnounceBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MusicianAnnounceCreate, MusicianAnnounce>
 */
readonly class MusicianAnnouncePostProcessor implements ProcessorInterface
{
    public function __construct(
        private Security               $security,
        private EntityManagerInterface $entityManager,
        private MusicianAnnounceBuilder $musicianAnnounceBuilder,
    ) {
    }

    /**
     * @param MusicianAnnounceCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MusicianAnnounce
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $entity = new MusicianAnnounceEntity();
        $entity->setAuthor($user);
        $entity->setType($data->type);
        $entity->setInstrument($data->instrument);
        $entity->setStyles(new ArrayCollection($data->styles));
        $entity->setLocationName($data->locationName);
        $entity->setLongitude($data->longitude);
        $entity->setLatitude($data->latitude);
        $entity->setNote($data->note);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->musicianAnnounceBuilder->buildItem($entity);
    }
}
