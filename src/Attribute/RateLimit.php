<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RateLimit
{
    public function __construct(public string $limiterName)
    {
    }
}
