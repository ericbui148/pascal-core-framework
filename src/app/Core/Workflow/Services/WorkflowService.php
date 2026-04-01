<?php

namespace App\Core\Workflow\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * WorkflowService — The state machine engine.
 *
 * Handles:
 *  - Creating / updating workflow definitions
 *  - Evaluating which transitions are allowed for the current user
 *  - Executing transitions (changing state + logging + notifications)
 */
class WorkflowService
{
    // ── Workflow CRUD ─────────────────────────────────────────────────────────

    public function create(array $data, mixed $user): array
    {
        if (DB::table('pascal_workflows')->where('doctype', $data['doctype'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'doctype' => ["An active workflow already exists for DocType [{$data['doctype']}]."],
            ]);
        }

        $id = DB::table('pascal_workflows')->insertGetId([
            'name'        => $data['name'],
            'doctype'     => $data['doctype'],
            'is_active'   => $data['is_active'] ?? true,
            'state_field' => $data['state_field'] ?? 'workflow_state',
            'owner'       => is_object($user) ? ($user->email ?? 'system') : 'system',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Create states
        foreach ($data['states'] ?? [] as $i => $state) {
            DB::table('pascal_workflow_states')->insert([
                'workflow_id' => $id,
                'state'       => $state['state'],
                'doc_status'  => $state['doc_status'] ?? '0',
                'color'       => $state['color']      ?? 'gray',
                'icon'        => $state['icon']        ?? null,
                'is_initial'  => $state['is_initial']  ?? ($i === 0),
                'allow_edit'  => $state['allow_edit']  ?? true,
                'sort_order'  => $i * 10,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // Create transitions
        foreach ($data['transitions'] ?? [] as $i => $tr) {
            DB::table('pascal_workflow_transitions')->insert([
                'workflow_id'          => $id,
                'from_state'           => $tr['from_state'],
                'to_state'             => $tr['to_state'],
                'action'               => $tr['action'],
                'action_icon'          => $tr['action_icon']   ?? null,
                'action_color'         => $tr['action_color']  ?? 'primary',
                'allowed_roles'        => json_encode($tr['allowed_roles'] ?? []),
                'condition'            => $tr['condition']      ?? null,
                'send_email'           => $tr['send_email']    ?? false,
                'requires_comment'     => $tr['requires_comment'] ?? false,
                'requires_confirmation'=> $tr['requires_confirmation'] ?? true,
                'sort_order'           => $i * 10,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        return $this->getWorkflow($id);
    }

    public function getWorkflow(int $id): array
    {
        $wf = DB::table('pascal_workflows')->where('id', $id)->firstOrFail();

        return array_merge((array) $wf, [
            'states' => DB::table('pascal_workflow_states')
                ->where('workflow_id', $id)->orderBy('sort_order')->get()->toArray(),
            'transitions' => DB::table('pascal_workflow_transitions')
                ->where('workflow_id', $id)->orderBy('sort_order')->get()
                ->map(fn ($t) => array_merge((array) $t, [
                    'allowed_roles' => json_decode($t->allowed_roles, true),
                ]))
                ->toArray(),
        ]);
    }

    public function getForDocType(string $doctype): ?array
    {
        $wf = DB::table('pascal_workflows')
            ->where('doctype', $doctype)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$wf) return null;

        return $this->getWorkflow($wf->id);
    }

    public function listWorkflows(): array
    {
        return DB::table('pascal_workflows')
            ->whereNull('deleted_at')
            ->orderBy('doctype')
            ->get()
            ->toArray();
    }

    public function deleteWorkflow(int $id): void
    {
        DB::table('pascal_workflows')->where('id', $id)->update([
            'deleted_at' => now(),
            'is_active'  => false,
        ]);
    }

    // ── State Management ──────────────────────────────────────────────────────

    /**
     * Get available transitions for a document given the current user's role.
     */
    public function getAvailableTransitions(string $doctype, array $doc, mixed $user): array
    {
        $wf = $this->getForDocType($doctype);
        if (!$wf) return [];

        $currentState = $doc[$wf['state_field']] ?? $this->getInitialState($wf);
        $userRole     = is_object($user) ? ($user->role ?? 'user') : ($user['role'] ?? 'user');

        return array_values(array_filter(
            $wf['transitions'],
            function ($tr) use ($currentState, $userRole, $doc) {
                if ($tr['from_state'] !== $currentState) return false;
                if (!in_array($userRole, $tr['allowed_roles']) && !in_array('*', $tr['allowed_roles'])) return false;
                if ($tr['condition']) {
                    return $this->evaluateCondition($tr['condition'], $doc);
                }
                return true;
            }
        ));
    }

    /**
     * Execute a workflow transition.
     */
    public function applyTransition(
        string  $doctype,
        array   &$doc,
        int     $transitionId,
        mixed   $user,
        ?string $comment = null,
    ): array {
        $tr = DB::table('pascal_workflow_transitions')->where('id', $transitionId)->first();

        if (!$tr) {
            throw new \RuntimeException("Transition [{$transitionId}] not found.");
        }

        $wf          = DB::table('pascal_workflows')->where('id', $tr->workflow_id)->first();
        $userRole    = is_object($user) ? ($user->role ?? 'user') : ($user['role'] ?? 'user');
        $currentState = $doc[$wf->state_field] ?? $this->getInitialStateByWorkflowId($wf->id);

        // Guard: correct from_state
        if ($tr->from_state !== $currentState) {
            throw ValidationException::withMessages([
                'workflow' => ["Cannot apply this transition from state [{$currentState}]."],
            ]);
        }

        // Guard: role allowed
        $allowedRoles = json_decode($tr->allowed_roles, true);
        if (!in_array($userRole, $allowedRoles) && !in_array('*', $allowedRoles)) {
            throw ValidationException::withMessages([
                'workflow' => ["Your role [{$userRole}] is not allowed to perform this action."],
            ]);
        }

        // Guard: comment required
        if ($tr->requires_comment && empty($comment)) {
            throw ValidationException::withMessages([
                'comment' => ['A comment is required for this transition.'],
            ]);
        }

        // Get target state info
        $targetState = DB::table('pascal_workflow_states')
            ->where('workflow_id', $wf->id)
            ->where('state', $tr->to_state)
            ->first();

        // Update the document's state field
        $doc[$wf->state_field] = $tr->to_state;

        // Sync docstatus if the state has a doc_status mapping
        if ($targetState && in_array($targetState->doc_status, ['0', '1', '2'])) {
            $doc['docstatus'] = (int) $targetState->doc_status;
        }

        // Log the transition
        DB::table('pascal_workflow_logs')->insert([
            'doctype'       => $doctype,
            'docname'       => $doc['name'],
            'transition_id' => $transitionId,
            'from_state'    => $tr->from_state,
            'to_state'      => $tr->to_state,
            'user_id'       => is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null),
            'user_email'    => is_object($user) ? ($user->email ?? null) : ($user['email'] ?? null),
            'comment'       => $comment,
            'created_at'    => now(),
        ]);

        // Send notification email if configured
        if ($tr->send_email) {
            $this->sendTransitionEmail($doctype, $doc, $tr, $comment);
        }

        return $doc;
    }

    /**
     * Get workflow history for a document.
     */
    public function getHistory(string $doctype, string $docname): array
    {
        return DB::table('pascal_workflow_logs')
            ->where('doctype', $doctype)
            ->where('docname', $docname)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getInitialState(array $wf): string
    {
        foreach ($wf['states'] as $s) {
            if ($s->is_initial ?? $s['is_initial'] ?? false) return $s->state ?? $s['state'];
        }
        return $wf['states'][0]->state ?? $wf['states'][0]['state'] ?? 'Draft';
    }

    private function getInitialStateByWorkflowId(int $workflowId): string
    {
        $s = DB::table('pascal_workflow_states')
            ->where('workflow_id', $workflowId)
            ->where('is_initial', true)
            ->first();

        return $s?->state ?? 'Draft';
    }

    private function evaluateCondition(string $condition, array $doc): bool
    {
        // Safe eval: only allow simple field comparisons
        // e.g. "doc.amount > 1000" or "doc.status == 'Approved'"
        try {
            $safeCondition = str_replace('doc.', '$doc[\'', $condition);
            $safeCondition = preg_replace('/(\$doc\[\'[a-z_]+)\'/', '$1\']', $safeCondition);
            return (bool) eval("return ({$safeCondition});");
        } catch (\Throwable) {
            return true; // on error, allow the transition
        }
    }

    private function sendTransitionEmail(string $doctype, array $doc, object $tr, ?string $comment): void
    {
        // Basic email notification — can be extended with templates
        try {
            $recipientEmail = $doc['owner'] ?? null;
            if (!$recipientEmail) return;

            Mail::raw(
                "Document [{$doc['name']}] ({$doctype}) has been moved from [{$tr->from_state}] to [{$tr->to_state}]."
                . ($comment ? "\n\nComment: {$comment}" : ''),
                fn ($m) => $m->to($recipientEmail)->subject("[Pascal] {$doctype} {$tr->to_state}: {$doc['name']}")
            );
        } catch (\Throwable) {
            // Email failure should not block the transition
        }
    }
}
