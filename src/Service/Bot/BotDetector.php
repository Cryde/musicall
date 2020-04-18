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

        if (strpos($userAgent, "facebookexternalhit/") !== false ||
            strpos($userAgent, "Facebot") !== false) {
            return true;
        }

        return false;
    }
}
