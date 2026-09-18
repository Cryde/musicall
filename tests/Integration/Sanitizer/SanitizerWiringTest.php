<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sanitizer;

use App\Service\Builder\Comment\CommentBuilder;
use App\Service\Builder\Forum\ForumPostBuilder;
use App\Service\Builder\Publication\PublicationBuilder;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Each builder reaches its sanitizer through a #[Target] and nothing else, its parameter being named
 * $sanitizer so that it matches no autowiring alias. Drop that attribute and the argument resolves to
 * html_sanitizer.sanitizer.default, which allows no element at all, so the content silently loses its
 * markup. PublicationSanitizerTest cannot catch that: it asks the container for a sanitizer by id and
 * so never goes through a builder.
 */
#[ResetDatabase]
class SanitizerWiringTest extends KernelTestCase
{
    public function test_a_publication_keeps_the_markup_only_the_publication_sanitizer_allows(): void
    {
        $publication = PublicationFactory::createOne([
            'content' => '<h2>Titre</h2><p>Un <strong>mot</strong></p>',
            'cover' => PublicationCoverFactory::createOne(['imageName' => 'cover.jpg']),
            'thread' => CommentThreadFactory::createOne(),
        ]);

        /** @var PublicationBuilder $builder */
        $builder = static::getContainer()->get(PublicationBuilder::class);

        // h2 is the discriminator: app.forum_sanitizer drops it, the default drops every tag.
        $this->assertSame(
            '<h2>Titre</h2><p>Un <strong>mot</strong></p>',
            $builder->buildItem($publication)->content,
        );
    }

    public function test_a_forum_post_keeps_the_markup_only_the_forum_sanitizer_allows(): void
    {
        $post = ForumPostFactory::createOne([
            'content' => '<h2>Titre</h2><p>Un <a href="https://musicall.com">lien</a></p>',
        ]);

        /** @var ForumPostBuilder $builder */
        $builder = static::getContainer()->get(ForumPostBuilder::class);

        // The link survives where the default would drop it, and the h2 does not, where
        // app.publication_sanitizer would have kept it.
        $this->assertSame(
            '<p>Un <a href="https://musicall.com">lien</a></p>',
            $builder->buildItem($post)->content,
        );
    }

    public function test_a_comment_keeps_only_the_line_break_the_onlybr_sanitizer_allows(): void
    {
        $comment = CommentFactory::createOne([
            'content' => "<strong>gras</strong>Ligne 1\nLigne 2",
        ]);

        /** @var CommentBuilder $builder */
        $builder = static::getContainer()->get(CommentBuilder::class);

        // The br comes from the builder's own nl2br and survives where the default would drop it;
        // strong does not, where both rich sanitizers would have kept it. A disallowed element goes
        // with its text, so the word inside it is gone too, not just the tag.
        $this->assertSame(
            "Ligne 1<br />\nLigne 2",
            $builder->buildItem($comment)->content,
        );
    }
}
