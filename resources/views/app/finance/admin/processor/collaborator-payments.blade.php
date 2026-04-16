<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }
        
        .main-card {
            background: white;
            border-radius: 2.5rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .card-header-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 3rem; 
            color: white;
        }

        .padding-alinhado {
            padding-left: 3.5rem !important;
            padding-right: 3.5rem !important;
        }

        .category-row {
            background-color: #f1f5f9;
            padding: 0.5rem 3.5rem;
            font-size: 12px;
            font-weight: 900;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .row-intercalada:nth-child(even) {
            background-color: #f8fafc;
        }

        .btn-liquidar-clean {
            background: #0f172a;
            color: white !important;
            border-radius: 1rem;
            transition: all 0.2s ease;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 10px;
        }

        .btn-liquidar-clean:hover {
            background: #000;
            transform: translateY(-2px);
        }

        .v-align-middle {
            vertical-align: middle !important;
        }
    </style>

    <div class="max-w-7xl mx-auto p-12" x-data="{ 
        openModal: false, 
        walletId: null, 
        pixKey: '', 
        balance: '', 
        collaboratorName: '' 
    }">

        <template x-teleport="body">
            <div x-show="openModal" 
                class="fixed inset-0 z-[9999] flex items-center justify-center"
                style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;"
                x-cloak>
                
                <div class="absolute inset-0 bg-slate-900/60" 
                    style="backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);"
                    x-transition.opacity>
                </div>
                
                <div @click.away="openModal = false" 
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="relative bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 m-4">
                    
                    <div class="card-header-gradient p-8 text-center">
                        <h3 class="text-xl font-light text-white">Confirmar <span class="font-black">Pagamento</span></h3>
                        <p class="opacity-60 text-xs mt-2 uppercase tracking-widest text-white" x-text="collaboratorName"></p>
                    </div>

                    <div class="p-8 space-y-6 bg-white">
                        <div class="text-center">
                            <p class="text-[10px] uppercase font-black tracking-widest text-slate-400 mb-1">Valor a Liquidar</p>
                            <p class="text-4xl font-black text-slate-900">R$ <span x-text="balance"></span></p>
                        </div>

                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                            <p class="text-[10px] uppercase font-black tracking-widest text-slate-400 mb-2">Chave PIX</p>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-sm text-slate-700 break-all select-all font-bold" x-text="pixKey"></span>
                            </div>
                        </div>

                        <form :action="'/admin/finance/processor/pay-wallet/' + walletId" method="POST" class="flex flex-col gap-3">
                            @csrf
                            <button type="submit" class="w-full btn-liquidar-clean py-4 text-xs shadow-lg shadow-slate-900/20">
                                Confirmar que realizei o pagamento
                            </button>
                            
                            <button type="button" @click="openModal = false" class="w-full py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-red-600 transition-colors">
                                Cancelar e Voltar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </template>

        <div class="main-card">
            <div class="card-header-gradient flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <h2 class="text-3xl font-extralight tracking-tight">Saldos de <span class="font-black">Carteira</span></h2>
                    <p class="opacity-50 text-sm mt-1">Agrupados por ordem alfabética.</p>
                </div>

                <div class="text-right">
                    <p class="text-[10px] uppercase font-black tracking-widest opacity-40 mb-1">Total Geral</p>
                    <div class="flex items-baseline justify-end">
                        <span class="text-xl font-light opacity-60 mr-2">R$</span>
                        <span class="text-4xl font-black tracking-tighter">
                            {{ number_format($wallets->sum('balance'), 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body-content">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-white">
                            <th class="py-6 text-left text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] padding-alinhado">Colaborador</th>
                            <th class="py-6 text-center text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Saldo</th>
                            <th class="py-6 text-center text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Atualização</th>
                            <th class="py-6 text-right padding-alinhado"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $groupedWallets = $wallets->groupBy(function($item) {
                                return strtoupper(substr($item->collaborator->name, 0, 1));
                            })->sortKeys();
                        @endphp

                        @forelse($groupedWallets as $letter => $items)
                            <tr>
                                <td colspan="4" class="category-row">{{ $letter }}</td>
                            </tr>

                            @foreach($items as $wallet)
                            <tr class="row-intercalada hover:bg-slate-100/50 transition-colors border-b border-slate-50">
                                <td class="py-6 v-align-middle padding-alinhado">
                                    <div class="flex flex-col">
                                        <span class="text-base font-bold text-slate-900 leading-tight">{{ $wallet->collaborator->name ?? 'N/A' }}</span>
                                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-1">ID #{{ $wallet->collaborator->id ?? '0' }}</span>
                                    </div>
                                </td>

                                <td class="py-6 text-center v-align-middle">
                                    <span class="inline-block px-4 py-2 rounded-xl text-sm font-black bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        R$ {{ number_format($wallet->balance, 2, ',', '.') }}
                                    </span>
                                </td>

                                <td class="py-6 text-center v-align-middle">
                                    <div class="flex flex-col items-center">
                                        <span class="text-sm font-bold text-slate-700">{{ $wallet->updated_at->format('d/m/Y') }}</span>
                                        <span class="text-[10px] text-slate-400 font-bold uppercase mt-0.5">{{ $wallet->updated_at->format('H:i') }}</span>
                                    </div>
                                </td>

                                <td class="py-6 text-right v-align-middle padding-alinhado">
                                    <button type="button" 
                                        @click="
                                            openModal = true; 
                                            walletId = '{{ $wallet->collaborator->id }}'; {{-- MUDANÇA AQUI --}}
                                            pixKey = '{{ $wallet->collaborator->pix_key ?? 'Chave não cadastrada' }}'; 
                                            balance = '{{ number_format($wallet->balance, 2, ',', '.') }}';
                                            collaboratorName = '{{ $wallet->collaborator->name }}';
                                        "
                                        class="btn-liquidar-clean px-6 py-3 shadow-sm">
                                        Liquidar
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="4" class="py-24 text-center text-slate-300 font-bold uppercase text-xs tracking-widest">
                                    Sem pagamentos pendentes
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>