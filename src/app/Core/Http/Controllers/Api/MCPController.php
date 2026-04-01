<?php

namespace App\Core\Http\Controllers\Api;

use App\Core\DocType\DocTypeRegistry;
use App\Core\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MCPController extends Controller
{
    public function __construct(protected DocumentService $service) {}

    /** POST /api/v1/mcp/tools — AI agent discovers available tools */
    public function listTools(): JsonResponse
    {
        $tools = [];

        foreach (DocTypeRegistry::allSchemas() as $name => $schema) {
            $slug    = str_replace('_', '-', strtolower($name));
            $tools[] = ['name' => "get_{$slug}",    'description' => "Get a {$name} by name"];
            $tools[] = ['name' => "list_{$slug}",   'description' => "List {$name} records"];
            $tools[] = ['name' => "create_{$slug}", 'description' => "Create a {$name}"];
            $tools[] = ['name' => "update_{$slug}", 'description' => "Update a {$name}"];
            $tools[] = ['name' => "delete_{$slug}", 'description' => "Delete a {$name}"];

            if ($schema->isSubmittable) {
                $tools[] = ['name' => "submit_{$slug}", 'description' => "Submit a {$name}"];
                $tools[] = ['name' => "cancel_{$slug}", 'description' => "Cancel a {$name}"];
            }
        }

        return response()->json(['tools' => $tools]);
    }

    /** POST /api/v1/mcp/execute — AI agent executes a tool */
    public function execute(Request $request): JsonResponse
    {
        $request->validate(['tool' => 'required|string', 'arguments' => 'sometimes|array']);

        $tool      = $request->input('tool');
        $arguments = $request->input('arguments', []);
        $user      = $request->user();

        // Parse "submit_sales_invoice" → action=submit, doctype=sales_invoice
        if (!preg_match('/^(get|list|create|update|delete|submit|cancel)_(.+)$/', $tool, $m)) {
            return response()->json(['error' => "Unknown tool: {$tool}"], 400);
        }

        [, $action, $doctype] = $m;

        $result = match ($action) {
            'get'    => $this->service->get($doctype, $arguments['name']),
            'list'   => $this->service->list($doctype, $arguments['filters'] ?? []),
            'create' => $this->service->create($doctype, $arguments, $user),
            'update' => $this->service->update($doctype, $arguments['name'], $arguments, $user),
            'delete' => (fn () => $this->service->delete($doctype, $arguments['name'], $user) ?: ['deleted' => true])(),
            'submit' => $this->service->submit($doctype, $arguments['name'], $user),
            'cancel' => $this->service->cancel($doctype, $arguments['name'], $user),
        };

        return response()->json([
            'content' => [['type' => 'text', 'text' => json_encode($result)]],
        ]);
    }
}
