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

        $viewCache = (new ViewCache())->setCount(10)->setId(42);
        $user = (new User())->setUsername("username_user");
        $result = $builder->build($viewCache, 'identifier', $user);

        $this->assertSame(42, $result->getViewCache()->getId());
        $this->assertSame(10, $result->getViewCache()->getCount());
        $this->assertSame('username_user', $result->getUser()->getUserIdentifier());
        $this->assertSame('username_user', $result->getUser()->getUsername());
        $this->assertSame('identifier', $result->getIdentifier());
    }
}