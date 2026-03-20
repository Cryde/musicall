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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<MusicianAnnounceCreate, MusicianAnnounce>
 */
readonly class MusicianAnnouncePostProcessor implements ProcessorInterface
{
    public function __construct(
        private Security               $security,
        private EntityManagerInterface $entityManager,
        private MusicianAnnounceBuilder $musicianAnnounceBuilder,
        #[Target('musician_announce')]
        private RateLimiterFactoryInterface $musicianAnnounceLimiter,
        #[Target('musician_announce_daily')]
        private RateLimiterFactoryInterface $musicianAnnounceDailyLimiter,
    ) {
    }

    /**
     * @param MusicianAnnounceCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MusicianAnnounce
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $userIdentifier = $user->getUserIdentifier();
        $this->musicianAnnounceLimiter->create($userIdentifier)->consume()->ensureAccepted();
        $this->musicianAnnounceDailyLimiter->create($userIdentifier)->consume()->ensureAccepted();

        $entity = new MusicianAnnounceEntity();
        $entity->author = $user;
        $entity->type = $data->type;
        $entity->instrument = $data->instrument;
        $entity->styles = new ArrayCollection($data->styles);
        $entity->locationName = $data->locationName;
        $entity->longitude = $data->longitude;
        $entity->latitude = $data->latitude;
        $entity->note = $data->note;

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->musicianAnnounceBuilder->buildItem($entity);
    }
}
