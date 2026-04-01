<x-filament-panels::page>
<div class="flex gap-6 h-[calc(100vh-10rem)]">

    {{-- LEFT: DocType list ────────────────────────────────────────────────── --}}
    <div class="w-64 flex-shrink-0 flex flex-col gap-2 overflow-y-auto">
        <div class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-1 mb-1">DocTypes</div>

        @foreach ($doctypes as $dt)
        <button wire:click="selectDocType('{{ $dt['name'] }}')"
            class="text-left px-3 py-2 rounded-lg text-sm transition-all
                {{ $selectedDocType === $dt['name']
                    ? 'bg-violet-600 text-white font-semibold'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
            <div class="font-medium">{{ $dt['name'] }}</div>
            <div class="text-xs opacity-60">{{ $dt['module'] }}</div>
        </button>
        @endforeach

        @if (empty($doctypes))
            <div class="text-sm text-gray-400 px-1">No DocTypes yet. Click "New DocType" to create one.</div>
        @endif
    </div>

    {{-- RIGHT: Field editor ───────────────────────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto">
        @if (!$selectedDocType)
            <div class="flex flex-col items-center justify-center h-full text-gray-400 gap-4">
                <x-heroicon-o-wrench-screwdriver class="w-16 h-16 opacity-30" />
                <div class="text-lg">Select a DocType to edit its fields</div>
                <div class="text-sm">Or create a new one with the button above</div>
            </div>
        @else
            {{-- DocType header --}}
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $doctype['name'] }}</h2>
                    <div class="text-sm text-gray-500">
                        Module: {{ $doctype['module'] }}
                        @if ($doctype['is_submittable'] ?? false)
                            &nbsp;·&nbsp;<span class="text-violet-500 font-medium">Submittable</span>
                        @endif
                        &nbsp;·&nbsp;{{ count($fields) }} fields
                    </div>
                </div>
                @if (!($doctype['is_system'] ?? false))
                <button wire:click="deleteDocType"
                    wire:confirm="Delete DocType {{ $doctype['name'] }}? This cannot be undone."
                    class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete DocType
                </button>
                @endif
            </div>

            {{-- Fields table --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-8">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Label</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fieldname</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Required</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">In List</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Options</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Order</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Delete</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($fields as $i => $field)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $field['label'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $field['fieldname'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ in_array($field['fieldtype'], ['Section Break','Column Break','HTML'])
                                        ? 'bg-gray-100 text-gray-500'
                                        : 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' }}">
                                    {{ $field['fieldtype'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox"
                                    wire:click="toggleRequired('{{ $field['fieldname'] }}', {{ $field['required'] ? 'false' : 'true' }})"
                                    @checked($field['required'])
                                    class="rounded border-gray-300 text-violet-600 cursor-pointer" />
                            </td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox"
                                    wire:click="toggleListView('{{ $field['fieldname'] }}', {{ $field['in_list_view'] ? 'false' : 'true' }})"
                                    @checked($field['in_list_view'])
                                    class="rounded border-gray-300 text-violet-600 cursor-pointer" />
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                                {{ $field['options'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button wire:click="moveFieldUp({{ $i }})" class="text-gray-400 hover:text-gray-600" title="Move up">
                                        <x-heroicon-m-chevron-up class="w-4 h-4" />
                                    </button>
                                    <button wire:click="moveFieldDown({{ $i }})" class="text-gray-400 hover:text-gray-600" title="Move down">
                                        <x-heroicon-m-chevron-down class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="deleteField('{{ $field['fieldname'] }}')"
                                    wire:confirm="Delete field '{{ $field['label'] }}'?"
                                    class="text-red-400 hover:text-red-600">
                                    <x-heroicon-m-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">
                                No fields yet. Add the first field below.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Add field form --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Add Field</h3>
                <div class="grid grid-cols-12 gap-3 items-end">

                    <div class="col-span-3">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Label *</label>
                        <input wire:model="newFieldLabel" type="text" placeholder="e.g. Customer Name"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent" />
                    </div>

                    <div class="col-span-3">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Field Type *</label>
                        <select wire:model="newFieldType"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                            @foreach ($this->getFieldTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-3">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Options
                            <span class="text-gray-400 font-normal">(Select: A\nB\nC — Link: DocType name)</span>
                        </label>
                        <input wire:model="newFieldOptions" type="text" placeholder="A\nB\nC or Customer"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <div class="col-span-2 flex items-center gap-2 pb-1">
                        <input wire:model="newFieldRequired" type="checkbox" id="req"
                            class="rounded border-gray-300 text-violet-600" />
                        <label for="req" class="text-sm text-gray-600 dark:text-gray-400">Required</label>
                    </div>

                    <div class="col-span-1">
                        <button wire:click="addField"
                            class="w-full bg-violet-600 hover:bg-violet-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition-colors">
                            Add
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
</x-filament-panels::page>
