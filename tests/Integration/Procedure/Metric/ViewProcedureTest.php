<?php

declare(strict_types=1);

namespace App\Tests\Integration\Procedure\Metric;

use App\Entity\Metric\View;
use App\Repository\Metric\ViewRepository;
use App\Service\Procedure\Metric\ViewProcedure;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ViewProcedureTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private ViewProcedure $viewProcedure;
    private ViewRepository $viewRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $this->viewProcedure = self::getContainer()->get(ViewProcedure::class);
        $this->viewRepository = self::getContainer()->get(ViewRepository::class);
    }

    public function test_first_view_creates_view_cache_and_increments_count(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->assertNull($publication->getViewCache());

        $this->viewProcedure->process($publication, $request);

        $this->assertNotNull($publication->getViewCache());
        $this->assertSame(1, $publication->getViewCache()->getCount());
    }

    public function test_view_stores_entity_type_and_entity_id(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->viewProcedure->process($publication, $request);

        $views = $this->viewRepository->findBy(['viewCache' => $publication->getViewCache()]);
        $this->assertCount(1, $views);

        $view = $views[0];
        $this->assertSame('publication', $view->getEntityType());
        $this->assertSame((string) $publication->getId(), $view->getEntityId());
    }

    public function test_anonymous_duplicate_view_within_24_hours_is_not_counted(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->viewProcedure->process($publication, $request);
        $this->assertSame(1, $publication->getViewCache()->getCount());

        // Same IP within 24 hours should not increment
        $this->viewProcedure->process($publication, $request);
        $this->assertSame(1, $publication->getViewCache()->getCount());
    }

    public function test_anonymous_view_from_different_ip_is_counted(): void
    {
        $publication = PublicationFactory::new()->create()->_real();

        $this->viewProcedure->process($publication, $this->createRequest('192.168.1.1'));
        $this->assertSame(1, $publication->getViewCache()->getCount());

        // Different IP should increment
        $this->viewProcedure->process($publication, $this->createRequest('192.168.1.2'));
        $this->assertSame(2, $publication->getViewCache()->getCount());
    }

    public function test_logged_in_user_duplicate_view_within_24_hours_is_not_counted(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->viewProcedure->process($publication, $request, $user);
        $this->assertSame(1, $publication->getViewCache()->getCount());

        // Same user within 24 hours should not increment
        $this->viewProcedure->process($publication, $request, $user);
        $this->assertSame(1, $publication->getViewCache()->getCount());
    }

    public function test_different_logged_in_users_are_counted_separately(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();
        $user2 = UserFactory::new()->asAdminUser()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->viewProcedure->process($publication, $request, $user1);
        $this->assertSame(1, $publication->getViewCache()->getCount());

        // Different user should increment
        $this->viewProcedure->process($publication, $request, $user2);
        $this->assertSame(2, $publication->getViewCache()->getCount());
    }

    public function test_logged_in_user_view_after_24_hours_is_counted(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->viewProcedure->process($publication, $request, $user);
        $this->assertSame(1, $publication->getViewCache()->getCount());

        // Manually backdate the view to simulate 25 hours ago
        $views = $this->viewRepository->findBy(['viewCache' => $publication->getViewCache()]);
        $this->assertCount(1, $views);

        $views[0]->setCreationDatetime(new \DateTime('25 hours ago'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        // Same user after 24 hours should increment
        $this->viewProcedure->process($publication, $request, $user);
        $this->assertSame(2, $publication->getViewCache()->getCount());
    }

    public function test_anonymous_view_after_24_hours_is_counted(): void
    {
        $publication = PublicationFactory::new()->create()->_real();
        $request = $this->createRequest('192.168.1.1');

        $this->viewProcedure->process($publication, $request);
        $this->assertSame(1, $publication->getViewCache()->getCount());

        // Manually backdate the view to simulate 25 hours ago
        $views = $this->viewRepository->findBy(['viewCache' => $publication->getViewCache()]);
        $this->assertCount(1, $views);

        $views[0]->setCreationDatetime(new \DateTime('25 hours ago'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        // Same IP after 24 hours should increment
        $this->viewProcedure->process($publication, $request);
        $this->assertSame(2, $publication->getViewCache()->getCount());
    }

    private function createRequest(string $clientIp): Request
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', $clientIp);

        return $request;
    }
}
