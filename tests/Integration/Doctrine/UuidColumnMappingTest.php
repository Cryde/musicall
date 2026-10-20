<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\User;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Every uuid is a guid column in a string property (#1178). A column type that hydrates an object
 * into a string property never compares equal to its snapshot, so every flush rewrites it: an
 * UPDATE, and an exclusive row lock, per hydrated entity (#985). Nothing else notices it coming back.
 */
#[ResetDatabase]
class UuidColumnMappingTest extends KernelTestCase
{
    public function test_no_column_is_mapped_as_uuid(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $uuidColumns = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->fieldMappings as $field => $mapping) {
                if ($mapping->type === 'uuid') {
                    $uuidColumns[] = $metadata->getName() . '::$' . $field;
                }
            }
        }

        // Symfony's uuid type is BINARY(16) on MariaDB and hydrates an object: use Types::GUID and a
        // string property, as every other id does.
        $this->assertSame([], $uuidColumns);
    }

    public function test_a_hydrated_user_has_nothing_to_flush(): void
    {
        $user = UserFactory::new()->create();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $hydrated = $entityManager->find(User::class, $user->id);
        $this->assertInstanceOf(User::class, $hydrated);
        $unitOfWork = $entityManager->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        $this->assertSame([], $unitOfWork->getEntityChangeSet($hydrated));
    }

    /**
     * Right after an insert, Doctrine keys the entity by the generator's Uuid object rather than the
     * string the property holds, so an UPDATE in the same unit of work binds that object.
     */
    public function test_a_user_updated_after_its_insert_is_written(): void
    {
        $user = UserFactory::new()->create(['username' => 'before']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user->username = 'after';
        $entityManager->flush();
        $entityManager->clear();

        $this->assertSame('after', $entityManager->find(User::class, $user->id)?->username);
    }
}
