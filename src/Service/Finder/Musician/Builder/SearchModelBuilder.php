<?php

namespace App\Service\Finder\Musician\Builder;

use App\Entity\Attribute\Style;
use App\Exception\Musician\InvalidFormatReturnedException;
use App\Model\Search\Musician;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SearchModelBuilder
{
    public function __construct(
        private readonly SerializerInterface  $serializer,
        private readonly ValidatorInterface   $validator,
        private readonly InstrumentRepository $instrumentRepository,
        private readonly StyleRepository      $styleRepository,
    ) {
    }

    /**
     * @throws InvalidFormatReturnedException
     */
    public function build(string $json): Musician
    {
        /** @var Musician $searchModel */
        $searchModel = $this->serializer->deserialize($json, Musician::class, 'json');
        $violations = $this->validator->validate($searchModel);
        if (count($violations) > 0) {
            throw new InvalidFormatReturnedException();
        }
        $searchModel->setStyles($this->formatStylesSlug($searchModel->getStyles()));
        $searchModel->setInstrument($this->formatInstrumentSlug($searchModel->getInstrument()));

        return $searchModel;
    }

    private function formatStylesSlug(array $slugs): array
    {
        return array_map(fn(Style $s) => $s->getId(), $this->styleRepository->findBy(['slug' => $slugs]));
    }

    private function formatInstrumentSlug(string $slug): string
    {
        return $this->instrumentRepository->findOneBy(['slug' => $slug])->getId();
    }
}