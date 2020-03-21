<?php

namespace App\Service\UserPublication;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class SortAndFilterFromArray
{
    const ITEM_PER_PAGE = 15;

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

        return [
            'sort'   => [$sortBy => $direction],
            'limit'  => $data['perPage'] ?? self::ITEM_PER_PAGE,
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
