<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceFolderActivityType: string
{
    case FolderCreated = 'folder_created';
    case FolderRenamed = 'folder_renamed';
    case FolderMoved = 'folder_moved';
    case FolderArchived = 'folder_archived';
}
