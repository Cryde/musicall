<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationInfo;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceInvitationInfoProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BandSpaceInvitationInfo
    {
        $invitation = $this->bandSpaceInvitationRepository->findPendingByToken((string) $uriVariables['token']);
        if (!$invitation instanceof \App\Entity\BandSpace\BandSpaceInvitation) {
            throw new NotFoundHttpException('Invitation introuvable ou expirée');
        }

        $dto = new BandSpaceInvitationInfo();
        $dto->token = $invitation->token;
        $dto->email = $invitation->email;
        $dto->bandSpaceName = $invitation->bandSpace->name;

        return $dto;
    }
}
