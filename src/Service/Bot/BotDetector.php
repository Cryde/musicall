<?php

namespace App\Service\Bot;

class BotDetector
{
    public function isBot(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, "facebookexternalhit/") !== false ||
            strpos($userAgent, "Facebot") !== false) {
            return true;
        }

        return false;
    }
}
