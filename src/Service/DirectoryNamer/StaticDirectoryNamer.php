<?php

namespace App\Service\DirectoryNamer;

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Vich\UploaderBundle\Exception\NameGenerationException;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\ConfigurableInterface;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

class StaticDirectoryNamer implements DirectoryNamerInterface, ConfigurableInterface
{
    private string $path = '';
    private string $propertyPath = '';
    protected PropertyAccessorInterface $propertyAccessor;

    public function __construct(?PropertyAccessorInterface $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    public function directoryName($object, PropertyMapping $mapping): string
    {
        // When we specify a propertyPath we act like "Vich\UploaderBundle\Naming\PropertyDirectoryNamer"
        if ($this->propertyPath) {
            try {
                $name = $this->propertyAccessor->getValue($object, $this->propertyPath);
            } catch (NoSuchPropertyException $e) {
                throw new NameGenerationException(\sprintf('Directory name could not be generated: property %s does not exist.', $this->propertyPath), $e->getCode(), $e);
            }
            if (empty($name)) {
                throw new NameGenerationException(\sprintf('Directory name could not be generated: property %s is empty.', $this->propertyPath));
            }

            dump($this->path . '/' . $name);

            return $this->path . '/' . $name;
        }

        return $this->path;
    }

    public function configure(array $options)
    {
        $this->path = $options['path'] ?? '';
        $this->propertyPath = $options['property'] ?? '';
    }
}