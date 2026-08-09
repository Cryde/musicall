<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpace as BandSpaceDto;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<BandSpaceDto, BandSpaceDto>
 */
readonly class BandSpaceUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceBuilder $bandSpaceBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceDto
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace, $membership] = $this->adminChecker->checkAdminForWrite((string) $uriVariables['id'], $user);

        // The name is the only writable field, and a PATCH that omits it arrives here carrying the
        // current one, so comparing values is enough to tell a real rename from a no-op. Nothing to
        // record and nothing to flush when they match.
        $oldName = $bandSpace->name;
        $newName = trim($data->name);

        if ($oldName !== $newName) {
            $bandSpace->name = $newName;

            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Settings,
                type: BandSpaceSettingsActivityType::BandRenamed,
                resourceId: $bandSpace->id,
                actor: $user,
                payload: ['from' => $oldName, 'to' => $newName],
            );

            $this->entityManager->flush();
        }

        return $this->bandSpaceBuilder->buildItem($bandSpace, $membership->role);
    }
}
