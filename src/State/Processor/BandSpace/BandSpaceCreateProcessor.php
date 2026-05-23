<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpace as BandSpaceDto;
use App\ApiResource\BandSpace\BandSpaceCreate;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<BandSpaceCreate, BandSpaceDto>
 */
readonly class BandSpaceCreateProcessor implements ProcessorInterface
{
    private const int MAX_BAND_SPACES_PER_USER = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceBuilder $bandSpaceBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private BandSpaceRepository $bandSpaceRepository,
        private Security $security,
        #[Target('band_space_creation')]
        private RateLimiterFactoryInterface $creationLimiter,
    ) {
    }

    /**
     * @param BandSpaceCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceDto
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $this->creationLimiter->create($user->id)->consume()->ensureAccepted();

        if ($this->bandSpaceRepository->countAdminByUser($user) >= self::MAX_BAND_SPACES_PER_USER) {
            throw new TooManyRequestsHttpException(message: sprintf('Vous avez atteint la limite de %d Band Spaces.', self::MAX_BAND_SPACES_PER_USER));
        }

        // Create BandSpace entity
        $bandSpace = new BandSpace();
        $bandSpace->name = $data->name;

        // Create creator membership
        $creatorMembership = new BandSpaceMembership();
        $creatorMembership->bandSpace = $bandSpace;
        $creatorMembership->user = $user;
        $creatorMembership->role = Role::Admin;

        // Add membership to band space (bidirectional relationship)
        $bandSpace->memberships->add($creatorMembership);

        $this->entityManager->persist($bandSpace);
        $this->entityManager->persist($creatorMembership);

        // BandSpace::id is assigned at persist time (CUSTOM UUID generator),
        // so we can safely read it before flush. record() only persists the
        // activity row - the single flush() below commits all three in one
        // transaction, so a failure can't leave a band space without its
        // membership or activity log.
        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::BandCreated,
            resourceId: $bandSpace->id,
            actor: $user,
            payload: ['name' => $bandSpace->name],
        );

        $this->entityManager->flush();

        return $this->bandSpaceBuilder->buildItem($bandSpace, Role::Admin);
    }
}
