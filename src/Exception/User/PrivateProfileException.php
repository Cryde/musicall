<?php declare(strict_types=1);

namespace App\Exception\User;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A profile its member made private. Still a 404, which the Android app reads as « hidden », but with its own
 * problem type so the web can tell it from a profile that does not exist.
 */
final class PrivateProfileException extends NotFoundHttpException implements ProblemExceptionInterface
{
    public const string TYPE = '/errors/profile-private';

    public function __construct()
    {
        parent::__construct('profile.private');
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getTitle(): string
    {
        return 'An error occurred';
    }

    public function getStatus(): int
    {
        return $this->getStatusCode();
    }

    public function getDetail(): string
    {
        return $this->getMessage();
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
