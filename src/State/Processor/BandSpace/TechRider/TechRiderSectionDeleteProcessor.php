<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderSection;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderSectionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 *
 * A hard delete, unlike riders themselves. A section is a paragraph inside a document, not
 * a document, so there is nothing to restore it to and no archive view that would show it.
 * The confirmation lives in the UI.
 */
readonly class TechRiderSectionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderSectionRepository $sectionRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['riderId'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        $section = $this->sectionRepository->findOneByIdAndRider((string) $uriVariables['id'], $techRider);
        if (!$section instanceof TechRiderSection) {
            throw new NotFoundHttpException('Section introuvable');
        }

        // Read before remove(): the activity outlives the row it points at, and relying on
        // the entity still being readable after removal is a bet on Doctrine internals.
        $sectionId = (string) $section->id;
        $title = $section->title;

        // Positions are left with a hole rather than compacted. ORDER BY does not care, the
        // next section appends past MAX(position), and a reorder rewrites the whole set
        // anyway, so renumbering here would be work nobody reads.
        $this->entityManager->remove($section);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Rider,
            type: BandSpaceRiderActivityType::RiderSectionRemoved,
            resourceId: $sectionId,
            actor: $user,
            payload: ['rider_name' => $techRider->name, 'title' => $title],
        );

        $this->entityManager->flush();
    }
}
