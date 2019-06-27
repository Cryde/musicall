<?php

namespace App\Service\UserPublication;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class SortAndFilterFromArray
{
    /**
     * @param array $data
     *
     * @return array
     */
    public function createFromArray(array $data)
    {
        // @todo : improve by abstract if into an object
        $sortBy = $data['sortBy'] ? $this->fieldConverter($data['sortBy']) : 'editionDatetime';
        $direction = 'DESC';
        if (isset($data['sortDesc'])) {
            $direction = $data['sortDesc'] ? 'DESC' : 'ASC';
        }

        return [
            'sort' => [$sortBy => $direction],
        ];
    }

    /**
     * @param string $name
     *
     * @return string|string[]|null
     */
    private function fieldConverter(string $name)
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->denormalize($name);
    }
}
