<?php

namespace App\Domains\Ambassador\Infrastructure\EventBus;

class AmbassadorEventBus
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}
