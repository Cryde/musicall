<?php declare(strict_types=1);

namespace App\Service;

use App\Contracts\SluggableEntityInterface;
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

    public function create(SluggableEntityInterface $object, string $property): string
    {
        $repository = $this->entityManager->getRepository($object::class);

        if (!$this->propertyAccessor->isReadable($object, $property)) {
            throw new \InvalidArgumentException('Property not valid');
        }

        $slugCandidate = $this->propertyAccessor->getValue($object, $property);

        $slug = $this->slugger->slug($slugCandidate)->lower()->toString();
        $i = 1;
        $initialSlug = $slug;
        while ($repository->count(['slug' => $slug]) > 0) {
            $slug = $initialSlug . '-' . $i++;
        }

        return $slug;
    }
}
