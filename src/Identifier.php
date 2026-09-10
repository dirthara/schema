<?php

declare(strict_types=1);

namespace Dirthara\Schema;

use Dirthara\Schema\Exceptions\InvalidSchemaException;

use function trim;
use function sprintf;
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
            throw new InvalidSchemaException('An identifier cannot be empty.');
        }

        if (preg_match(self::PATTERN, $this->name) !== 1) {
            throw new InvalidSchemaException(
                message: sprintf(
                    'The identifier [%s] may only contain letters, digits and underscores, and cannot start with a digit.',
                    $this->name,
                ),
                context: ['identifier' => $this->name],
            );
        }
    }
}
