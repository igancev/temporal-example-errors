<?php

declare(strict_types=1);

namespace Temporal\Samples\FailedExample;

use Ramsey\Uuid\Uuid;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\SampleUtils\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Samples\FailedExample\ParentWorkflow;

class SignalsCommand extends Command
{
    protected const string NAME = 'signals';
    protected const string DESCRIPTION = 'Send signals into running workflows';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $paginator = $this->workflowClient->listWorkflowExecutions(
            '`ExecutionStatus`="Running" AND `WorkflowType`!="Parent.DoSomething"', // child
            pageSize: 10000,
        );

        foreach ($paginator as $key => $workflowExecutionInfo) {
            $runningWorkflowId = $workflowExecutionInfo->execution->getID();

            /** @var ChildWhiteWorkflow|ChildGrayWorkflow $workflow */
            $workflow = $this->workflowClient->newRunningWorkflowStub(
                $workflowExecutionInfo->type->name === 'ChildGrayWorkflow.DoAnything' ? ChildGrayWorkflow::class : ChildWhiteWorkflow::class,
                $runningWorkflowId,
            );

            // send signal
            try {
                $workflow->sendSignalToContinue();
                $output->writeln($key . '. Sent signal to ' . $runningWorkflowId);
            } catch (WorkflowNotFoundException $e) {
                $output->writeln($e->getPrevious()->getMessage() . ': ' . $runningWorkflowId);
            } catch (\Throwable $e) {
                $output->writeln('Catch exception on ' . $runningWorkflowId);
                dump($e);
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
