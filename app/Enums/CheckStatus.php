<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckStatus: string
{
    case Up = 'up';
    case Down = 'down';
}
