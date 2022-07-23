<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class Slugifier
{
    public function __construct(
        private readonly SluggerInterface          $slugger,
        private readonly EntityManagerInterface    $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor
    ) {
    }

    public function create(object $object, string $property): string
    {
        $repository = $this->entityManager->getRepository($object::class);

        if (!$this->propertyAccessor->isReadable($object, $property)) {
            throw new \InvalidArgumentException('Property not valid');
        }

        $slugCandidate = $this->propertyAccessor->getValue($object, $property);

        $slug = $this->slugger->slug($slugCandidate)->lower();
        $i = 1;
        $initialSlug = $slug;
        while ($repository->count([$property => $slug]) > 0) {
            $slug = $initialSlug . '-' . $i++;
        }

        return $slug;
    }
}
