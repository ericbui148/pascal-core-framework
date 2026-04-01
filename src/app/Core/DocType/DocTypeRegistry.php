<?php

namespace App\Core\DocType;

use App\Core\Contracts\DocumentController;
use RuntimeException;

/**
 * DocTypeRegistry — in-memory registry, populated at boot time by module ServiceProviders.
 */
class DocTypeRegistry
{
    private static array $schemas     = [];  // doctype => DocTypeSchema
    private static array $controllers = [];  // doctype => FQCN

    // ── Registration ─────────────────────────────────────────────────────────

    public static function register(
        string  $doctype,
        ?string $controllerClass = null,
        array   $options = []
    ): void {
        static::$schemas[$doctype]     = new DocTypeSchema(array_merge(['name' => $doctype], $options));
        static::$controllers[$doctype] = $controllerClass ?? BaseDocumentController::class;
    }

    // ── Retrieval ─────────────────────────────────────────────────────────────

    public static function schema(string $doctype): DocTypeSchema
    {
        if (!isset(static::$schemas[$doctype])) {
            throw new RuntimeException("DocType [{$doctype}] is not registered.");
        }
        return static::$schemas[$doctype];
    }

    public static function controller(string $doctype): DocumentController
    {
        $class = static::$controllers[$doctype] ?? BaseDocumentController::class;
        return app($class);
    }

    public static function exists(string $doctype): bool
    {
        return isset(static::$schemas[$doctype]);
    }

    public static function all(): array
    {
        return array_keys(static::$schemas);
    }

    public static function allSchemas(): array
    {
        return static::$schemas;
    }
}
