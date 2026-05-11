<?php

declare(strict_types=1);

namespace App\Enums;

enum HttpMethod: string
{
    case HttpGet = 'GET';
    case HttpHead = 'HEAD';
}
