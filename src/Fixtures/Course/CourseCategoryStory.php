<?php declare(strict_types=1);

namespace App\Fixtures\Course;

use App\Fixtures\Factory\Course\CourseCategoryFactory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class CourseCategoryStory extends Story
{
    const string COURSE_WRITEABLE_CATEGORIES = 'course_writeable_categories';

    public function build(): void
    {
        $this->addToPool(self::COURSE_WRITEABLE_CATEGORIES, CourseCategoryFactory::new()->asGuitare());
        $this->addToPool(self::COURSE_WRITEABLE_CATEGORIES, CourseCategoryFactory::new()->asBasse());
        $this->addToPool(self::COURSE_WRITEABLE_CATEGORIES, CourseCategoryFactory::new()->asBatterie());
        $this->addToPool(self::COURSE_WRITEABLE_CATEGORIES, CourseCategoryFactory::new()->asMAO());
        $this->addToPool(self::COURSE_WRITEABLE_CATEGORIES, CourseCategoryFactory::new()->asDivers());
    }
}
