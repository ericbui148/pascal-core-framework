<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Workflow Engine tables
|--------------------------------------------------------------------------
| A Workflow is a state machine attached to a DocType.
| It defines: states, transitions between states, and who can trigger them.
|
| Example: Leave Application workflow
|   States     : Draft → Pending Approval → Approved / Rejected
|   Transitions: [Draft → Pending]     allowed by: role=Employee
|                [Pending → Approved]  allowed by: role=Manager
|                [Pending → Rejected]  allowed by: role=Manager
|                [Approved → Cancelled] allowed by: role=HR Manager
*/

return new class extends Migration
{
    public function up(): void
    {
        // ── Workflows ─────────────────────────────────────────────────────────
        Schema::create('pascal_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('doctype', 120);              // which DocType this applies to
            $table->boolean('is_active')->default(true);
            $table->string('state_field', 120)->default('workflow_state'); // field storing current state
            $table->string('owner', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('doctype');
        });

        // ── Workflow States ───────────────────────────────────────────────────
        Schema::create('pascal_workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('pascal_workflows')->cascadeOnDelete();
            $table->string('state', 120);                // "Draft", "Pending Approval", "Approved"
            $table->string('doc_status', 10)->default('0'); // '0'=Draft, '1'=Submitted, '2'=Cancelled
            $table->string('color', 30)->default('gray'); // badge color: gray|blue|green|red|yellow|purple
            $table->string('icon', 60)->nullable();       // heroicon
            $table->boolean('is_initial')->default(false);// starting state
            $table->boolean('allow_edit')->default(true); // can edit document in this state
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workflow_id', 'state']);
        });

        // ── Workflow Transitions ──────────────────────────────────────────────
        Schema::create('pascal_workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('pascal_workflows')->cascadeOnDelete();
            $table->string('from_state', 120);
            $table->string('to_state', 120);
            $table->string('action', 120);               // button label: "Submit", "Approve", "Reject"
            $table->string('action_icon', 60)->nullable();// heroicon for button
            $table->string('action_color', 30)->default('primary'); // primary|success|danger|warning|gray
            $table->json('allowed_roles');               // ["Manager", "HR Manager"]
            $table->string('condition', 1000)->nullable();// optional: "doc.amount > 1000"
            $table->boolean('send_email')->default(false);
            $table->string('email_template', 120)->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->boolean('requires_confirmation')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workflow_id', 'from_state']);
        });

        // ── Workflow Actions (log of every transition taken) ──────────────────
        Schema::create('pascal_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->string('doctype', 120);
            $table->string('docname', 255);
            $table->foreignId('transition_id')->constrained('pascal_workflow_transitions');
            $table->string('from_state', 120);
            $table->string('to_state', 120);
            $table->foreignId('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at');

            $table->index(['doctype', 'docname']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pascal_workflow_logs');
        Schema::dropIfExists('pascal_workflow_transitions');
        Schema::dropIfExists('pascal_workflow_states');
        Schema::dropIfExists('pascal_workflows');
    }
};
