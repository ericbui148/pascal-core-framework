<?php

namespace App\Core\Events;

// Generic document lifecycle events — dispatched automatically by DocumentService.
// Module listeners subscribe to these.

class DocumentCreated
{
    public function __construct(
        public readonly string $doctype,
        public readonly array  $data,
        public readonly mixed  $user,
    ) {}
}

class DocumentUpdated
{
    public function __construct(
        public readonly string $doctype,
        public readonly array  $data,
        public readonly mixed  $user,
        public readonly array  $diff = [],
    ) {}
}

class DocumentSubmitted
{
    public function __construct(
        public readonly string $doctype,
        public readonly array  $data,
        public readonly mixed  $user,
    ) {}
}

class DocumentCancelled
{
    public function __construct(
        public readonly string $doctype,
        public readonly array  $data,
        public readonly mixed  $user,
    ) {}
}

class DocumentDeleted
{
    public function __construct(
        public readonly string $doctype,
        public readonly array  $data,
        public readonly mixed  $user,
    ) {}
}
