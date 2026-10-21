<?php declare(strict_types=1);

namespace App\State\Provider\Admin\Feedback;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Feedback\AdminFeedback;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use App\Repository\Feedback\FeedbackRepository;
use App\Service\Builder\Admin\Feedback\AdminFeedbackBuilder;
use ArrayIterator;

/**
 * @implements ProviderInterface<AdminFeedback>
 */
readonly class AdminFeedbackCollectionProvider implements ProviderInterface
{
    public function __construct(
        private FeedbackRepository $feedbackRepository,
        private AdminFeedbackBuilder $builder,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return TraversablePaginator<AdminFeedback>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $filters = $context['filters'] ?? [];

        $paginator = $this->feedbackRepository->findForAdmin(
            self::readStatuses($operation),
            FeedbackModule::tryFrom(self::readFilter($filters, 'module')),
            FeedbackType::tryFrom(self::readFilter($filters, 'type')),
            $offset,
            $itemsPerPage,
        );

        $resources = $this->builder->buildFromEntities(array_values(iterator_to_array($paginator)));

        return new TraversablePaginator(new ArrayIterator($resources), $page, $itemsPerPage, count($paginator));
    }

    /**
     * Read from the parameter rather than the raw filters, because only the parameter has been through
     * castToArray: `?status=new` arrives here as `['new']`. Every value has already passed the Choice.
     *
     * @return list<FeedbackStatus>
     */
    private static function readStatuses(Operation $operation): array
    {
        $values = $operation->getParameters()?->get('status')?->getValue();
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn (string $value): FeedbackStatus => FeedbackStatus::from($value), $values));
    }

    /**
     * No defensive parsing: ParameterValidatorProvider has already turned an unknown value into a
     * 422 before this runs, so all that is left is "was it sent at all". The string check survives
     * `?type[]=x`, which reaches here as an array rather than a string.
     *
     * @param array<string, mixed> $filters
     */
    private static function readFilter(array $filters, string $key): string
    {
        $value = $filters[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
