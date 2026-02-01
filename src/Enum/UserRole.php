<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case BANNED = 'ROLE_BANNED';
    case BOT = 'ROLE_BOT';
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';
    case SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
}
