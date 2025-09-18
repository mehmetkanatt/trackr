<?php

namespace App\enum;

enum TaskStatus: int
{
    case BACKLOG = 0;
    case TODO = 1;
    case INPROGRESS = 2;
    case DONE = 3;
    case CANCELED = 4;

    public function capitalizedStatusName(): string
    {
        return ucfirst(strtolower($this->name));
    }
}