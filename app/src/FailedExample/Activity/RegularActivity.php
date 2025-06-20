<?php

namespace Temporal\Samples\FailedExample\Activity;

use Ramsey\Uuid\Rfc4122\UuidV7;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Samples\FailedExample\Dto\Id;
use Temporal\Samples\FailedExample\Dto\WorkDto;
use Temporal\Samples\FailedExample\Dto\WorkType;
use Temporal\Samples\FailedExample\Workflow\ChildGrayWorkflow;
use Temporal\Samples\FailedExample\Workflow\ChildWhiteWorkflow;

#[ActivityInterface(prefix: 'RegularActivity.')]
class RegularActivity
{
    #[ActivityMethod(name: "Sleep")]
    public function sleep(int $delayMilliseconds): int
    {
        usleep(microseconds: $delayMilliseconds * 1000);

        return $delayMilliseconds;
    }

    #[ActivityMethod(name: "BeforeWait")]
    public function beforeWait(string $any, int $delayMilliseconds): int
    {
        usleep(microseconds: $delayMilliseconds * 1000);

        return $delayMilliseconds;
    }

    #[ActivityMethod(name: "PersistChildWorkflowId")]
    public function persistChildWorkflowId(WorkDto $workDto, string $childWorkflowId): true
    {
        // try to instantiate uuid
        UuidV7::fromString($childWorkflowId);
        UuidV7::fromString($workDto->id->id);

        // access dto fields
        foreach ($workDto->tags as $tag) {
            $nonsense = $tag . $workDto->name . $workDto->id->id . $workDto->tenant;
        }

        // synthetic delay
        $delayMs = random_int(1, 50);
        usleep($delayMs * 1000);

        return true;
    }

    #[ActivityMethod(name: "DetectChildWorkflowType")]
    public function detectChildWorkflowType(WorkType $type, string $tenant): string
    {
        return match ($type) {
            WorkType::gray => ChildGrayWorkflow::class,
            WorkType::white => ChildWhiteWorkflow::class,
        };
    }

    #[ActivityMethod(name: "Finish")]
    public function finish(Id $id): true
    {
        // try to instantiate uuid
        UuidV7::fromString($id->id);

        // synthetic delay
        $delayMs = random_int(1, 50);
        usleep($delayMs * 1000);

        return true;
    }
}
