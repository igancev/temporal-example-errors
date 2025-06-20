<?php

declare(strict_types=1);

namespace Temporal\Samples\FailedExample\Workflow;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\Type;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Samples\FailedExample\Activity\RegularActivity;
use Temporal\Samples\FailedExample\Dto\Id;
use Temporal\Samples\FailedExample\Dto\WorkType;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class ChildWhiteWorkflow
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

    #[WorkflowMethod("ChildWhiteWorkflow.DoAnything")]
    #[ReturnType(Type::TYPE_STRING)]
    public function doAnything(WorkType $type, Id $id, array $tags): \Generator
    {
        $delayMs = yield Workflow::sideEffect(
            static fn() => random_int(100, 500) // from 100ms to 500ms
        );

        yield Workflow::await(fn() => $this->signalAlwaysSkipped);

        $sleepTimeInMs = yield $this->regularActivity->sleep($delayMs);

        if ($sleepTimeInMs > 300) {
            yield $this->regularActivity->beforeWait('white', $delayMs);
            yield Workflow::await(fn() => $this->signalReceived);
        }

        return 'Hello from ChildWhiteWorkflow! Delay was ' . $delayMs . ' milliseconds. ' .
            ' Signal was received: ' . $this->signalReceived ? 'yes' : 'no';
    }

    #[Workflow\SignalMethod]
    public function sendSignalToContinue(): void
    {
        $this->signalReceived = true;
    }
}
