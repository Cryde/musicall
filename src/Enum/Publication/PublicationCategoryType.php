<?php declare(strict_types=1);

namespace App\Enum\Publication;
enum PublicationCategoryType: string
{
    case Gallery = 'gallery';
    case Course = 'course';
    case Publication = 'publication';
}
