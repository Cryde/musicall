<?php

namespace App\ApiResource\Publication\Video;

use ApiPlatform\Metadata\Post;
use App\Entity\PublicationSubCategory;
use App\Processor\Publication\VideoPostProcessor;
use App\Validator\Publication\AlreadyExistingVideo;
use App\Validator\Publication\UrlVideo;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/publications/video/add',
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    name: 'api_publication_video_add',
    processor: VideoPostProcessor::class,
)]
class AddVideo
{
    #[Assert\Sequentially([
        new Assert\NotBlank,
        new Assert\Url,
        new UrlVideo,
        new AlreadyExistingVideo,
    ])]
    public string $url;
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    public string $title;
    #[Assert\NotBlank]
    #[Assert\Length(min: 20)]
    public string $description;
    public ?PublicationSubCategory $category = null;
}