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

    public function withForumSource(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource]);
    }

    public function asGeneralites(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Généralités', 'position' => 1]);
    }

    public function asDemandeAide(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => "Demande d'aide", 'position' => 2]);
    }

    public function asAnnoncesPromotion(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Annonces et promotion', 'position' => 3]);
    }

    public function asPartageMultimedia(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Partage multimédia', 'position' => 4]);
    }

    public function asConcernantLeSite(ForumSource $forumSource): self
    {
        return $this->with(['forumSource' => $forumSource, 'title' => 'Concernant le site', 'position' => 5]);
    }

    public static function class(): string
    {
        return ForumCategory::class;
    }
}
