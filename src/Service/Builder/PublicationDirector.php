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

    public function buildVideo(AddVideo $addVideo, User $user): Publication
    {
        $category = $addVideo->category;
        if ($category && $category->type !== PublicationSubCategory::TYPE_COURSE) {
            $category = null;
        }
        if (!$category) {
            $category = $this->publicationSubCategoryRepository->findOneBy(['slug' => 'decouvertes']);
        }
        assert($category !== null);

        $publication = new Publication();
        $publication->title = $addVideo->title;
        $publication->type = Publication::TYPE_VIDEO;
        $publication->status = Publication::STATUS_ONLINE;
        $publication->shortDescription = $addVideo->description;
        $publication->content = $this->youtubeUrlHelper->getVideoId($addVideo->url);
        $publication->subCategory = $category;
        $publication->author = $user;
        $publication->publicationDatetime = new \DateTime();
        $publication->slug = $this->publicationSlug->create('v-' . $addVideo->title);

        return $publication;
    }
}
