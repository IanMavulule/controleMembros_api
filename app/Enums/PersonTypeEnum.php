<?php

namespace App\Enums;

enum PersonTypeEnum: string
{
    case Visitor = 'visitor';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => 'Visitante',
            self::Member => 'Membro',
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
