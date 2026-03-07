<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceNote as BandSpaceNoteDTO;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\Builder\BandSpace\BandSpaceNoteBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceNoteDTO, BandSpaceNoteDTO>
 */
readonly class BandSpaceNoteUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceNoteRepository $bandSpaceNoteRepository,
        private BandSpaceNoteBuilder $bandSpaceNoteBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param BandSpaceNoteDTO $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceNoteDTO
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships((string) $uriVariables['bandSpaceId']);
        if (!$bandSpace) {
            throw new NotFoundHttpException('Band space not found');
        }

        if (!$this->bandSpaceMembershipRepository->isMember($bandSpace, $user)) {
            throw new AccessDeniedHttpException('You are not a member of this band space');
        }

        $note = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$note) {
            throw new NotFoundHttpException('Note not found');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (array_key_exists('title', $requestPayload)) {
            $note->title = $data->title;
        }

        if (array_key_exists('emoji', $requestPayload)) {
            $note->emoji = $data->emoji;
        }

        if (array_key_exists('content', $requestPayload)) {
            $note->content = $data->content;
        }

        if (array_key_exists('position', $requestPayload)) {
            $note->position = $data->position;
        }

        $note->updateDatetime = new DateTime();

        $this->entityManager->flush();

        return $this->bandSpaceNoteBuilder->buildItem($note);
    }
}
