<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Dirthara\Schema\Exception\InvalidSchemaException;

use function trim;
use function preg_match;

final readonly class Identifier
{
    private const string PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @throws InvalidSchemaException
     */
    public function __construct(
        public string $name,
    ) {
        if (trim($this->name) === '') {
            throw InvalidSchemaException::emptyIdentifier();
        }

        if (preg_match(self::PATTERN, $this->name) !== 1) {
            throw InvalidSchemaException::invalidIdentifier($this->name);
        }
    }
}
