<?php

declare(strict_types=1);

namespace App\State\Provider\Musician\Match;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Musician\Match\AnnounceMatches;
use App\ApiResource\Musician\Match\AnnounceMatchItem;
use App\ApiResource\Musician\Match\AnsweredAnnounce;
use App\Entity\User;
use App\Model\Musician\AnnounceMatch;
use App\Service\Builder\Search\MusicianSearchResultBuilder;
use App\Service\Musician\Match\AnnounceMatcher;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<AnnounceMatches>
 */
readonly class AnnounceMatchesProvider implements ProviderInterface
{
    private const int LIMIT = 12;

    public function __construct(
        private Security $security,
        private AnnounceMatcher $announceMatcher,
        private MusicianSearchResultBuilder $musicianSearchResultBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AnnounceMatches
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $result = new AnnounceMatches();
        $result->matches = array_map($this->buildItem(...), $this->announceMatcher->matchesFor($user, self::LIMIT));

        return $result;
    }

    private function buildItem(AnnounceMatch $match): AnnounceMatchItem
    {
        $answered = new AnsweredAnnounce();
        $answered->id = (string) $match->answered->id;
        $answered->type = $match->answered->type;
        $answered->instrumentName = $match->answered->instrument->musicianName;
        $answered->locationName = $match->answered->locationName;

        $item = new AnnounceMatchItem();
        $item->announce = $this->musicianSearchResultBuilder->build($match->announce, $match->distanceMetres);
        $item->answered = $answered;
        $item->sharedStyles = $match->sharedStyles;

        return $item;
    }
}
