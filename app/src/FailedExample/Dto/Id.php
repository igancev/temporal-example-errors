<?php
declare(strict_types=1);

namespace Temporal\Samples\FailedExample\Dto;

final readonly class Id
{
    public function __construct(public string $id)
    {
    }
}
