<?php

declare(strict_types=1);

namespace App\Fixtures\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumTopic;
use App\Fixtures\Factory\Forum\ForumCategoryFactory;
use App\Fixtures\Factory\Forum\ForumFactory;
use App\Fixtures\Factory\Forum\ForumPostFactory;
use App\Fixtures\Factory\Forum\ForumSourceFactory;
use App\Fixtures\Factory\Forum\ForumTopicFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class ForumStory extends Story
{
    public const string FORUM_SOURCE = 'forum_source';
    public const string FORUM_CATEGORIES = 'forum_categories';
    public const string FORUMS = 'forums';
    public const string TOPICS = 'topics';

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
        $presentationForum = ForumFactory::new()->asPresentation($generalites)->create();
        $discussionForum = ForumFactory::new()->asDiscussionGenerale($generalites)->create();
        $this->addToPool(self::FORUMS, $presentationForum);
        $this->addToPool(self::FORUMS, $discussionForum);

        // Demande d'aide
        $theorieForum = ForumFactory::new()->asTheorieMusicale($demandeAide)->create();
        $infoForum = ForumFactory::new()->asInformatiqueMusicale($demandeAide)->create();
        $this->addToPool(self::FORUMS, $theorieForum);
        $this->addToPool(self::FORUMS, $infoForum);

        // Annonces et promotion
        $this->addToPool(self::FORUMS, ForumFactory::new()->asPromotion($annoncesPromotion)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asPetitesAnnonces($annoncesPromotion)->create());

        // Partage multimédia
        $this->addToPool(self::FORUMS, ForumFactory::new()->asVideos($partageMultimedia)->create());
        $this->addToPool(self::FORUMS, ForumFactory::new()->asAudio($partageMultimedia)->create());

        // Concernant le site
        $suggestionsForum = ForumFactory::new()->asSuggestions($concernantLeSite)->create();
        $this->addToPool(self::FORUMS, $suggestionsForum);
        $this->addToPool(self::FORUMS, ForumFactory::new()->asBugs($concernantLeSite)->create());

        // Create topics and posts for some forums
        $this->createTopicsForPresentationForum($presentationForum);
        $this->createTopicsForDiscussionForum($discussionForum);
        $this->createTopicsForTheorieForum($theorieForum);
        $this->createTopicsForSuggestionsForum($suggestionsForum);
    }

    /**
     * @param Proxy<Forum> $forum
     */
    private function createTopicsForPresentationForum(Proxy $forum): void
    {
        $users = UserStory::getPool(UserStory::POOL_USERS);

        // Pinned welcome topic with many replies
        $welcomeTopic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Bienvenue sur MusicAll ! Présentez-vous ici',
            'slug' => 'bienvenue-sur-musicall-presentez-vous-ici',
            'author' => UserStory::get(UserStory::ADMIN_USER),
            'creationDatetime' => new \DateTime('-6 months'),
        ])->asPinned()->create();

        $this->createPostsForTopic($welcomeTopic, 15);
        $this->addToPool(self::TOPICS, $welcomeTopic);

        // Regular presentation topics
        $presentationTopics = [
            ['title' => 'Salut à tous ! Guitariste depuis 10 ans', 'slug' => 'salut-a-tous-guitariste-depuis-10-ans'],
            ['title' => 'Nouveau batteur dans la place !', 'slug' => 'nouveau-batteur-dans-la-place'],
            ['title' => 'Bassiste amateur cherche groupe', 'slug' => 'bassiste-amateur-cherche-groupe'],
            ['title' => 'Hello, pianiste classique ici', 'slug' => 'hello-pianiste-classique-ici'],
        ];

        foreach ($presentationTopics as $index => $topicData) {
            $topic = ForumTopicFactory::new([
                'forum' => $forum,
                'title' => $topicData['title'],
                'slug' => $topicData['slug'],
                'author' => $users[array_rand($users)],
                'creationDatetime' => new \DateTime('-' . (5 - $index) . ' months'),
            ])->create();

            // Add posts to some topics only
            if ($index < 2) {
                $this->createPostsForTopic($topic, rand(3, 8));
            }

            $this->addToPool(self::TOPICS, $topic);
        }

        $this->updateForumCounts($forum);
    }

    /**
     * @param Proxy<Forum> $forum
     */
    private function createTopicsForDiscussionForum(Proxy $forum): void
    {
        $users = UserStory::getPool(UserStory::POOL_USERS);

        $discussionTopics = [
            ['title' => 'Quel est votre morceau préféré du moment ?', 'slug' => 'quel-est-votre-morceau-prefere-du-moment', 'posts' => 12],
            ['title' => 'Les concerts à ne pas manquer en 2024', 'slug' => 'les-concerts-a-ne-pas-manquer-2024', 'posts' => 8],
            ['title' => 'Débat : Vinyle vs Streaming', 'slug' => 'debat-vinyle-vs-streaming', 'posts' => 20],
            ['title' => 'Vos influences musicales', 'slug' => 'vos-influences-musicales', 'posts' => 0],
            ['title' => 'Comment gérez-vous le trac sur scène ?', 'slug' => 'comment-gerez-vous-le-trac-sur-scene', 'posts' => 6],
        ];

        foreach ($discussionTopics as $index => $topicData) {
            $topic = ForumTopicFactory::new([
                'forum' => $forum,
                'title' => $topicData['title'],
                'slug' => $topicData['slug'],
                'author' => $users[array_rand($users)],
                'creationDatetime' => new \DateTime('-' . rand(1, 120) . ' days'),
            ])->create();

            if ($topicData['posts'] > 0) {
                $this->createPostsForTopic($topic, $topicData['posts']);
            }

            $this->addToPool(self::TOPICS, $topic);
        }

        $this->updateForumCounts($forum);
    }

    /**
     * @param Proxy<Forum> $forum
     */
    private function createTopicsForTheorieForum(Proxy $forum): void
    {
        $users = UserStory::getPool(UserStory::POOL_USERS);

        // Pinned FAQ topic
        $faqTopic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => '[FAQ] Questions fréquentes sur la théorie musicale',
            'slug' => 'faq-questions-frequentes-theorie-musicale',
            'author' => UserStory::get(UserStory::ADMIN_USER),
            'creationDatetime' => new \DateTime('-1 year'),
        ])->asPinned()->create();

        $this->addToPool(self::TOPICS, $faqTopic);

        $theorieTopics = [
            ['title' => 'Comment construire des accords de 7ème ?', 'slug' => 'comment-construire-accords-7eme', 'posts' => 5],
            ['title' => 'Gamme pentatonique : par où commencer ?', 'slug' => 'gamme-pentatonique-par-ou-commencer', 'posts' => 7],
            ['title' => 'Comprendre les modes grecs', 'slug' => 'comprendre-les-modes-grecs', 'posts' => 0],
            ['title' => 'Aide pour analyser une partition', 'slug' => 'aide-pour-analyser-partition', 'posts' => 3],
        ];

        foreach ($theorieTopics as $topicData) {
            $topic = ForumTopicFactory::new([
                'forum' => $forum,
                'title' => $topicData['title'],
                'slug' => $topicData['slug'],
                'author' => $users[array_rand($users)],
                'creationDatetime' => new \DateTime('-' . rand(1, 90) . ' days'),
            ])->create();

            if ($topicData['posts'] > 0) {
                $this->createPostsForTopic($topic, $topicData['posts']);
            }

            $this->addToPool(self::TOPICS, $topic);
        }

        $this->updateForumCounts($forum);
    }

    /**
     * @param Proxy<Forum> $forum
     */
    private function createTopicsForSuggestionsForum(Proxy $forum): void
    {
        $users = UserStory::getPool(UserStory::POOL_USERS);

        $suggestionTopics = [
            ['title' => 'Suggestion : Mode sombre pour le site', 'slug' => 'suggestion-mode-sombre-site', 'posts' => 4, 'locked' => true],
            ['title' => 'Idée : Système de messagerie privée', 'slug' => 'idee-systeme-messagerie-privee', 'posts' => 2, 'locked' => false],
            ['title' => 'Proposition : Calendrier des événements', 'slug' => 'proposition-calendrier-evenements', 'posts' => 0, 'locked' => false],
        ];

        foreach ($suggestionTopics as $topicData) {
            $factory = ForumTopicFactory::new([
                'forum' => $forum,
                'title' => $topicData['title'],
                'slug' => $topicData['slug'],
                'author' => $users[array_rand($users)],
                'creationDatetime' => new \DateTime('-' . rand(1, 60) . ' days'),
            ]);

            if ($topicData['locked']) {
                $factory = $factory->asLocked();
            }

            $topic = $factory->create();

            if ($topicData['posts'] > 0) {
                $this->createPostsForTopic($topic, $topicData['posts']);
            }

            $this->addToPool(self::TOPICS, $topic);
        }

        $this->updateForumCounts($forum);
    }

    /**
     * @param Proxy<ForumTopic> $topic
     */
    private function createPostsForTopic(Proxy $topic, int $count): void
    {
        $users = UserStory::getPool(UserStory::POOL_USERS);
        $topicCreationDate = \DateTime::createFromInterface($topic->getCreationDatetime());
        $lastPost = null;

        for ($i = 0; $i < $count; $i++) {
            $daysAfterTopic = $i * rand(1, 5);
            $postDate = (clone $topicCreationDate)->modify('+' . $daysAfterTopic . ' days');

            // Ensure post date is not in the future
            if ($postDate > new \DateTime()) {
                $postDate = new \DateTime('-' . rand(0, 30) . ' days');
            }

            $post = ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $users[array_rand($users)],
                'creationDatetime' => $postDate,
            ])->create();

            $lastPost = $post;
        }

        // Update topic with last post and post count
        $topic->_real()->setPostNumber($count);
        if ($lastPost) {
            $topic->_real()->setLastPost($lastPost->_real());
        }
        $topic->_save();
    }

    /**
     * @param Proxy<Forum> $forum
     */
    private function updateForumCounts(Proxy $forum): void
    {
        $topicCount = 0;
        $postCount = 0;

        foreach ($this->getPool(self::TOPICS) as $topic) {
            if ($topic->getForum()->getId() === $forum->getId()) {
                $topicCount++;
                $postCount += $topic->getPostNumber();
            }
        }

        $forum->_real()->setTopicNumber($topicCount);
        $forum->_real()->setPostNumber($postCount);
        $forum->_save();
    }
}
