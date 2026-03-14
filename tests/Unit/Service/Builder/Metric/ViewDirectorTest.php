<?php

namespace App\Tests\Unit\Service\Builder\Metric;
use App\Entity\Metric\ViewCache;
use App\Entity\User;
use App\Service\Builder\Metric\ViewDirector;
use PHPUnit\Framework\TestCase;

class ViewDirectorTest extends TestCase
{
    public function test_build(): void
    {
        $builder = new ViewDirector();

        $viewCache = new ViewCache();
        $viewCache->count = 10;
        $viewCache->id = 42;
        $user = new User();
        $user->username = "username_user";
        $result = $builder->build($viewCache, 'identifier', $user);

        $this->assertSame(42, $result->viewCache->id);
        $this->assertSame(10, $result->viewCache->count);
        $this->assertSame('username_user', $result->user->getUserIdentifier());
        $this->assertSame('username_user', $result->user->username);
        $this->assertSame('identifier', $result->identifier);
    }
}
