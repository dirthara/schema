<?php

declare(strict_types=1);

namespace Dirthara\Schema\Change;

use Dirthara\Schema\Index\Index;

final readonly class AddIndex implements Change
{
    public function __construct(
        public Index $index,
    ) {}
}
