<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** « Ce que tu vas apprendre » for one level (#1312): a title, the points under it, or both. */
class LevelGoal
{
    #[Assert\NotBlank(message: 'profile.teacher.level_goal.level_required')]
    #[Assert\Choice(choices: ['beginner', 'intermediate', 'advanced'], message: 'profile.teacher.level_invalid')]
    public ?string $level = null;

    #[Assert\Length(max: 80, maxMessage: 'profile.teacher.level_goal.title_too_long')]
    public ?string $title = null;

    /** @var string[] */
    #[Assert\Count(max: 3, maxMessage: 'profile.teacher.level_goal.too_many_points')]
    #[Assert\All([
        new Assert\Length(max: 80, maxMessage: 'profile.teacher.level_goal.point_too_long'),
    ])]
    public array $points = [];
}
