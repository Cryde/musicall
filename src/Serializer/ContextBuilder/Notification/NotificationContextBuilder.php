<?php

namespace App\Serializer\ContextBuilder\Notification;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\ApiResource\Notification\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NotificationContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly AuthorizationCheckerInterface     $authorizationChecker
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        $resourceClass = $context['resource_class'] ?? null;

        if ($resourceClass === Notification::class // target notification class only
            && isset($context['groups']) // if we have groups
            && $this->authorizationChecker->isGranted('ROLE_ADMIN') // user is admin
            && true === $normalization // normalization (false is for denormalization)
        ) {
            $context['groups'][] = Notification::ITEM_ADMIN;
        }

        return $context;
    }
}