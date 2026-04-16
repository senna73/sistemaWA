<x-app-layout>
    <div class="container mx-auto px-4 py-8">
        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Gestão de Lotes Financeiros</h1>
                <p class="text-sm text-gray-500">Monitore e processe os fechamentos de períodos.</p>
            </div>
            <div class="bg-white p-2 rounded-xl shadow-sm border border-gray-100">
                @include('app.finance.admin.batches.create') 
            </div>
        </div>

        {{-- Componente de Resumo --}}
        <x-finance.batch-stats :batches="$batches" />

        {{-- Card de Listagem --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-6" style="width: 100%; border-radius: 16px;">
            
            {{-- Header do Card: Centralizado e com espaçamento --}}
            <div style="padding: 24px; border-bottom: 1px solid #f9fafb; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; position: relative;">
                
                <h2 style="font-weight: 700; color: #374151; font-size: 1.25rem; margin: 0;">
                    Histórico de Lotes
                </h2>
                
                <p style="font-size: 0.875rem; color: #9ca3af; margin-top: 4px;">
                    Consulte o detalhamento de todos os períodos processados
                </p>

                {{-- Botão de filtro posicionado no canto, para não atrapalhar a centralização do texto --}}
                <div style="position: absolute; right: 24px; top: 50%; transform: translateY(-50%);">
                    <button class="text-gray-400 hover:text-gray-600 transition-colors" style="background: none; border: none; cursor: pointer;">
                        <i class='bx bx-filter-alt text-xl'></i>
                    </button>
                </div>
            </div>
            
            {{-- Container da Tabela --}}
            <div style="width: 100%; overflow-x: auto;">
                <x-finance.batch-table :batches="$batches" />
            </div>

            @if($batches->hasPages())
                <div class="px-6 py-4 bg-gray-50/30 border-t border-gray-50">
                    {{ $batches->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>