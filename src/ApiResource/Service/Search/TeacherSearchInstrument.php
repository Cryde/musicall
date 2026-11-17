<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

class TeacherSearchInstrument
{
    public string $id;
    public string $name;
    /** What the teacher page's `?instrument=` and the search page's address hold. */
    public string $slug;
}
