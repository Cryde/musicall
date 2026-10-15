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

    private function publicationBy(string $locale): Publication
    {
        $author = new User();
        $author->locale = $locale;
        $publication = new Publication();
        $publication->author = $author;

        return $publication;
    }
}
