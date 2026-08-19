<?php

namespace App\Domain\Assessment\Enums;

enum QuestionType: string
{
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';
    case CountrySelect = 'country_select';
    case Text = 'text';
    case Textarea = 'textarea';
    case Boolean = 'boolean';
    case Number = 'number';
    case Date = 'date';

    public function hasOptions(): bool
    {
        return in_array($this, [self::SingleSelect, self::MultiSelect], true);
    }

    public function isMultiple(): bool
    {
        return $this === self::MultiSelect;
    }

    /** The inputmode the mobile keyboard should open in. */
    public function inputMode(): ?string
    {
        return match ($this) {
            self::Number => 'numeric',
            self::CountrySelect => 'search',
            default => null,
        };
    }
}
