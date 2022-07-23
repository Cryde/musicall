<?php

namespace App\Serializer\Comment;

use App\Entity\Comment\CommentThread;
use App\Service\DatetimeHelper;

class ThreadArraySerializer
{
    public function __construct(private readonly CommentArraySerializer $commentArraySerializer)
    {
    }

    public function toArray(CommentThread $commentThread): array
    {
        return [
            'comment_number'    => $commentThread->getCommentNumber(),
            'is_active'         => $commentThread->getIsActive(),
            'creation_datetime' => $commentThread->getCreationDatetime()->format(DatetimeHelper::FORMAT_ISO_8601),
            'comments'          => $this->commentArraySerializer->listToArray($commentThread->getComments()),
        ];
    }
}
