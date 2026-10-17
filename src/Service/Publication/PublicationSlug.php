<?php declare(strict_types=1);

namespace App\Service\Publication;

use App\Repository\PublicationRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

class PublicationSlug
{
    // A draft's slug ends in a dash, so the plain one stays free for the version approval publishes.
    private const string DRAFT_SUFFIX = '-';

    public function __construct(private readonly PublicationRepository $publicationRepository, private readonly SluggerInterface $slugger)
    {
    }

    public function create(string $slugCandidate): string
    {
        return $this->firstFree((string) $this->slugger->slug($slugCandidate)->lower(), '');
    }

    public function createForDraft(string $title): string
    {
        return $this->firstFree((string) $this->slugger->slug($title)->lower(), self::DRAFT_SUFFIX);
    }

    private function firstFree(string $initialSlug, string $suffix): string
    {
        $slug = $initialSlug . $suffix;
        $i = 1;
        while ($this->publicationRepository->count(['slug' => $slug]) > 0) {
            $slug = $initialSlug . '-' . $i++ . $suffix;
        }

        return $slug;
    }
}
