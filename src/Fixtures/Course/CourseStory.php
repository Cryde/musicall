<?php declare(strict_types=1);

namespace App\Fixtures\Course;

use App\Fixtures\Factory\Comment\CommentFactory;
use App\Fixtures\Factory\Comment\CommentThreadFactory;
use App\Fixtures\Factory\Publication\PublicationFactory;
use App\Fixtures\Publication\PublicationCategoryStory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class CourseStory extends Story
{
    public function build(): void
    {
        PublicationFactory::new()
            ->with(
                static function () {
                    $commentNumber = random_int(0, 5);
                    $thread = CommentThreadFactory::new(['commentNumber' => $commentNumber])->create();
                    CommentFactory::new([
                        'thread' => $thread,
                        'author' => UserStory::getRandom(UserStory::POOL_USERS),
                    ])->many($commentNumber)->create();

                    return [
                        'subCategory' => CourseCategoryStory::getRandom(CourseCategoryStory::COURSE_WRITEABLE_CATEGORIES),
                        'author'      => UserStory::getRandom(UserStory::POOL_USERS),
                        'thread'      => $thread,
                    ];
                }
            )
            ->many(200)
            ->applyStateMethod('asBaseTextPublicationOnline')->create();
    }
}
