<?php declare(strict_types=1);

namespace App\Service\Builder;

use App\ApiResource\Publication\Video\AddVideo;
use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Google\YoutubeUrlHelper;
use App\Service\Publication\PublicationSlug;

class PublicationDirector
{
    public function __construct(
        private readonly YoutubeUrlHelper                 $youtubeUrlHelper,
        private readonly PublicationSubCategoryRepository $publicationSubCategoryRepository,
        private readonly PublicationSlug                  $publicationSlug
    ) {
    }

    /** A text, in its author's language until they pick another one in the editor. */
    public function buildTextDraft(string $title, PublicationSubCategory $category, User $author): Publication
    {
        $publication = new Publication();
        $publication->title = $title;
        $publication->subCategory = $category;
        $publication->author = $author;
        $publication->slug = $this->publicationSlug->createForDraft($title);
        $publication->status = Publication::STATUS_DRAFT;
        $publication->assignType(Publication::TYPE_TEXT);

        return $publication;
    }

    public function buildVideo(AddVideo $addVideo, User $user): Publication
    {
        $category = $addVideo->category;
        if ($category && $category->type !== PublicationSubCategory::TYPE_COURSE) {
            $category = null;
        }
        if (!$category instanceof \App\Entity\PublicationSubCategory) {
            $category = $this->publicationSubCategoryRepository->findOneBy(['slug' => 'decouvertes']);
        }
        assert($category !== null);

        $publication = new Publication();
        $publication->title = $addVideo->title;
        $publication->status = Publication::STATUS_ONLINE;
        $publication->shortDescription = $addVideo->description;
        $publication->content = $this->youtubeUrlHelper->getVideoId($addVideo->url);
        $publication->subCategory = $category;
        $publication->author = $user;
        $publication->assignType(Publication::TYPE_VIDEO);
        $publication->publicationDatetime = new \DateTime();
        $publication->slug = $this->publicationSlug->create('v-' . $addVideo->title);

        return $publication;
    }
}
