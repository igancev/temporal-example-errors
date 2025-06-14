<?php

declare(strict_types=1);

namespace Temporal\Samples\FailedExample;

use Carbon\CarbonInterval;
use Generator;
use Ramsey\Uuid\Uuid;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\LocalActivityOptions;
use Temporal\DataConverter\Type;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class ParentWorkflow
{
    private LocalActivity | ActivityProxy $localActivity;
    private RegularActivity | ActivityProxy $regularActivity;

    public function __construct()
    {
        $this->localActivity = Workflow::newActivityStub(
            class: LocalActivity::class,
            options: new LocalActivityOptions(),
        );

        $this->regularActivity = Workflow::newActivityStub(
            class: RegularActivity::class,
            options: ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(10))
        );
    }

    #[WorkflowMethod(name: "Parent.DoSomething")]
    #[ReturnType(Type::TYPE_STRING)]
    public function doSomething(WorkDto $workDto): Generator
    {
        // dirty local activity with sleep under the hood
        yield $this->localActivity->calculate();

        $childWorkflowId = yield Workflow::sideEffect(
            static fn() => Uuid::uuid7()->toString()
        );

        yield $this->regularActivity->persistChildWorkflowId($workDto, $childWorkflowId);

        $childWorkflowType = yield $this->regularActivity->detectChildWorkflowType($workDto->type, $workDto->tenant);

        /** @var ChildWhiteWorkflow|ChildGrayWorkflow $childWorkflow */
        $childWorkflow = Workflow::newChildWorkflowStub(
            class: $childWorkflowType,
            options: (new Workflow\ChildWorkflowOptions())
                ->withWorkflowId($childWorkflowId)
        );

        yield $childWorkflow->doAnything($workDto->type, $workDto->id, $workDto->tags);

        yield $this->regularActivity->finish($workDto->id);

        return 'I am ParentWorkflow, My WorkflowId: ' .
            Workflow::getCurrentContext()->getInfo()->execution->getID();
    }
}
