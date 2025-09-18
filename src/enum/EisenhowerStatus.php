<?php

namespace App\enum;

enum EisenhowerStatus: int
{
    case ELIMINATE = 0;
    case DELEGATE = 1;
    case SCHEDULE = 2;
    case DO = 3;

    public function capitalizedStatusName(): string
    {
        return ucfirst(strtolower($this->name));
    }
}