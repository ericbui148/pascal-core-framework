<x-filament-panels::page>
<div class="flex gap-6 min-h-[calc(100vh-10rem)]">

    {{-- LEFT: Workflow list ───────────────────────────────────────────────── --}}
    <div class="w-64 flex-shrink-0 space-y-2">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-1 mb-1">Workflows</div>

        @foreach ($workflows as $wf)
        <button wire:click="selectWorkflow({{ $wf['id'] }})"
            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-all
                {{ ($selected['id'] ?? null) == $wf['id']
                    ? 'bg-violet-600 text-white font-semibold'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
            <div class="font-medium">{{ $wf['name'] }}</div>
            <div class="text-xs opacity-60">{{ $wf['doctype'] }}</div>
        </button>
        @endforeach

        @if (empty($workflows))
        <div class="text-sm text-gray-400 px-1">No workflows yet. Click "New Workflow".</div>
        @endif
    </div>

    {{-- RIGHT: Workflow detail ────────────────────────────────────────────── --}}
    <div class="flex-1">
        @if (!$selected)
        <div class="flex flex-col items-center justify-center h-full text-gray-400 gap-4">
            <x-heroicon-o-arrow-path class="w-16 h-16 opacity-30" />
            <div class="text-lg">Select a workflow to view its diagram</div>
        </div>
        @else
        <div class="space-y-6">

            {{-- Header --}}
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $selected['name'] }}</h2>
                    <p class="text-sm text-gray-500">DocType: <span class="font-medium text-violet-600">{{ $selected['doctype'] }}</span></p>
                </div>
                <button wire:click="deleteWorkflow({{ $selected['id'] }})"
                    wire:confirm="Delete workflow '{{ $selected['name'] }}'?"
                    class="text-red-400 hover:text-red-600 text-sm flex items-center gap-1">
                    <x-heroicon-o-trash class="w-4 h-4" /> Delete
                </button>
            </div>

            {{-- State diagram ──────────────────────────────────────────────── --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-5 uppercase tracking-wider">State Diagram</h3>

                @php
                    $colorMap = [
                        'gray'   => 'bg-gray-100 text-gray-700 border-gray-300',
                        'blue'   => 'bg-blue-100 text-blue-700 border-blue-300',
                        'green'  => 'bg-green-100 text-green-700 border-green-300',
                        'red'    => 'bg-red-100 text-red-700 border-red-300',
                        'yellow' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                        'purple' => 'bg-violet-100 text-violet-700 border-violet-300',
                    ];
                    $btnColorMap = [
                        'primary' => 'bg-blue-500 text-white',
                        'success' => 'bg-green-500 text-white',
                        'danger'  => 'bg-red-500 text-white',
                        'warning' => 'bg-yellow-400 text-white',
                        'gray'    => 'bg-gray-400 text-white',
                    ];
                @endphp

                {{-- States row --}}
                <div class="flex items-center gap-4 flex-wrap mb-8">
                    @foreach ($selected['states'] as $i => $state)
                        @php $c = $colorMap[$state->color ?? $state['color'] ?? 'gray']; @endphp
                        <div class="flex items-center gap-2">
                            <div class="px-4 py-2 rounded-lg border-2 text-sm font-semibold {{ $c }}
                                {{ ($state->is_initial ?? $state['is_initial'] ?? false) ? 'ring-2 ring-offset-2 ring-violet-500' : '' }}">
                                {{ $state->state ?? $state['state'] }}
                                @if ($state->is_initial ?? $state['is_initial'] ?? false)
                                    <span class="text-xs font-normal ml-1 opacity-70">(initial)</span>
                                @endif
                                <div class="text-xs font-normal opacity-60">docstatus={{ $state->doc_status ?? $state['doc_status'] ?? 0 }}</div>
                            </div>
                            @if (!$loop->last)
                                <x-heroicon-m-chevron-right class="w-5 h-5 text-gray-400" />
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Transitions table --}}
                <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wider">Transitions</h3>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">From State</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Action Button</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">To State</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500">Allowed Roles</th>
                            <th class="px-4 py-2 text-center text-xs text-gray-500">Email</th>
                            <th class="px-4 py-2 text-center text-xs text-gray-500">Comment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($selected['transitions'] as $tr)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $tr['from_state'] ?? $tr->from_state }}</td>
                            <td class="px-4 py-3">
                                @php $bc = $btnColorMap[$tr['action_color'] ?? $tr->action_color ?? 'primary']; @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $bc }}">
                                    {{ $tr['action'] ?? $tr->action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $tr['to_state'] ?? $tr->to_state }}</td>
                            <td class="px-4 py-3">
                                @php $roles = is_array($tr['allowed_roles'] ?? null) ? $tr['allowed_roles'] : (is_array($tr->allowed_roles ?? null) ? $tr->allowed_roles : json_decode($tr->allowed_roles ?? '[]', true)); @endphp
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($roles as $role)
                                    <span class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded text-xs">{{ $role }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($tr['send_email'] ?? $tr->send_email ?? false)
                                    <x-heroicon-m-check-circle class="w-4 h-4 text-green-500 inline" />
                                @else
                                    <x-heroicon-m-minus class="w-4 h-4 text-gray-300 inline" />
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($tr['requires_comment'] ?? $tr->requires_comment ?? false)
                                    <x-heroicon-m-check-circle class="w-4 h-4 text-green-500 inline" />
                                @else
                                    <x-heroicon-m-minus class="w-4 h-4 text-gray-300 inline" />
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- API usage hint --}}
            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">How to use via API</div>
                <div class="font-mono text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div class="text-gray-400"># Get available action buttons for a document:</div>
                    <div>GET /api/v1/workflows/transitions/{{ $selected['doctype'] }}/{name}</div>
                    <div class="text-gray-400 mt-2"># Apply a transition (e.g. Approve):</div>
                    <div>POST /api/v1/workflows/apply/{{ $selected['doctype'] }}/{name}</div>
                    <div class="text-gray-400 pl-4">{ "transition_id": 1, "comment": "Looks good" }</div>
                    <div class="text-gray-400 mt-2"># View history:</div>
                    <div>GET /api/v1/workflows/history/{{ $selected['doctype'] }}/{name}</div>
                </div>
            </div>

        </div>
        @endif
    </div>

</div>
</x-filament-panels::page>
