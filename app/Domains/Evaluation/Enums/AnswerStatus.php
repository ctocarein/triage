<?php

namespace App\Domains\Evaluation\Enums;

enum AnswerStatus: string
{
    case Answered = 'answered';
    case Skipped = 'skipped';
    case Invalidated = 'invalidated';
    case NonApplicable = 'non_applicable';
}
