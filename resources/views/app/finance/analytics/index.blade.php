<x-app-layout>
    <div class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Analytics & Insights</h2>
                    <p class="text-sm text-gray-500">Acompanhando de {{ $start->format('d/m') }} até {{ $end->format('d/m') }}</p>
                </div>

                <form action="{{ route('analytics.index') }}" method="GET" class="flex items-center gap-2 bg-white p-2 rounded-xl shadow-sm border border-gray-100">
                    <input type="date" name="start_date" value="{{ $start->format('Y-m-d') }}" class="text-xs border-none focus:ring-0 rounded-lg">
                    <span class="text-gray-300">|</span>
                    <input type="date" name="end_date" value="{{ $end->format('Y-m-d') }}" class="text-xs border-none focus:ring-0 rounded-lg">
                    <button type="submit" class="bg-indigo-600 p-2 rounded-lg text-white hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Ticket Médio</p>
                    <p class="text-2xl font-black text-gray-900">R$ {{ number_format($systemAverage, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Colaboradores Ativos</p>
                    <p class="text-2xl font-black text-gray-900">{{ $activeCount }}</p>
                </div>
                <div class="bg-indigo-600 p-6 rounded-2xl shadow-lg text-white">
                    <p class="text-xs font-bold uppercase tracking-wider opacity-80 mb-1">Top Performance</p>
                    <p class="text-xl font-bold">{{ $topPerformer->name ?? 'Nenhum registro' }}</p>
                    <p class="text-sm opacity-90">{{ $topPerformer->total_diarias ?? 0 }} diárias no período</p>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                @include('app.finance.analytics.collaborators')
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</x-app-layout>