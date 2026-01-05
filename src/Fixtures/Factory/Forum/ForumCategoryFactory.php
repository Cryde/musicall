<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Forum;

use App\Entity\Forum\ForumCategory;
use App\Entity\Forum\ForumSource;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<ForumCategory>
 */
final class ForumCategoryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'forumSource' => ForumSourceFactory::new()->asRoot(),
            'position' => self::faker()->randomNumber(),
            'title' => self::faker()->text(255),
        ];
    }

    /**
     * @param Proxy<ForumSource>|ForumSource $forumSource
     */
    public function withForumSource(Proxy|ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource]);
    }

    /**
     * @param Proxy<ForumSource>|ForumSource $forumSource
     */
    public function asGeneralites(Proxy|ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Généralités', 'position' => 1]);
    }

    /**
     * @param Proxy<ForumSource>|ForumSource $forumSource
     */
    public function asDemandeAide(Proxy|ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => "Demande d'aide", 'position' => 2]);
    }

    /**
     * @param Proxy<ForumSource>|ForumSource $forumSource
     */
    public function asAnnoncesPromotion(Proxy|ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Annonces et promotion', 'position' => 3]);
    }

    /**
     * @param Proxy<ForumSource>|ForumSource $forumSource
     */
    public function asPartageMultimedia(Proxy|ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Partage multimédia', 'position' => 4]);
    }

    /**
     * @param Proxy<ForumSource>|ForumSource $forumSource
     */
    public function asConcernantLeSite(Proxy|ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Concernant le site', 'position' => 5]);
    }

    public static function class(): string
    {
        return ForumCategory::class;
    }
}
