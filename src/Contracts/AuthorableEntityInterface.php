<?php

namespace App\Contracts;

use App\Entity\User;

interface AuthorableEntityInterface
{
    public function getAuthor(): User;
    public function setAuthor(User $user): static;
}