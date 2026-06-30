<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Registry;

/**
 * The wiring behind one resource key: the Filter Data class (declaration site), the
 * Query class (escape hatch + model binding), and the Eloquent model. Bound classes
 * self-describe; this is the value the Resource Registry resolves a key to.
 */
final class ResourceDefinition
{
    /**
     * @param  class-string  $data
     * @param  class-string  $query
     * @param  class-string  $model
     */
    public function __construct(
        public readonly string $key,
        public readonly string $data,
        public readonly string $query,
        public readonly string $model,
    ) {}

    /**
     * @param  array{data: class-string, query: class-string, model: class-string}  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            data: $config['data'],
            query: $config['query'],
            model: $config['model'],
        );
    }
}
