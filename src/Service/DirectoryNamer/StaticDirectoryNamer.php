<?php

namespace App\Service\DirectoryNamer;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\ConfigurableInterface;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

class StaticDirectoryNamer implements DirectoryNamerInterface, ConfigurableInterface
{
    private string $path = '';

    public function directoryName($object, PropertyMapping $mapping): string
    {
        return $this->path;
    }

    public function configure(array $options)
    {
        $this->path = $options['path'] ?? '';
    }
}