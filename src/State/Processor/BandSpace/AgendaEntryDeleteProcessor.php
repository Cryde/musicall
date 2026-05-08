<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AgendaEntryResource, void>
 */
readonly class AgendaEntryDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryRepository $agendaEntryRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param AgendaEntryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->agendaEntryRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$entry) {
            throw new NotFoundHttpException('Événement introuvable');
        }

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Agenda,
            type: BandSpaceAgendaActivityType::EntryDeleted,
            resourceId: $entry->id,
            actor: $user,
            payload: ['title' => $entry->title],
        );

        $this->entityManager->remove($entry);
        $this->entityManager->flush();
    }
}
