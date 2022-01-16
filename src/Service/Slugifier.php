<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class Slugifier
{

    private SluggerInterface $slugger;
    private EntityManagerInterface $entityManager;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        PropertyAccessorInterface $propertyAccessor
    ) {
        $this->slugger = $slugger;
        $this->entityManager = $entityManager;
        $this->propertyAccessor = $propertyAccessor;
    }

    public function create(object $object, string $property): string
    {
        $repository = $this->entityManager->getRepository(get_class($object));

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
