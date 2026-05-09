<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Forum;

use App\Entity\Forum\ForumCategory;
use App\Entity\Forum\ForumSource;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<ForumCategory>
 */
final class ForumCategoryFactory extends PersistentObjectFactory
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
     * @param ForumSource $forumSource
     */
    public function withForumSource(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource]);
    }

    /**
     * @param ForumSource $forumSource
     */
    public function asGeneralites(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Généralités', 'position' => 1]);
    }

    /**
     * @param ForumSource $forumSource
     */
    public function asDemandeAide(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => "Demande d'aide", 'position' => 2]);
    }

    /**
     * @param ForumSource $forumSource
     */
    public function asAnnoncesPromotion(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Annonces et promotion', 'position' => 3]);
    }

    /**
     * @param ForumSource $forumSource
     */
    public function asPartageMultimedia(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Partage multimédia', 'position' => 4]);
    }

    /**
     * @param ForumSource $forumSource
     */
    public function asConcernantLeSite(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Concernant le site', 'position' => 5]);
    }

    public static function class(): string
    {
        return ForumCategory::class;
    }
}
