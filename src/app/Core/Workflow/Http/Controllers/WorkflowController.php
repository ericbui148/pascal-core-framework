<?php

namespace App\Core\Workflow\Http\Controllers;

use App\Core\Workflow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkflowController extends Controller
{
    public function __construct(protected WorkflowService $wf) {}

    // GET /api/v1/workflows
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->wf->listWorkflows()]);
    }

    // GET /api/v1/workflows/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->wf->getWorkflow($id)]);
    }

    // GET /api/v1/workflows/doctype/{doctype}
    public function forDocType(string $doctype): JsonResponse
    {
        $wf = $this->wf->getForDocType($doctype);
        if (!$wf) {
            return response()->json(['data' => null, 'message' => 'No active workflow for this DocType.']);
        }
        return response()->json(['data' => $wf]);
    }

    // POST /api/v1/workflows
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:120',
            'doctype'     => 'required|string|max:120',
            'states'      => 'required|array|min:2',
            'states.*.state' => 'required|string',
            'transitions' => 'required|array|min:1',
            'transitions.*.from_state'    => 'required|string',
            'transitions.*.to_state'      => 'required|string',
            'transitions.*.action'        => 'required|string',
            'transitions.*.allowed_roles' => 'required|array',
        ]);

        $wf = $this->wf->create($request->all(), $request->user());
        return response()->json(['message' => 'Workflow created.', 'data' => $wf], 201);
    }

    // DELETE /api/v1/workflows/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->wf->deleteWorkflow($id);
        return response()->json(['message' => 'Workflow deleted.']);
    }

    // GET /api/v1/workflows/transitions/{doctype}/{name}
    // Returns available action buttons for the current user + document state
    public function availableTransitions(Request $request, string $doctype, string $name): JsonResponse
    {
        // Fetch the document (works for both custom and real tables)
        $doc = $this->fetchDoc($doctype, $name);

        $transitions = $this->wf->getAvailableTransitions($doctype, $doc, $request->user());

        return response()->json([
            'data'          => $transitions,
            'current_state' => $doc[$this->wf->getForDocType($doctype)['state_field'] ?? 'workflow_state'] ?? null,
        ]);
    }

    // POST /api/v1/workflows/apply/{doctype}/{name}
    public function applyTransition(Request $request, string $doctype, string $name): JsonResponse
    {
        $request->validate([
            'transition_id' => 'required|integer',
            'comment'       => 'sometimes|nullable|string|max:1000',
        ]);

        $doc = $this->fetchDoc($doctype, $name);

        $updatedDoc = $this->wf->applyTransition(
            $doctype,
            $doc,
            $request->transition_id,
            $request->user(),
            $request->comment,
        );

        // Persist the updated state
        $this->saveDoc($doctype, $name, $updatedDoc);

        return response()->json([
            'message'       => 'Transition applied.',
            'data'          => $updatedDoc,
            'current_state' => $updatedDoc['workflow_state'] ?? null,
        ]);
    }

    // GET /api/v1/workflows/history/{doctype}/{name}
    public function history(string $doctype, string $name): JsonResponse
    {
        return response()->json([
            'data' => $this->wf->getHistory($doctype, $name),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fetchDoc(string $doctype, string $name): array
    {
        // Try real table first, fall back to pascal_custom_data
        try {
            $schema = \App\Core\DocType\DocTypeRegistry::schema($doctype);
            $row    = \Illuminate\Support\Facades\DB::table($schema->getTable())
                ->where('name', $name)->first();

            if ($row) return (array) $row;
        } catch (\Throwable) {}

        $row = \Illuminate\Support\Facades\DB::table('pascal_custom_data')
            ->where('doctype', $doctype)->where('name', $name)->first();

        if (!$row) throw new \RuntimeException("Document [{$name}] not found in DocType [{$doctype}].");

        $data = json_decode($row->data, true) ?? [];
        return array_merge(['name' => $row->name, 'docstatus' => $row->docstatus, 'workflow_state' => $row->workflow_state], $data);
    }

    private function saveDoc(string $doctype, string $name, array $doc): void
    {
        // Try real table
        try {
            $schema = \App\Core\DocType\DocTypeRegistry::schema($doctype);
            \Illuminate\Support\Facades\DB::table($schema->getTable())
                ->where('name', $name)
                ->update([
                    'workflow_state' => $doc['workflow_state'] ?? null,
                    'docstatus'      => $doc['docstatus'] ?? 0,
                    'updated_at'     => now(),
                ]);
            return;
        } catch (\Throwable) {}

        // Fall back to custom data
        \Illuminate\Support\Facades\DB::table('pascal_custom_data')
            ->where('doctype', $doctype)->where('name', $name)
            ->update([
                'workflow_state' => $doc['workflow_state'] ?? null,
                'docstatus'      => $doc['docstatus'] ?? 0,
                'data'           => json_encode($doc),
                'updated_at'     => now(),
            ]);
    }
}
