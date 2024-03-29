<?php

namespace App\Service\UserPublication;

use App\Entity\Publication;
use App\Repository\PublicationSubCategoryRepository;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class SortAndFilterFromArray
{
    final const ITEM_PER_PAGE = 15;

    public function __construct(private readonly PublicationSubCategoryRepository $publicationSubCategoryRepository)
    {
    }

    public function createFromArray(array $data): array
    {
        // @todo : improve by abstract if into an object
        $sortBy = $data['sortBy'] ? $this->fieldConverter($data['sortBy']) : 'creationDatetime';
        $direction = 'DESC';
        if (isset($data['sortDesc'])) {
            $direction = $data['sortDesc'] ? 'DESC' : 'ASC';
        }

        $arrayFilters = [];
        if (isset($data['filter'])) {
            if (isset($data['filter']['category_id'])) {
                $category = $this->publicationSubCategoryRepository->find($data['filter']['category_id']);
                if ($category) {
                    $arrayFilters['subCategory'] = $category;
                }
            }
            if (isset($data['filter']['status']) && in_array($data['filter']['status'], Publication::ALL_STATUS)) {
                $arrayFilters['status'] = $data['filter']['status'];
            }
        }

        return [
            'sort'   => [$sortBy => $direction],
            'limit'  => $data['perPage'] ?? self::ITEM_PER_PAGE,
            'filters' => $arrayFilters,
            'offset' => $data['currentPage'] ? ($data['currentPage'] - 1) * self::ITEM_PER_PAGE : 0,
        ];
    }

    private function fieldConverter(string $name): string
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->denormalize($name);
    }
}
