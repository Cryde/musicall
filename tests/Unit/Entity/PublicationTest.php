<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Publication;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PublicationTest extends TestCase
{
    public function test_a_text_is_in_its_author_language(): void
    {
        $publication = $this->publicationBy('en');

        $publication->assignType(Publication::TYPE_TEXT);

        self::assertSame(Publication::TYPE_TEXT, $publication->type);
        self::assertSame('en', $publication->language);
    }

    public function test_a_video_has_no_language(): void
    {
        $publication = $this->publicationBy('en');

        $publication->assignType(Publication::TYPE_VIDEO);

        self::assertSame(Publication::TYPE_VIDEO, $publication->type);
        self::assertNull($publication->language);
    }

    public function test_a_video_turned_into_a_text_takes_the_author_language(): void
    {
        $publication = $this->publicationBy('en');
        $publication->assignType(Publication::TYPE_VIDEO);

        $publication->assignType(Publication::TYPE_TEXT);

        self::assertSame('en', $publication->language);
    }

    public function test_the_author_must_be_set_first(): void
    {
        $this->expectException(\Error::class);

        (new Publication())->assignType(Publication::TYPE_TEXT);
    }

    public function test_a_translation_opens_a_group_shared_with_its_sibling(): void
    {
        $original = $this->publicationBy('fr');
        $original->assignType(Publication::TYPE_TEXT);
        $translation = $this->publicationBy('en');
        $translation->assignType(Publication::TYPE_TEXT);

        $translation->joinTranslationGroupOf($original);

        self::assertNotNull($original->translationGroup);
        self::assertSame($original->translationGroup, $translation->translationGroup);
    }

    public function test_a_translation_joins_the_existing_group_of_its_sibling(): void
    {
        $original = $this->publicationBy('fr');
        $original->assignType(Publication::TYPE_TEXT);
        $original->translationGroup = '0199e8a0-0000-7000-8000-000000000001';
        $translation = $this->publicationBy('en');
        $translation->assignType(Publication::TYPE_TEXT);

        $translation->joinTranslationGroupOf($original);

        self::assertSame('0199e8a0-0000-7000-8000-000000000001', $original->translationGroup);
        self::assertSame('0199e8a0-0000-7000-8000-000000000001', $translation->translationGroup);
    }

    public function test_a_video_cannot_join_a_translation_group(): void
    {
        $text = $this->publicationBy('fr');
        $text->assignType(Publication::TYPE_TEXT);
        $video = $this->publicationBy('fr');
        $video->assignType(Publication::TYPE_VIDEO);

        try {
            $video->joinTranslationGroupOf($text);
            self::fail('A video joined a translation group.');
        } catch (\LogicException $exception) {
            self::assertSame('A video publication cannot join a translation group.', $exception->getMessage());
        }

        self::assertNull($video->translationGroup);
        self::assertNull($text->translationGroup);
    }

    public function test_a_text_cannot_become_a_translation_of_a_video(): void
    {
        $text = $this->publicationBy('fr');
        $text->assignType(Publication::TYPE_TEXT);
        $video = $this->publicationBy('fr');
        $video->assignType(Publication::TYPE_VIDEO);

        try {
            $text->joinTranslationGroupOf($video);
            self::fail('A video was given a translation group.');
        } catch (\LogicException $exception) {
            self::assertSame('A video publication cannot join a translation group.', $exception->getMessage());
        }

        self::assertNull($video->translationGroup);
        self::assertNull($text->translationGroup);
    }

    public function test_a_publication_cannot_be_a_translation_of_itself(): void
    {
        $text = $this->textIn('fr');

        $this->assertJoinRefused($text, $text, 'A publication cannot be a translation of itself.');

        self::assertNull($text->translationGroup);
    }

    public function test_a_publication_already_in_a_group_cannot_join_another_one(): void
    {
        $grouped = $this->textIn('en');
        $grouped->translationGroup = '0199e8a0-0000-7000-8000-000000000001';
        $sibling = $this->textIn('fr');

        $this->assertJoinRefused($grouped, $sibling, 'A publication already in a translation group cannot join another one.');

        self::assertSame('0199e8a0-0000-7000-8000-000000000001', $grouped->translationGroup);
        self::assertNull($sibling->translationGroup);
    }

    public function test_a_translation_cannot_share_the_language_of_its_sibling(): void
    {
        $original = $this->textIn('fr');
        $translation = $this->textIn('fr');

        $this->assertJoinRefused($translation, $original, 'A translation group holds one publication per language.');

        self::assertNull($original->translationGroup);
        self::assertNull($translation->translationGroup);
    }

    private function assertJoinRefused(Publication $publication, Publication $sibling, string $expectedMessage): void
    {
        try {
            $publication->joinTranslationGroupOf($sibling);
            self::fail('The publication joined the translation group.');
        } catch (\LogicException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
        }
    }

    private function textIn(string $locale): Publication
    {
        $publication = $this->publicationBy($locale);
        $publication->assignType(Publication::TYPE_TEXT);

        return $publication;
    }

    private function publicationBy(string $locale): Publication
    {
        $author = new User();
        $author->locale = $locale;
        $publication = new Publication();
        $publication->author = $author;

        return $publication;
    }
}
