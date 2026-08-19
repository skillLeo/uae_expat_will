<?php

namespace App\Domain\Assessment\Enums;

enum ConditionAction: string
{
    case Show = 'show';
    case Hide = 'hide';
    case SkipSection = 'skip_section';

    public function label(): string
    {
        return match ($this) {
            self::Show => 'show this question',
            self::Hide => 'hide this question',
            self::SkipSection => 'skip the whole section',
        };
    }
}
