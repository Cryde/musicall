<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\User;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\AgendaEntryBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AgendaEntryResource, AgendaEntryResource>
 */
readonly class AgendaEntryUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryRepository $agendaEntryRepository,
        private AgendaEntryBuilder $agendaEntryBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param AgendaEntryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgendaEntryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->agendaEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$entry) {
            throw new NotFoundHttpException('Événement introuvable');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (array_key_exists('title', $payload)) {
            $entry->title = $data->title;
        }

        if (array_key_exists('description', $payload)) {
            $entry->description = $data->description;
        }

        if (array_key_exists('location', $payload)) {
            $entry->location = $data->location;
        }

        if (array_key_exists('event_datetime', $payload) || array_key_exists('eventDatetime', $payload)) {
            try {
                $entry->eventDatetime = new DateTimeImmutable($data->eventDatetime);
            } catch (\Exception) {
                throw new BadRequestHttpException('Date et heure invalides');
            }
        }

        if (array_key_exists('end_datetime', $payload) || array_key_exists('endDatetime', $payload)) {
            if ($data->endDatetime === null) {
                $entry->endDatetime = null;
            } else {
                try {
                    $entry->endDatetime = new DateTimeImmutable($data->endDatetime);
                } catch (\Exception) {
                    throw new BadRequestHttpException('Date de fin invalide');
                }
            }
        }

        $this->entityManager->flush();

        return $this->agendaEntryBuilder->buildItem($entry);
    }
}
