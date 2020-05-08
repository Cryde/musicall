<?php

namespace App\Service\UserPublication;

use App\Repository\PublicationSubCategoryRepository;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class SortAndFilterFromArray
{
    const ITEM_PER_PAGE = 15;
    /**
     * @var PublicationSubCategoryRepository
     */
    private PublicationSubCategoryRepository $publicationSubCategoryRepository;

    public function __construct(PublicationSubCategoryRepository $publicationSubCategoryRepository)
    {
        $this->publicationSubCategoryRepository = $publicationSubCategoryRepository;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function createFromArray(array $data)
    {
        // @todo : improve by abstract if into an object
        $sortBy = $data['sortBy'] ? $this->fieldConverter($data['sortBy']) : 'creationDatetime';
        $direction = 'DESC';
        if (isset($data['sortDesc'])) {
            $direction = $data['sortDesc'] ? 'DESC' : 'ASC';
        }

        $arrayFilters = [];

        if (isset($data['filter']) && isset($data['filter']['category_id'])) {
            $category = $this->publicationSubCategoryRepository->find($data['filter']['category_id']);
            if ($category) {
                $arrayFilters['subCategory'] = $category;
            }
        }

        return [
            'sort'   => [$sortBy => $direction],
            'limit'  => $data['perPage'] ?? self::ITEM_PER_PAGE,
            'filters' => $arrayFilters,
            'offset' => $data['currentPage'] ? ($data['currentPage'] - 1) * self::ITEM_PER_PAGE : 0,
        ];
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function fieldConverter(string $name)
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->denormalize($name);
    }
}
