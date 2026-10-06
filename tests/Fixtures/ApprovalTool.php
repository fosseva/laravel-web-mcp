<?php

namespace Fosseva\WebMcp\Tests\Fixtures;

use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;

class ApprovalTool extends Greet implements Approvable
{
    use InteractsWithApprovals;
}
