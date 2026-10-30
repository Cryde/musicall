<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsCreate;
use App\Entity\User;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The empty payload the request body is written into. The member check comes first, so validation
 * never reveals who belongs to a space the caller is not in.
 *
 * @implements ProviderInterface<LyricsCreate>
 */
readonly class LyricsCreateProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LyricsCreate
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        return new LyricsCreate();
    }
}
