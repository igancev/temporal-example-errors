<?php

declare(strict_types=1);

namespace Temporal\Samples\FailedExample;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Samples\FailedExample\Dto\Id;
use Temporal\Samples\FailedExample\Dto\WorkDto;
use Temporal\Samples\FailedExample\Dto\WorkType;
use Temporal\Samples\FailedExample\Workflow\ChildGrayWorkflow;
use Temporal\Samples\FailedExample\Workflow\ChildWhiteWorkflow;
use Temporal\Samples\FailedExample\Workflow\ParentWorkflow;
use Temporal\SampleUtils\Command;

class ExecuteCommand extends Command
{
    protected const string NAME = 'failed-example';
    protected const string DESCRIPTION = 'Execute FailedExample';

    protected const array ARGUMENTS = [
        ['countOfWorkflows', InputArgument::REQUIRED, 'Count of workflows'],
    ];

    /**
     * @var list<string, true>
     */
    private array $sentSignals = [];

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $countOfWorkflows = (int)$input->getArgument('countOfWorkflows');

        $this->runManyWorkflows($countOfWorkflows);

        $count = 0;
        start:
        $d = $this->sendSignals($output);
        $count += $d;
        if ($count < $countOfWorkflows || $d > 0) {
            goto start;
        }

        return self::SUCCESS;
    }

    private function runManyWorkflows(int $countOfWorkflows): void
    {
        for ($i = 0; $i < $countOfWorkflows; ++$i) {
            $workflow = $this->workflowClient->newWorkflowStub(
                ParentWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(Uuid::uuid7()->toString())
            );

            $this->workflowClient->start(
                $workflow,
                new WorkDto(
                    new Id(Uuid::uuid7()->toString()),
                    WorkType::cases()[random_int(0, 1)],
                    'any',
                    'John Doe',
                    ['foo', 'bar', 'baz'],
                ));

            echo "$i\n";
        }
    }

    private function sendSignals(OutputInterface $output): int
    {
        $paginator = $this->workflowClient->listWorkflowExecutions(
            '`ExecutionStatus`="Running" AND `WorkflowType`!="Parent.DoSomething"', // all children
            pageSize: 50,
        );

        $count = 0;
        do {
            $output->writeln('Page: '. $paginator->getPageNumber());

            foreach ($paginator as $key => $workflowExecutionInfo) {
                $runningWorkflowId = $workflowExecutionInfo->execution->getID();

                if (array_key_exists($runningWorkflowId, $this->sentSignals)) {
                    $output->writeln('Signal have been already sent in Workflow ' . $runningWorkflowId);
                    continue;
                }

                ++$count;
                /** @var ChildWhiteWorkflow|ChildGrayWorkflow $workflow */
                $workflow = $this->workflowClient->newRunningWorkflowStub(
                    $workflowExecutionInfo->type->name === 'ChildGrayWorkflow.DoAnything'
                        ? ChildGrayWorkflow::class : ChildWhiteWorkflow::class,
                    $runningWorkflowId,
                );

                // send signal
                try {
                    $workflow->sendSignalToContinue();
                    $this->sentSignals[$runningWorkflowId] = true;
                    $output->writeln($key . '. Sent signal to ' . $runningWorkflowId);
                } catch (WorkflowNotFoundException $e) {
                    $output->writeln($e->getPrevious()->getMessage() . ': ' . $runningWorkflowId);
                } catch (\Throwable $e) {
                    $output->writeln('Catch exception on ' . $runningWorkflowId);
                    dump($e);
                    throw $e;
                }
            }
        } while ($paginator = $paginator->getNextPage());

        return $count;
    }
}
