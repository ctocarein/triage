<?php

namespace App\Domains\Evaluation\Enums;

enum QuestionType: string
{
    case Boolean        = 'boolean';
    case Text           = 'text';
    case Textarea       = 'textarea';
    case Number         = 'number';
    case Date           = 'date';
    case SelectOne      = 'select_one';
    case SelectMultiple = 'select_multiple';
    case Radio          = 'radio';
    case Checkbox       = 'checkbox';
    case Slider         = 'slider';
    case Scale          = 'scale';
    case Info           = 'info';
    case File           = 'file';
    case Hidden         = 'hidden';
    case Dynamic        = 'dynamic';
    case Custom         = 'custom';
    case Map            = 'map';

    /**
     * All string values — use with Rule::in() or seeder validation.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Types that expect a predefined list of options (options[]).
     */
    public function hasOptions(): bool
    {
        return match ($this) {
            self::SelectOne, self::SelectMultiple,
            self::Radio, self::Checkbox => true,
            default                     => false,
        };
    }

    /**
     * Types that collect actual user input (excludes info/hidden/custom/map/dynamic).
     */
    public function isInput(): bool
    {
        return match ($this) {
            self::Info, self::Hidden,
            self::Custom, self::Map,
            self::Dynamic               => false,
            default                     => true,
        };
    }
}
