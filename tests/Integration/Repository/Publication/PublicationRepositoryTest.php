<?php

namespace App\Tests\Integration\Repository\Publication;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Tests\Factory\Publication\PublicationFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class PublicationRepositoryTest extends KernelTestCase
{
    public function test_find_one_video(): void
    {
        $repo = static::getContainer()->get(PublicationRepository::class);

        $video = PublicationFactory::createOne(['content' => 'content-id', 'type' => Publication::TYPE_VIDEO]);
        PublicationFactory::createOne(['content' => 'content-id', 'type' => Publication::TYPE_TEXT]);

        $result = $repo->findOneVideo('content-id');
        $this->assertSame($video, $result);
    }

    public function test_find_translation_siblings_returns_the_other_translations_of_the_group(): void
    {
        $repo = static::getContainer()->get(PublicationRepository::class);

        $french = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr', 'status' => Publication::STATUS_ONLINE]);
        $english = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en', 'status' => Publication::STATUS_DRAFT]);
        $english->joinTranslationGroupOf($french);
        $german = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'de', 'status' => Publication::STATUS_ONLINE]);
        $german->joinTranslationGroupOf($french);
        $otherGroup = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en']);
        $otherGroup->joinTranslationGroupOf(PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']));
        PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en']);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->assertSame([$german, $english], $repo->findTranslationSiblings($french));
        $this->assertSame([$english, $french], $repo->findTranslationSiblings($german));
    }

    public function test_find_translation_siblings_of_an_ungrouped_publication_is_empty(): void
    {
        $repo = static::getContainer()->get(PublicationRepository::class);

        $alone = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']);
        PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en']);

        $this->assertSame([], $repo->findTranslationSiblings($alone));
    }

    public function test_find_languages_by_translation_group_reads_every_group_asked_for_at_once(): void
    {
        $repo = static::getContainer()->get(PublicationRepository::class);

        $french = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']);
        $english = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en']);
        $english->joinTranslationGroupOf($french);
        $otherFrench = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']);
        $otherGerman = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'de']);
        $otherGerman->joinTranslationGroupOf($otherFrench);
        $notAsked = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en']);
        $notAsked->joinTranslationGroupOf(PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        // A map, so its key order is not part of the answer; each list is sorted by language.
        $this->assertEquals(
            [
                (string) $french->translationGroup => ['en', 'fr'],
                (string) $otherFrench->translationGroup => ['de', 'fr'],
            ],
            $repo->findLanguagesByTranslationGroup([(string) $french->translationGroup, (string) $otherFrench->translationGroup]),
        );
        $this->assertSame([], $repo->findLanguagesByTranslationGroup([]));
    }

    public function test_ungrouped_publications_and_videos_share_a_language_freely(): void
    {
        PublicationFactory::createMany(2, ['type' => Publication::TYPE_TEXT, 'language' => 'fr']);
        PublicationFactory::createMany(2, ['type' => Publication::TYPE_VIDEO]);

        $this->assertSame(4, PublicationFactory::count());
    }

    public function test_a_group_holds_at_most_one_publication_per_language(): void
    {
        // The entity only sees the sibling it joins, so a second French text joining through the English one gets past it.
        $french = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']);
        $english = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'en']);
        $english->joinTranslationGroupOf($french);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $secondFrench = PublicationFactory::createOne(['type' => Publication::TYPE_TEXT, 'language' => 'fr']);
        $secondFrench->joinTranslationGroupOf($english);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('publication_translation_group_language_unique');

        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
