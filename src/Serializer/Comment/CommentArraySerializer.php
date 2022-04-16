<?php

namespace App\Serializer\Comment;

use App\Entity\Comment\Comment;
use App\Serializer\User\UserArraySerializer;
use Doctrine\Common\Collections\Collection;
use HtmlSanitizer\SanitizerInterface;

class CommentArraySerializer
{
    private UserArraySerializer $userArraySerializer;
    private SanitizerInterface $sanitizer;

    public function __construct(UserArraySerializer $userArraySerializer, SanitizerInterface $onlyBr)
    {
        $this->userArraySerializer = $userArraySerializer;
        $this->sanitizer = $onlyBr;
    }

    /**
     * @param Comment[] $comments
     */
    public function listToArray(iterable $comments): array
    {
        $list = [];
        foreach ($comments as $comment) {
            $list[] = $this->toArray($comment);
        }

        return $list;
    }

    public function toArray(Comment $comment): array
    {
        return [
            'id'                => $comment->getId(),
            'author'            => $this->userArraySerializer->toArray($comment->getAuthor()),
            'content'           => $this->sanitizer->sanitize(nl2br($comment->getContent())),
            'creation_datetime' => $comment->getCreationDatetime(),
        ];
    }
}
