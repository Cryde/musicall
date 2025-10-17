<?php declare(strict_types=1);

namespace App\Fixtures\Publication;

use App\Fixtures\Factory\Publication\PublicationSubCategoryFactory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class PublicationCategoryStory extends Story
{
    const string WRITEABLE_CATEGORIES = 'writeable_categories';
    const string CATEGORY_NEWS = 'category_news';
    const string CATEGORY_ARTICLE = 'category_article';
    const string CATEGORY_CHRONIQUE = 'category_chronique';
    const string CATEGORY_DECOUVERTE = 'category_decouverte';
    const string CATEGORY_INTERVIEW = 'category_interview';
    const string CATEGORY_LIVEREPORT = 'category_livereport';

    public function build(): void
    {
        $this->addState(self::CATEGORY_DECOUVERTE, PublicationSubCategoryFactory::new()->asDecouvertes());
        $this->addState(self::CATEGORY_NEWS, PublicationSubCategoryFactory::new()->asNews(), self::WRITEABLE_CATEGORIES);
        $this->addState(self::CATEGORY_ARTICLE, PublicationSubCategoryFactory::new()->asArticle(), self::WRITEABLE_CATEGORIES);
        $this->addState(self::CATEGORY_CHRONIQUE, PublicationSubCategoryFactory::new()->asChronique(), self::WRITEABLE_CATEGORIES);
        $this->addState(self::CATEGORY_INTERVIEW, PublicationSubCategoryFactory::new()->asInterview(), self::WRITEABLE_CATEGORIES);
        $this->addState(self::CATEGORY_LIVEREPORT, PublicationSubCategoryFactory::new()->asLiveReports(), self::WRITEABLE_CATEGORIES);
    }
}
