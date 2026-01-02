<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Bot\BotMetaDataProviderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class CourseMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private PublicationRepository            $publicationRepository,
        private PublicationSubCategoryRepository $publicationSubCategoryRepository,
        private UploaderHelper                   $uploaderHelper,
        private CacheManager                     $cacheManager,
    ) {
    }

    public function supports(string $uri): bool
    {
        return $uri === '/cours'
            || $uri === '/cours/'
            || str_starts_with($uri, '/cours/');
    }

    public function getMetaData(string $uri): array
    {
        if (preg_match('#^/cours/category/(.+)$#', $uri, $matches)) {
            return $this->getForCategory($matches[1]);
        }

        if (preg_match('#^/cours/(.+)$#', $uri, $matches)) {
            return $this->getForCourse($matches[1]);
        }

        return $this->getForBase();
    }

    private function getForBase(): array
    {
        return [
            'title' => 'Cours de musique - MusicAll',
            'description' => 'Apprenez la musique avec nos cours gratuits : guitare, basse, batterie, piano, chant et bien plus encore.',
        ];
    }

    private function getForCategory(string $slug): array
    {
        $category = $this->publicationSubCategoryRepository->findOneBy([
            'slug' => $slug,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);

        if (!$category) {
            return $this->getForBase();
        }

        return [
            'title' => $category->getTitle() . ' - Cours - MusicAll',
            'description' => 'Retrouvez tous les cours de ' . $category->getTitle() . ' sur MusicAll.',
        ];
    }

    private function getForCourse(string $slug): array
    {
        $publication = $this->publicationRepository->findOneBy([
            'slug' => $slug,
            'status' => Publication::STATUS_ONLINE,
        ]);

        if (!$publication) {
            return [];
        }

        $cover = null;
        if ($publication->getCover()) {
            $path = $this->uploaderHelper->asset($publication->getCover(), 'imageFile');
            $cover = $this->cacheManager->getBrowserPath($path, 'publication_image_filter');
        }

        return [
            'title' => $publication->getTitle(),
            'description' => $publication->getShortDescription(),
            'cover' => $cover,
        ];
    }
}
