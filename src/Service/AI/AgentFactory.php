<?php

namespace App\Service\AI;

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Platform;

class AgentFactory
{
    public static function create(Platform $platform, string $model): Agent
    {
        return new Agent(
            $platform,
            $model,
        );
    }
}

