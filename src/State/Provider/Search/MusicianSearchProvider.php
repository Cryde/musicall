<?php declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\AnnounceMusician;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Search\MusicianSearchResultBuilder;
use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<AnnounceMusician>
 */
readonly class MusicianSearchProvider implements ProviderInterface
{
    private const int LIMIT_GUEST = 4;
    private const int LIMIT_AUTHENTICATED = 12;

    public function __construct(
        private Security                    $security,
        private SearchModelBuilder          $searchModelBuilder,
        private MusicianAnnounceRepository  $musicianAnnounceRepository,
        private MusicianSearchResultBuilder $musicianSearchResultBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        // Determine limit based on authentication status
        $limit = $user !== null ? self::LIMIT_AUTHENTICATED : self::LIMIT_GUEST;

        $params = $operation->getParameters();

        $typeValue = $params?->get('type')?->getValue();
        $instrument = $params?->get('instrument')?->getValue();
        $styles = $params?->get('styles')?->getValue();
        $longitude = $params?->get('longitude')?->getValue();
        $latitude = $params?->get('latitude')?->getValue();
        $pageValue = $params?->get('page')?->getValue();

        $page = $pageValue instanceof ParameterNotFound || $pageValue === null ? 1 : (int)$pageValue;

        $searchModel = $this->searchModelBuilder->build(
            $typeValue instanceof ParameterNotFound || $typeValue === null ? null : (int)$typeValue,
            $instrument instanceof ParameterNotFound ? null : $instrument,
            $styles instanceof ParameterNotFound ? [] : ($styles ?? []),
            $longitude instanceof ParameterNotFound ? null : (float)$longitude,
            $latitude instanceof ParameterNotFound ? null : (float)$latitude,
            $page,
            $limit,
        );

        $results = $this->musicianAnnounceRepository->findByCriteria($searchModel, $user, $limit);

        // We don't expose total count, so we use a large arbitrary number to allow pagination
        // The frontend will know there are no more results when member is empty
        $fakeTotal = $page * $limit + (count($results) === $limit ? $limit : 0);

        return new TraversablePaginator(
            new \ArrayIterator($this->musicianSearchResultBuilder->buildFromList($results)),
            $page,
            $limit,
            $fakeTotal,
        );
    }
}
