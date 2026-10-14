<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\User;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A `uuid` column hydrates a UuidInterface. Held in a `string` property, it never compares equal to
 * its snapshot, so every flush rewrites it: an UPDATE, and an exclusive row lock, per hydrated
 * entity (#985). Nothing else notices it coming back.
 */
#[ResetDatabase]
class UuidColumnMappingTest extends KernelTestCase
{
    public function test_every_uuid_column_is_held_in_a_property_that_accepts_a_uuid_object(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $mismatches = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->fieldMappings as $field => $mapping) {
                if ($mapping->type !== 'uuid') {
                    continue;
                }
                $type = $metadata->getReflectionClass()->getProperty($field)->getType();
                if (!str_contains((string) $type, UuidInterface::class)) {
                    $mismatches[] = $metadata->getName() . '::$' . $field . ' (' . $type . ')';
                }
            }
        }

        // A string property takes Types::GUID, which hydrates a string.
        $this->assertSame([], $mismatches);
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
