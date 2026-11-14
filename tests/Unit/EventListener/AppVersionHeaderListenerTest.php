<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\AppVersionHeaderListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The deployed build named on responses, so a stale tab learns of a deploy (#1105).
 */
class AppVersionHeaderListenerTest extends TestCase
{
    private string $versionFile;

    protected function setUp(): void
    {
        $this->versionFile = sys_get_temp_dir() . '/musicall-version-' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->versionFile)) {
            unlink($this->versionFile);
        }
    }

    public function test_the_deployed_build_is_named_on_the_response(): void
    {
        file_put_contents($this->versionFile, '{"version":"20261114153012345"}');

        $response = $this->respond(new AppVersionHeaderListener($this->versionFile));

        self::assertSame('20261114153012345', $response->headers->get('X-App-Version'));
    }

    public function test_no_build_means_no_header(): void
    {
        $response = $this->respond(new AppVersionHeaderListener($this->versionFile));

        self::assertFalse($response->headers->has('X-App-Version'));
    }

    public function test_a_file_that_does_not_name_a_version_means_no_header(): void
    {
        foreach (['not json', '[]', '{"version":12}', '{"version":""}', "{\"version\":\"1\\r\\nSet-Cookie: x=1\"}"] as $content) {
            file_put_contents($this->versionFile, $content);

            $response = $this->respond(new AppVersionHeaderListener($this->versionFile));

            self::assertFalse($response->headers->has('X-App-Version'), $content);
        }
    }

    public function test_the_file_is_read_once_per_process(): void
    {
        file_put_contents($this->versionFile, '{"version":"first"}');
        $listener = new AppVersionHeaderListener($this->versionFile);
        $this->respond($listener);
        file_put_contents($this->versionFile, '{"version":"second"}');

        $response = $this->respond($listener);

        self::assertSame('first', $response->headers->get('X-App-Version'));
    }

    public function test_a_sub_request_is_left_alone(): void
    {
        file_put_contents($this->versionFile, '{"version":"20261114153012345"}');

        $response = $this->respond(new AppVersionHeaderListener($this->versionFile), HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('X-App-Version'));
    }

    private function respond(AppVersionHeaderListener $listener, int $requestType = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response = new Response();
        $listener(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/users/self'),
            $requestType,
            $response,
        ));

        return $response;
    }
}
