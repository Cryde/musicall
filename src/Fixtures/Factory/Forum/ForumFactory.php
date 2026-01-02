<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumCategory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<Forum>
 */
final class ForumFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'description' => self::faker()->text(300),
            'forumCategory' => ForumCategoryFactory::new(),
            'position' => self::faker()->randomNumber(),
            'postNumber' => 0,
            'slug' => self::faker()->slug(),
            'title' => self::faker()->text(150),
            'topicNumber' => 0,
            'updateDatetime' => null,
        ];
    }

    /**
     * @param Proxy<ForumCategory>|ForumCategory $forumCategory
     */
    public function withForumCategory(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with(['forumCategory' => $forumCategory]);
    }

    // Généralités forums
    public function asPresentation(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Présentation',
            'description' => 'Présentez-vous !',
            'slug' => 'presentation',
            'position' => 1,
        ]);
    }

    public function asDiscussionGenerale(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Discussion générale',
            'description' => 'Tous les sujets !',
            'slug' => 'discussion-generale',
            'position' => 2,
        ]);
    }

    // Demande d'aide forums
    public function asTheorieMusicale(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Théorie musicale',
            'description' => 'Solfège, harmonie, etc.',
            'slug' => 'theorie-musicale',
            'position' => 1,
        ]);
    }

    public function asInformatiqueMusicale(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Informatique musicale',
            'description' => 'MAO, logiciels, etc.',
            'slug' => 'informatique-musicale',
            'position' => 2,
        ]);
    }

    // Annonces et promotion forums
    public function asPromotion(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Promotion',
            'description' => 'Faites votre promotion !',
            'slug' => 'promotion',
            'position' => 1,
        ]);
    }

    public function asPetitesAnnonces(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Petites annonces',
            'description' => 'Achats, ventes, échanges',
            'slug' => 'petites-annonces',
            'position' => 2,
        ]);
    }

    // Partage multimédia forums
    public function asVideos(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Vidéos',
            'description' => 'Partagez vos vidéos',
            'slug' => 'videos',
            'position' => 1,
        ]);
    }

    public function asAudio(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Audio',
            'description' => 'Partagez vos morceaux',
            'slug' => 'audio',
            'position' => 2,
        ]);
    }

    // Concernant le site forums
    public function asSuggestions(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Suggestions',
            'description' => 'Vos idées pour le site',
            'slug' => 'suggestions',
            'position' => 1,
        ]);
    }

    public function asBugs(Proxy|ForumCategory $forumCategory): self
    {
        return $this->with([
            'forumCategory' => $forumCategory,
            'title' => 'Bugs',
            'description' => 'Signalement de bugs',
            'slug' => 'bugs',
            'position' => 2,
        ]);
    }

    public static function class(): string
    {
        return Forum::class;
    }
}
