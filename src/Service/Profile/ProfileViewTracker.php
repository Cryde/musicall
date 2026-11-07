<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\User;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Counts a visit to one of a member's pages: the profile, its « Médias » (the musician page, #1317) or the lessons
 * page. Never the owner's own, and at most once a period per visitor (ViewProcedure).
 */
readonly class ProfileViewTracker
{
    public function __construct(
        private ViewProcedure $viewProcedure,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    public function track(ViewableInterface $page, User $owner): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        if ($viewer instanceof User && $viewer->id === $owner->id) {
            return;
        }

        $this->viewProcedure->process($page, $request, $viewer);
    }
}
