<?php
declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\Constraint\ArraySubset;
use Symfony\Component\HttpFoundation\Response;

trait ApiTestAssertionsTrait
{
    /**
     * Asserts that the retrieved JSON is equal to $json.
     */
    public function assertJsonEquals(array|string $json, string $message = ''): void
    {
        if (\is_string($json)) {
            $json = json_decode($json, true);
        }
        if (!\is_array($json)) {
            throw new \InvalidArgumentException('$json must be array or string (JSON array or JSON object)');
        }
        static::assertEquals($json, $this->getResponseAsArray(), $message);
    }

    public function assertJsonContains($subset, bool $checkForObjectIdentity = true, string $message = ''): void
    {
        if (\is_string($subset)) {
            $subset = json_decode($subset, true);
        }
        if (!\is_array($subset)) {
            throw new \InvalidArgumentException('$subset must be array or string (JSON array or JSON object)');
        }

        static::assertArraySubset($subset, $this->getResponseAsArray(), $checkForObjectIdentity, $message);
    }

    public static function assertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        $constraint = new ArraySubset($subset, $checkForObjectIdentity);

        static::assertThat($array, $constraint, $message);
    }

    private function getResponseAsArray()
    {
        return json_decode($this->getHttpResponse()->getContent(), true);
    }

    private function getHttpResponse(): Response
    {
        if (!$response = $this->client->getResponse()) {
            static::fail('A client must have an HTTP Response to make assertions. Did you forget to make an HTTP request?');
        }

        return $response;
    }
}