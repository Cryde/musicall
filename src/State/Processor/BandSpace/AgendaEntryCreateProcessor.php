<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryCreate;
use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\User;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\AgendaEntryBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<AgendaEntryCreate, AgendaEntryResource>
 */
readonly class AgendaEntryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private AgendaEntryBuilder $agendaEntryBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param AgendaEntryCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgendaEntryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        try {
            $eventDatetime = new DateTimeImmutable($data->eventDatetime);
        } catch (\Exception) {
            throw new BadRequestHttpException('Date et heure invalides');
        }

        $endDatetime = null;
        if ($data->endDatetime !== null) {
            try {
                $endDatetime = new DateTimeImmutable($data->endDatetime);
            } catch (\Exception) {
                throw new BadRequestHttpException('Date de fin invalide');
            }
        }

        $entry = new AgendaEntry();
        $entry->bandSpace = $bandSpace;
        $entry->creator = $user;
        $entry->title = $data->title;
        $entry->description = $data->description;
        $entry->location = $data->location;
        $entry->eventDatetime = $eventDatetime;
        $entry->endDatetime = $endDatetime;

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $this->agendaEntryBuilder->buildItem($entry);
    }
}
