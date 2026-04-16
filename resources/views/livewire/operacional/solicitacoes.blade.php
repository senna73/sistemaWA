<div class="p-4 md:p-6 bg-white rounded-lg shadow-sm">
    <h2 class="text-xl font-semibold mb-4 text-slate-800">Solicitações de Custos</h2>

    @if (session()->has('message'))
        <div class="p-3 mb-4 text-green-700 bg-green-100 rounded">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="p-3 mb-4 text-red-700 bg-red-100 rounded">{{ session('error') }}</div>
    @endif

    {{-- Visão para Desktop (Tabela) --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b text-slate-500 uppercase text-xs">
                    <th class="py-3 px-4">Descrição</th>
                    <th class="py-3 px-4">Valor</th>
                    <th class="py-3 px-4">Centro de Custo</th>
                    <th class="py-3 px-4 text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($solicitacoes as $item)
                    <tr class="border-b hover:bg-slate-50 transition">
                        <td class="py-3 px-4 text-sm font-medium text-slate-700">
                            {{ $item->cost->description ?? 'Sem descrição' }}
                        </td>
                        <td class="py-3 px-4 text-sm text-slate-600">
                            R$ {{ number_format($item->divided_value, 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-4">
                            <select wire:model="selectedCostCenters.{{ $item->id }}" class="text-sm border-gray-300 rounded-md shadow-sm w-full max-w-[200px]">
                                <option value="">Selecione o Centro</option>
                                @foreach($myCostCenters as $cc)
                                    <option value="{{ $cc->id }}">{{ $cc->name }} (R$ {{ number_format($cc->balance, 2, ',', '.') }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-3 px-4 flex gap-2 justify-center">
                            <button wire:click="decidir({{ $item->id }}, true)" class="px-4 py-1.5 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 text-sm font-medium transition">Aceitar</button>
                            <button wire:click="decidir({{ $item->id }}, false)" class="px-4 py-1.5 bg-rose-600 text-white rounded-md hover:bg-rose-700 text-sm font-medium transition">Rejeitar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-400">Nenhuma solicitação pendente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Visão para Mobile (Cards) --}}
    <div class="md:hidden space-y-4">
        @forelse($solicitacoes as $item)
            <div class="border rounded-xl p-4 bg-slate-50 space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Descrição</span>
                        <p class="text-sm font-bold text-slate-700">{{ $item->cost->description ?? 'Sem descrição' }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Valor</span>
                        <p class="text-sm font-black text-blue-600">R$ {{ number_format($item->divided_value, 2, ',', '.') }}</p>
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block">Centro de Custo</label>
                    <select wire:model="selectedCostCenters.{{ $item->id }}" class="text-sm border-gray-300 rounded-lg shadow-sm w-full bg-white">
                        <option value="">Selecione o Centro</option>
                        @foreach($myCostCenters as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->name }} (R$ {{ number_format($cc->balance, 2, ',', '.') }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <button wire:click="decidir({{ $item->id }}, true)" class="w-full py-3 bg-emerald-600 text-white rounded-lg font-bold text-xs uppercase tracking-wider">Aceitar</button>
                    <button wire:click="decidir({{ $item->id }}, false)" class="w-full py-3 bg-rose-600 text-white rounded-lg font-bold text-xs uppercase tracking-wider">Rejeitar</button>
                </div>
            </div>
        @empty
            <p class="text-center py-8 text-slate-400 italic">Nenhuma solicitação pendente.</p>
        @endforelse
    </div>
</div>