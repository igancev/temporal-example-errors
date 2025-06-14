<?php

declare(strict_types=1);

namespace Temporal\Samples\FailedExample;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\Type;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class ChildGrayWorkflow
{
    private RegularActivity|ActivityProxy $regularActivity;

    private bool $signalReceived = false;
    private bool $signalAlwaysSkipped = true;

    public function __construct()
    {
        $this->regularActivity = Workflow::newActivityStub(
            class: RegularActivity::class,
            options: (new ActivityOptions())->withStartToCloseTimeout(CarbonInterval::seconds(10))
        );
    }

    #[WorkflowMethod("ChildGrayWorkflow.DoAnything")]
    #[ReturnType(Type::TYPE_ARRAY)]
    public function doAnything(WorkType $type, Id $id, array $tags): \Generator
    {
        $delayMs = yield Workflow::sideEffect(
            static fn() => random_int(100, 500) // from 100ms to 500ms
        );

        yield Workflow::await(fn() => $this->signalAlwaysSkipped);

        $sleepTimeInMs = yield $this->regularActivity->sleep($delayMs);

        if ($sleepTimeInMs > 300) {
            yield $this->regularActivity->sleep($delayMs);
            yield Workflow::await(fn() => $this->signalReceived);
        }

        return [
            'Hello from ChildGrayWorkflow! Delay was ',
            $delayMs,
            ' milliseconds.',
        ];
    }

    #[Workflow\SignalMethod]
    public function sendSignalToContinue(): void
    {
        $this->signalReceived = true;
    }
}
