<?php

namespace App\enum;

enum EisenhowerStatusColor: string
{
    case ELIMINATE = 'bg-secondary-dark';
    case DELEGATE = 'bg-info-dark';
    case SCHEDULE = 'bg-warning-dark';
    case DO = 'bg-danger-darker';
}