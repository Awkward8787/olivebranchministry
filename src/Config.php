<?php

declare(strict_types=1);

namespace Obm;

use RuntimeException;

final class Config
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException('Missing config.php. Copy config.example.php and fill in the database.');
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('config.php must return an array.');
        }

        return new self($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $node = $this->data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }

        return $node;
    }
}
