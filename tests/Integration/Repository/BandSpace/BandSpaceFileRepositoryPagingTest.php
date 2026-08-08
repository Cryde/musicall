<?php declare(strict_types=1);

namespace App\Tests\Integration\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\Filter\BandSpaceFileFilter;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Paging lives in the query, so it is exercised against the repository rather than through the
 * endpoint: the collection provider adds nothing but the member check and the offset arithmetic,
 * and both are already pinned by the API tests for this collection.
 */
#[ResetDatabase]
class BandSpaceFileRepositoryPagingTest extends KernelTestCase
{
    /**
     * A batch upload stamps one creation datetime on every file in it. Ordering on that alone leaves
     * a tied group up to the database, which may hand the same row to two pages and to neither.
     */
    public function test_pages_do_not_repeat_or_drop_files_sharing_a_creation_datetime(): void
    {
        [$bandSpace, $user] = $this->createBandSpace();
        $this->createFiles($bandSpace, $user, [
            'tie-a.pdf' => '2026-04-01 12:00:00',
            'tie-b.pdf' => '2026-04-01 12:00:00',
            'tie-c.pdf' => '2026-04-01 12:00:00',
            'tie-d.pdf' => '2026-04-01 12:00:00',
        ]);

        $names = [...$this->pageOfNames($bandSpace, 2, 0), ...$this->pageOfNames($bandSpace, 2, 2)];

        sort($names);
        $this->assertSame(['tie-a.pdf', 'tie-b.pdf', 'tie-c.pdf', 'tie-d.pdf'], $names);
    }

    /**
     * The tags collection is fetch joined, so one file becomes one row per tag. Applying the limit to
     * those rows cut a page short, and the shortfall read to the client as "nothing left to load".
     */
    public function test_a_page_holds_its_full_size_when_files_carry_several_tags(): void
    {
        [$bandSpace, $user] = $this->createBandSpace();

        $tags = new ArrayCollection([
            BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'masters'])->create(),
            BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'live'])->create(),
            BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'demos'])->create(),
        ]);

        foreach ([
            'file-a.pdf' => '2026-03-01 10:00:00',
            'file-b.pdf' => '2026-03-02 10:00:00',
            'file-c.pdf' => '2026-03-03 10:00:00',
            'file-d.pdf' => '2026-03-04 10:00:00',
        ] as $name => $createdAt) {
            BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => $name,
                'creationDatetime' => new \DateTime($createdAt),
                'tags' => $tags,
            ])->create();
        }

        $this->assertSame(['file-d.pdf', 'file-c.pdf'], $this->pageOfNames($bandSpace, 2, 0));
        $this->assertSame(['file-b.pdf', 'file-a.pdf'], $this->pageOfNames($bandSpace, 2, 2));
    }

    /**
     * @return array{0: BandSpace, 1: User}
     */
    private function createBandSpace(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();

        return [BandSpaceFactory::new()->create(), $user];
    }

    /**
     * @param array<string, string> $creationDatetimeByName
     */
    private function createFiles(BandSpace $bandSpace, User $user, array $creationDatetimeByName): void
    {
        foreach ($creationDatetimeByName as $name => $createdAt) {
            BandSpaceFileFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'originalName' => $name,
                'creationDatetime' => new \DateTime($createdAt),
            ])->create();
        }
    }

    /**
     * @return list<string>
     */
    private function pageOfNames(BandSpace $bandSpace, int $limit, int $offset): array
    {
        $files = static::getContainer()->get(BandSpaceFileRepository::class)->findByBandSpace(
            $bandSpace,
            new BandSpaceFileFilter(limit: $limit, offset: $offset),
        );

        return array_map(static fn(BandSpaceFile $file): string => $file->originalName, $files);
    }
}
