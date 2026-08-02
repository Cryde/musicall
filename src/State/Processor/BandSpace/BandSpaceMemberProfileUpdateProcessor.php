<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\ApiResource\BandSpace\BandSpaceMemberProfile;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\Role;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceMemberBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceMemberProfile, BandSpaceMember>
 */
readonly class BandSpaceMemberProfileUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceMembershipRepository $membershipRepository,
        private InstrumentRepository $instrumentRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceMemberBuilder $memberBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param BandSpaceMemberProfile $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceMember
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // Membership, not admin: being in the band is the floor, and the caller's own membership
        // is what the self check below compares against.
        [$bandSpace, $callerMembership] = $this->memberChecker->checkMemberForWrite(
            (string) $uriVariables['bandSpaceId'],
            $user,
        );

        $membership = $this->membershipRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$membership instanceof BandSpaceMembership) {
            // 404 rather than 403 for a membership of another space: the caller has no standing to
            // learn whether that id exists.
            throw new NotFoundHttpException('Membre introuvable');
        }

        $isSelf = (string) $membership->id === (string) $callerMembership->id;
        if (!$isSelf && $callerMembership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Vous ne pouvez modifier que votre propre profil');
        }

        // Absent and null differ: a stage-name-only save must not wipe the instruments, and
        // clearing either is a legitimate request. Only the raw payload can tell them apart.
        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $changed = false;

        if (array_key_exists('stage_name', $payload)) {
            // Blank collapses to null, so "no stage name chosen" has one representation and
            // displayName() has one thing to test.
            $stageName = $data->stageName === null ? null : trim($data->stageName);
            $stageName = $stageName === '' ? null : $stageName;

            if ($membership->stageName !== $stageName) {
                $membership->stageName = $stageName;
                $changed = true;
            }
        }

        if (array_key_exists('instrument_ids', $payload)) {
            $changed = $this->applyInstruments($membership, $data->instrumentIds) || $changed;
        }

        if (!$changed) {
            return $this->memberBuilder->buildItem($membership);
        }

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::MemberProfileUpdated,
            resourceId: $membership->user->id,
            actor: $user,
            payload: [
                'target_user_id' => $membership->user->id,
                'target_username' => $membership->user->username,
            ],
        );

        $this->entityManager->flush();

        return $this->memberBuilder->buildItem($membership);
    }

    /**
     * @param list<mixed> $requestedIds
     * @return bool whether the set actually changed
     */
    private function applyInstruments(BandSpaceMembership $membership, array $requestedIds): bool
    {
        $ids = [];
        foreach ($requestedIds as $id) {
            if (!is_string($id)) {
                throw new UnprocessableEntityHttpException('Instrument inconnu');
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));

        $instruments = $this->instrumentRepository->findByIds($ids);
        if (count($instruments) !== count($ids)) {
            // Named rather than merely refused, because with a picker on screen an unknown id
            // means the client and the catalogue disagree and somebody has to see which one.
            $found = array_map(static fn ($instrument): string => (string) $instrument->id, $instruments);
            $missing = array_values(array_diff($ids, $found));

            throw new UnprocessableEntityHttpException(sprintf('Instrument inconnu : %s', $missing[0]));
        }

        $current = array_map(static fn ($instrument): string => (string) $instrument->id, $membership->instruments->toArray());
        $next = array_map(static fn ($instrument): string => (string) $instrument->id, $instruments);
        sort($current);
        sort($next);

        if ($current === $next) {
            return false;
        }

        $membership->instruments->clear();
        foreach ($instruments as $instrument) {
            $membership->instruments->add($instrument);
        }

        return true;
    }
}
