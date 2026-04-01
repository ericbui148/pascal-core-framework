<?php

namespace App\Core\Contracts;

interface DocumentController
{
    /** Validate. Throw ValidationException on failure. */
    public function validate(array &$data): void;

    /** Transform / compute fields BEFORE save. */
    public function beforeSave(array &$data, ?array $existing = null): void;

    /** Side effects AFTER a successful save. */
    public function afterSave(array $data, string $action): void;

    /** Business logic on Submit (docstatus 0→1). Dispatch domain events here. */
    public function onSubmit(array $data): void;

    /** Business logic on Cancel (docstatus 1→2). */
    public function onCancel(array $data): void;

    /** Guard before deletion. Throw to prevent. */
    public function beforeDelete(array $data): void;
}
