<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderMicrophoneSuggestions;
use App\Entity\User;
use App\Repository\BandSpace\TechRiderPatchRowRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\TechRider\TechRiderMicrophoneCatalogue;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<TechRiderMicrophoneSuggestions>
 */
readonly class TechRiderMicrophoneSuggestionsProvider implements ProviderInterface
{
    /** Enough for a dropdown; a band past this many distinct models is typing anyway. */
    private const int MAX_USED = 30;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderPatchRowRepository $patchRowRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TechRiderMicrophoneSuggestions
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $suggestions = new TechRiderMicrophoneSuggestions();
        $suggestions->used = array_map(
            static fn (array $row): array => ['name' => $row['microphone'], 'usage_count' => $row['usage_count']],
            $this->patchRowRepository->findMicrophoneUsageByBandSpace($bandSpace, self::MAX_USED),
        );

        // Case-insensitive, as the column's collation is: « sm58 » already used hides « SM58 ».
        $usedNames = array_map(mb_strtolower(...), array_column($suggestions->used, 'name'));
        $suggestions->catalogue = array_values(array_filter(
            TechRiderMicrophoneCatalogue::MODELS,
            static fn (string $model): bool => !in_array(mb_strtolower($model), $usedNames, true),
        ));

        return $suggestions;
    }
}
