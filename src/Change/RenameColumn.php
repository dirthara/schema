<?php

declare(strict_types=1);

namespace Dirthara\Schema\Change;

use Dirthara\Schema\Identifier;

final readonly class RenameColumn implements Change
{
    public function __construct(
        public Identifier $from,
        public Identifier $to,
    ) {}
}
