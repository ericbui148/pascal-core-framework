<x-filament-panels::page>
    <div class="space-y-4">

        {{-- Filters --}}
        <div class="flex gap-3 flex-wrap">
            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model.live="doctype"
                    placeholder="Filter by DocType (e.g. User)"
                />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model.live="docname"
                    placeholder="Filter by record name"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">When</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">DocType</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Record</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Action</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">User</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">IP</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($this->getAuditLogs() as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($log['created_at'])->diffForHumans() }}
                                <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($log['created_at'])->format('M d H:i') }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-violet-600 dark:text-violet-400">
                                {{ $log['doctype'] }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ $log['docname'] }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $colors = [
                                        'create' => 'bg-green-100 text-green-700',
                                        'update' => 'bg-blue-100 text-blue-700',
                                        'delete' => 'bg-red-100 text-red-700',
                                        'submit' => 'bg-violet-100 text-violet-700',
                                        'cancel' => 'bg-orange-100 text-orange-700',
                                    ];
                                    $color = $colors[$log['action']] ?? 'bg-gray-100 text-gray-700';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
                                    {{ strtoupper($log['action']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                {{ $log['user_email'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-gray-500">
                                {{ $log['ip_address'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($log['diff'])
                                    @php $diff = json_decode($log['diff'], true) @endphp
                                    <div class="text-xs text-gray-500 space-y-0.5">
                                        @foreach (array_keys($diff) as $field)
                                            @if (!in_array($field, ['password', 'remember_token', 'updated_at']))
                                                <div class="font-mono">{{ $field }}</div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                No audit log entries found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
