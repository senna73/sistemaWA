<x-app-layout>
    <style>
        .main-card {
            background: white;
            border-radius: 2.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .card-header-pix {
            background: #064e3b;
            padding: 4rem 4.5rem;
            color: white;
        }

        .category-header {
            background: #f8fafc;
            padding: 0.8rem 4.5rem;
            font-size: 11px;
            font-weight: 900;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.25em;
            border-bottom: 1px solid #f1f5f9;
        }

        .padding-alinhado {
            padding-left: 4.5rem !important;
            padding-right: 4.5rem !important;
        }

        .btn-liquidar-pix {
            background: #10b981;
            color: white !important;
            border-radius: 1.2rem;
            padding: 0.9rem 2rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 11px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-liquidar-pix:hover {
            background: #059669;
            transform: scale(1.03);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }

        .cost-badge {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 0.3rem 0.8rem;
            border-radius: 0.6rem;
            background: #f1f5f9;
            color: #475569;
            display: inline-block;
        }
    </style>

    <div class="max-w-7xl mx-auto p-12">
        <div class="main-card">
            
            <div class="card-header-pix flex justify-between items-center">
                <div>
                    <h2 class="text-4xl font-black tracking-tighter italic">Custos <span class="opacity-40 font-light not-italic text-3xl ml-1">PIX</span></h2>
                    <p class="text-emerald-400/60 text-[10px] font-bold uppercase tracking-[0.4em] mt-4">Processamento de liquidação direta</p>
                </div>

                <div class="text-right">
                    <p class="text-[10px] uppercase font-black text-emerald-500/70 tracking-widest mb-1">Montante em Espera</p>
                    <div class="text-5xl font-black tracking-tighter">
                        <span class="text-xl font-bold text-emerald-500 mr-1 italic">R$</span>{{ number_format($pendingCosts->sum('value') ?? 0, 2, ',', '.') }}
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] border-b border-slate-100">
                            <th class="py-8 text-left padding-alinhado">Solicitante & Detalhes</th>
                            <th class="py-8 text-center">Valor Bruto</th>
                            <th class="py-8 text-center">Data / Hora</th>
                            <th class="py-8 text-right padding-alinhado">Gerenciamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $groupedCosts = $pendingCosts->groupBy(fn($item) => $item->category->name ?? 'DIVERSOS');
                        @endphp

                        @forelse($groupedCosts as $category => $items)
                            <tr>
                                <td colspan="4" class="category-header">
                                    {{ $category }}
                                </td>
                            </tr>

                            @foreach($items as $cost)
                            <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                <td class="py-8 padding-alinhado">
                                    <div class="flex flex-col">
                                        <span class="text-base font-black text-slate-800 tracking-tight">
                                            {{ $cost->collaborator->name ?? 'Admin / Sistema' }}
                                        </span>
                                        <div class="mt-2">
                                            <span class="cost-badge">{{ $cost->description }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-8 text-center">
                                    <span class="font-black text-slate-900 text-xl tracking-tighter italic">
                                        R$ {{ number_format($cost->value, 2, ',', '.') }}
                                    </span>
                                </td>

                                <td class="py-8 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs font-black text-slate-700 tracking-tight">{{ \Carbon\Carbon::parse($cost->date)->format('d/m/Y') }}</span>
                                        <span class="text-[10px] text-slate-400 font-bold uppercase">{{ \Carbon\Carbon::parse($cost->created_at)->format('H:i') }}h</span>
                                    </div>
                                </td>

                                <td class="py-8 text-right padding-alinhado">
                                    <form action="{{ route('admin.finance.processor.pay-pix', $cost->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-liquidar-pix">
                                            Liberar Pagamento
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="4" class="py-24 text-center">
                                    <span class="text-slate-300 font-black uppercase text-xs tracking-[0.5em]">Nenhum custo pendente</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>