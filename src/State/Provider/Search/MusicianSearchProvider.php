<?php declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Search\MusicianSearchResultBuilder;
use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class MusicianSearchProvider implements ProviderInterface
{
    public function __construct(
        private Security                    $security,
        private SearchModelBuilder          $searchModelBuilder,
        private MusicianAnnounceRepository  $musicianAnnounceRepository,
        private MusicianSearchResultBuilder $musicianSearchResultBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$params = $operation->getParameters()) {
            return [];
        }

        if(!$instrument = $params->get('instrument')?->getValue()) {
            return [];
        }

        $styles = $params->get('styles')?->getValue();
        $longitude = $params->get('longitude')?->getValue();
        $latitude = $params->get('latitude')?->getValue();

        /** @var User|null $user */
        $user = $this->security->getUser();
        $searchModel = $this->searchModelBuilder->build(
            (int)$params->get('type')?->getValue(),
            $instrument,
            $styles instanceof ParameterNotFound ? [] : $styles,
            $longitude instanceof ParameterNotFound ? null : (float)$longitude,
            $latitude instanceof ParameterNotFound ? null : (float)$latitude,
        );
        $results = $this->musicianAnnounceRepository->findByCriteria($searchModel, $user);

        return $this->musicianSearchResultBuilder->buildFromList($results);
    }
}
