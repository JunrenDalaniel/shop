<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Insufficient stock for {$productName}: requested {$requested}, available {$available}."
        );
    }
}
