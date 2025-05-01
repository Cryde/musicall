<?php

namespace App\Service\Bot;

class BotDetector
{
    public function isBot(string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, "facebookexternalhit/") || str_contains($userAgent, "facebot")) {
            return true;
        }
        return str_contains($userAgent, "twitterbot");
    }
}
