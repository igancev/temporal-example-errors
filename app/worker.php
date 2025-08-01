<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\OpenTelemetry\Interceptor\OpenTelemetryActivityInboundInterceptor;
use Temporal\OpenTelemetry\Interceptor\OpenTelemetryWorkflowOutboundRequestInterceptor;
use Temporal\SampleUtils\DeclarationLocator;
use Temporal\SampleUtils\TracerFactory;
use Temporal\WorkerFactory;

ini_set('display_errors', 'stderr');
include "vendor/autoload.php";

error_reporting(E_ALL & ~E_DEPRECATED);

//FeatureFlags::$workflowDeferredHandlerStart = true;

// finds all available workflows, activity types and commands in a given directory
$declarations = DeclarationLocator::create(__DIR__ . '/src/');

// factory initiates and runs task queue specific activity and workflow workers
$factory = WorkerFactory::create();

// OpenTelemetry tracer
$tracer = TracerFactory::create('interceptors-sample-worker');

// Worker that listens on a task queue and hosts both workflow and activity implementations.
$worker = $factory->newWorker(
    options: \Temporal\Worker\WorkerOptions::new()->withMaxConcurrentWorkflowTaskExecutionSize(100),
    interceptorProvider: new SimplePipelineProvider([
        new OpenTelemetryActivityInboundInterceptor($tracer),
        new OpenTelemetryWorkflowOutboundRequestInterceptor($tracer)
    ])
);

foreach ($declarations->getWorkflowTypes() as $workflowType) {
    // Workflows are stateful. So you need a type to create instances.
    $worker->registerWorkflowTypes($workflowType);
}

foreach ($declarations->getActivityTypes() as $activityType) {
    // Activities are stateless and thread safe. So a shared instance is used.
    $worker->registerActivity($activityType);
}

// We can use task queue for more complex task routing, for example our FileProcessing
// activity will receive unique, host specific, TaskQueue which can be used to process
// files locally.
$hostTaskQueue = gethostname();

// start primary loop
$factory->run();
