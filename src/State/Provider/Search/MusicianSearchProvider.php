<?php declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\AnnounceMusician;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Search\MusicianSearchResultBuilder;
use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use App\Service\Search\MusicianSearchRecorder;
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
        private MusicianSearchRecorder      $musicianSearchRecorder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        // Determine limit based on authentication status
        $limit = $user !== null ? self::LIMIT_AUTHENTICATED : self::LIMIT_GUEST;

        $params = $operation->getParameters();

        $location = $params?->get('location')?->getValue();
        $landing = $params?->get('landing')?->getValue();

        $searchModel = $this->searchModelBuilder->buildFromParameters($params, $limit);
        $page = $searchModel->page;

        $results = $this->musicianAnnounceRepository->findByCriteria($searchModel, $user, $limit);
        $this->musicianSearchRecorder->recordFiltersSearch(
            $searchModel,
            is_string($location) ? $location : null,
            count($results),
            $landing === '1',
        );

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
