<?php

namespace App\enum;

enum EisenhowerStatusColor: string
{
    case ELIMINATE = 'bg-secondary-dark';
    case DELEGATE = 'bg-info-dark';
    case SCHEDULE = 'bg-warning-dark';
    case DO = 'bg-danger-darker';

    public function capitalizedStatusColorName(): string
    {
        return ucfirst(strtolower($this->name));
    }

    public static function valueFromName(string $name): ?string
    {
        // Since i use PHP 8.2 i need this function but can be done like this at PHP 8.3: EisenhowerStatusColor::{$eisenhowerStatus}->value
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case->value;
            }
        }
        return null; // not found
    }
}