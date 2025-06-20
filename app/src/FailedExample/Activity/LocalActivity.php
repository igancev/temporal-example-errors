<?php

namespace Temporal\Samples\FailedExample\Activity;

use Temporal\Activity\ActivityMethod;
use Temporal\Activity\LocalActivityInterface;

#[LocalActivityInterface(prefix: 'LocalActivity.')]
class LocalActivity
{
    #[ActivityMethod(name: "Calculate")]
    public function calculate(): string
    {
        // synthetic delay on local activity
        $delayMs = random_int(1, 30);

        usleep($delayMs * 1000);

        return 'Delay is ' . $delayMs . ' ms';
    }
}
