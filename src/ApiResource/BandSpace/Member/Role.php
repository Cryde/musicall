<?php

namespace App\ApiResource\BandSpace\Member;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}
