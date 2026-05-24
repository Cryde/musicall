<?php declare(strict_types=1);

namespace App\State\Provider\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Forum\ForumSearchResult;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Forum\PostSnippetExtractor;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * GET /api/forums/search?term=X&page=N
 *
 * @implements ProviderInterface<ForumSearchResult>
 */
readonly class ForumSearchProvider implements ProviderInterface
{
    private const int MIN_TERM_LENGTH = 3;

    public function __construct(
        private ForumTopicRepository $forumTopicRepository,
        private PostSnippetExtractor $snippetExtractor,
        private Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $term = trim((string) ($context['filters']['term'] ?? ''));
        if ($term === '') {
            throw new UnprocessableEntityHttpException('Le terme de recherche est requis');
        }
        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Le terme de recherche doit contenir au moins %d caractères',
                self::MIN_TERM_LENGTH,
            ));
        }

        $page = $this->pagination->getPage($context);
        $limit = $this->pagination->getLimit($operation, $context);

        $result = $this->forumTopicRepository->searchPaginated($term, $page, $limit);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $result['rows'];
        $total = $result['total'];

        $topicIds = array_map(static fn(array $row): string => (string) $row['topic_id'], $rows);
        $bestPosts = $this->forumTopicRepository->findBestMatchingPostByTopic($topicIds, $term);

        $dtos = array_map(
            fn(array $row): ForumSearchResult => $this->buildDto($row, $bestPosts, $term),
            $rows,
        );

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $limit,
            $total,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array{id: string, content: string}> $bestPosts
     */
    private function buildDto(array $row, array $bestPosts, string $term): ForumSearchResult
    {
        $topicId = (string) $row['topic_id'];
        $titleScore = (float) ($row['title_score'] ?? 0);
        $bestPost = $bestPosts[$topicId] ?? null;

        $dto = new ForumSearchResult();
        $dto->topicId = $topicId;
        $dto->topicSlug = (string) $row['topic_slug'];
        $dto->topicTitle = (string) $row['topic_title'];
        $dto->topicPostNumber = (int) $row['topic_post_number'];
        $dto->lastPostDatetime = $row['last_post_datetime'] !== null
            ? (new \DateTimeImmutable((string) $row['last_post_datetime']))->format(\DateTimeInterface::ATOM)
            : null;
        $dto->forumId = (string) $row['forum_id'];
        $dto->forumTitle = (string) $row['forum_title'];
        $dto->forumSlug = (string) $row['forum_slug'];
        $dto->categoryId = (string) $row['category_id'];
        $dto->categoryTitle = (string) $row['category_title'];
        $dto->postSnippet = $bestPost !== null
            ? $this->snippetExtractor->extract($bestPost['content'], $term)
            : null;
        $dto->postId = $bestPost['id'] ?? null;
        $dto->matchType = match (true) {
            $titleScore > 0 && $bestPost !== null => 'both',
            $titleScore > 0 => 'title',
            default => 'post',
        };
        $dto->term = $term;

        return $dto;
    }
}
