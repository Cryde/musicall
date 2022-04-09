<?php

namespace App\Model\Message;

use Symfony\Component\Validator\Constraints as Assert;

class MessageModel
{
    #[Assert\NotBlank]
    public ?string $content;

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): MessageModel
    {
        $this->content = $content;

        return $this;
    }
}
