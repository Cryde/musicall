<?php declare(strict_types=1);

namespace App\Fixtures\Forum;

use App\Fixtures\Factory\Forum\ForumCategoryFactory;
use App\Fixtures\Factory\Forum\ForumFactory;
use App\Fixtures\Factory\Forum\ForumSourceFactory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class ForumStory extends Story
{
    public const string FORUM_SOURCE = 'forum_source';
    public const string FORUM_CATEGORIES = 'forum_categories';
    public const string FORUMS = 'forums';

    public function build(): void
    {
        // Create forum source
        $rootSource = ForumSourceFactory::new()->asRoot()->create();
        $this->addState(self::FORUM_SOURCE, $rootSource);

        // Create categories
        $generalites = ForumCategoryFactory::new()->asGeneralites($rootSource)->create();
        $demandeAide = ForumCategoryFactory::new()->asDemandeAide($rootSource)->create();
        $annoncesPromotion = ForumCategoryFactory::new()->asAnnoncesPromotion($rootSource)->create();
        $partageMultimedia = ForumCategoryFactory::new()->asPartageMultimedia($rootSource)->create();
        $concernantLeSite = ForumCategoryFactory::new()->asConcernantLeSite($rootSource)->create();

        $this->addToPool(self::FORUM_CATEGORIES, $generalites);
        $this->addToPool(self::FORUM_CATEGORIES, $demandeAide);
        $this->addToPool(self::FORUM_CATEGORIES, $annoncesPromotion);
        $this->addToPool(self::FORUM_CATEGORIES, $partageMultimedia);
        $this->addToPool(self::FORUM_CATEGORIES, $concernantLeSite);

        // Create forums for each category
        // Généralités
        $this->addToPool(self::FORUMS, ForumFactory::new()->asPresentation($generalites)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asDiscussionGenerale($generalites)->create());

        // Demande d'aide
        $this->addToPool(self::FORUMS, ForumFactory::new()->asTheorieMusicale($demandeAide)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asInformatiqueMusicale($demandeAide)->create());

        // Annonces et promotion
        $this->addToPool(self::FORUMS, ForumFactory::new()->asPromotion($annoncesPromotion)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asPetitesAnnonces($annoncesPromotion)->create());

        // Partage multimédia
        $this->addToPool(self::FORUMS, ForumFactory::new()->asVideos($partageMultimedia)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asAudio($partageMultimedia)->create());

        // Concernant le site
        $this->addToPool(self::FORUMS, ForumFactory::new()->asSuggestions($concernantLeSite)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asBugs($concernantLeSite)->create());
    }
}
