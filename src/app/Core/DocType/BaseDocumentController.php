<?php

namespace App\Core\DocType;

use App\Core\Contracts\DocumentController;

/** Default no-op implementation. Module controllers extend and override only what they need. */
class BaseDocumentController implements DocumentController
{
    public function validate(array &$data): void {}
    public function beforeSave(array &$data, ?array $existing = null): void {}
    public function afterSave(array $data, string $action): void {}
    public function onSubmit(array $data): void {}
    public function onCancel(array $data): void {}
    public function beforeDelete(array $data): void {}
}
