<?php

declare(strict_types=1);

namespace App\Service\Procedure\Profile;

use App\ApiResource\Profile\ProfilePostCreate;
use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostImage;
use App\Entity\User;
use App\Service\Message\ChatMediaPreparer;
use App\Service\Musician\MediaMetadataFetcher;
use App\Service\Musician\MediaUrlParser;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Handler\UploadHandlerInterface;

readonly class CreateProfilePostProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChatMediaPreparer $mediaPreparer,
        private MediaUrlParser $mediaUrlParser,
        private MediaMetadataFetcher $metadataFetcher,
        private UploadHandlerInterface $uploadHandler,
    ) {
    }

    public function process(User $author, ProfilePostCreate $input): ProfilePost
    {
        $post = new ProfilePost();
        $post->author = $author;
        $content = trim($input->content);
        $post->content = $content === '' ? null : $content;
        $this->attachMediaLink($post, trim((string) $input->mediaUrl));

        $this->entityManager->persist($post);
        try {
            foreach ($input->photos as $photo) {
                $this->mediaPreparer->image($photo, fn (File $converted, string $mimeType, string $name): ProfilePostImage => $this->persistImage($post, $converted, $mimeType, $name));
            }
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            // Each photo is stored the moment it is persisted, so a post that never lands takes them back.
            foreach ($post->images as $image) {
                $this->uploadHandler->remove($image, 'uploadedFile');
            }
            throw $exception;
        }

        return $post;
    }

    private function attachMediaLink(ProfilePost $post, string $url): void
    {
        if ($url === '') {
            return;
        }
        $parsed = $this->mediaUrlParser->parse($url);
        // Already checked by SupportedMediaUrl.
        if (!$parsed instanceof ParsedMediaUrl) {
            return;
        }
        $post->mediaPlatform = $parsed->platform;
        $post->mediaUrl = $url;
        $post->mediaEmbedId = $parsed->embedId;
        $post->mediaTitle = $this->metadataFetcher->fetch($parsed->platform, $url, $parsed->embedId)->title;
    }

    private function persistImage(ProfilePost $post, File $converted, string $mimeType, string $name): ProfilePostImage
    {
        $image = new ProfilePostImage();
        $image->mimeType = $mimeType;
        $image->size = $converted->getSize();
        $image->originalName = $name;
        $image->uploadedFile = $converted;
        $post->addImage($image);
        // VichUploader stores the file on persist, while the converted copy still exists.
        $this->entityManager->persist($image);

        return $image;
    }
}
