<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case Worker = 'worker';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Worker => 'Obreiro',
        };
    }

    /**
     * Prefixo usado na geração do código do utilizador.
     */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Admin => 'ADM',
            self::Worker => 'WRK',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
