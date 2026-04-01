<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Registered DocTypes</x-slot>
        <x-slot name="description">All DocTypes currently registered in the Pascal Platform registry</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="pb-3 text-left font-medium text-gray-600 dark:text-gray-300">DocType</th>
                        <th class="pb-3 text-left font-medium text-gray-600 dark:text-gray-300">Module</th>
                        <th class="pb-3 text-left font-medium text-gray-600 dark:text-gray-300">Table</th>
                        <th class="pb-3 text-center font-medium text-gray-600 dark:text-gray-300">Records</th>
                        <th class="pb-3 text-center font-medium text-gray-600 dark:text-gray-300">Submittable</th>
                        <th class="pb-3 text-center font-medium text-gray-600 dark:text-gray-300">Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @foreach ($this->getRegisteredDocTypes() as $dt)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                            <td class="py-3 font-semibold text-violet-600 dark:text-violet-400">
                                {{ $dt['name'] }}
                            </td>
                            <td class="py-3 text-gray-500">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800">
                                    {{ $dt['module'] }}
                                </span>
                            </td>
                            <td class="py-3 font-mono text-xs text-gray-500">{{ $dt['table'] }}</td>
                            <td class="py-3 text-center font-mono text-sm">{{ number_format($dt['count']) }}</td>
                            <td class="py-3 text-center">
                                @if ($dt['is_submittable'])
                                    <x-heroicon-m-check-circle class="w-4 h-4 text-green-500 inline" />
                                @else
                                    <x-heroicon-m-minus class="w-4 h-4 text-gray-300 inline" />
                                @endif
                            </td>
                            <td class="py-3 text-center">
                                @if ($dt['track_changes'])
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
    </x-filament::section>
</x-filament-widgets::widget>
