<?php

namespace App\Enums;

enum UserRole: string
{
    case Warehouse = 'WAREHOUSE';
    case User = 'USER';
    case HeadWarehouse = 'HEAD_WAREHOUSE';
    case Admin = 'ADMIN';

    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Warehouse => 'dashboard.warehouse',
            self::User => 'dashboard.user',
            self::HeadWarehouse => 'dashboard.head-warehouse',
            self::Admin => 'dashboard.admin',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Warehouse',
            self::User => 'User',
            self::HeadWarehouse => 'Head Warehouse',
            self::Admin => 'Admin',
        };
    }
}
