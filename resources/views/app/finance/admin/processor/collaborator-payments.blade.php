<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }
        
        /* Estrutura Principal */
        .main-card { background: white; border-radius: 2rem; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.04); border: 1px solid #f1f5f9; overflow: hidden; }
        .card-header-gradient { background: #0f172a; padding: 2.5rem 3.5rem; color: white; }
        .padding-alinhado { padding-left: 3.5rem !important; padding-right: 3.5rem !important; }
        
        /* Cores de Categoria e Linhas */
        .category-row { background-color: #f8fafc; padding: 0.5rem 3.5rem; font-size: 11px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; border-y: 1px solid #f1f5f9; }
        .row-intercalada:nth-child(even) { background-color: #fafbfc; }
        
        /* Botão de Expansão */
        .btn-liquidar-sm { background: #0f172a; color: white !important; border-radius: 0.75rem; transition: all 0.2s ease; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 10px; padding: 0.6rem 1.2rem; cursor: pointer; border: none; }
        .btn-liquidar-sm:hover { background: #1e293b; transform: translateY(-1px); }
        
        /* Botão de Confirmação */
        .btn-confirmar-pix { background: #10b981; color: white !important; border-radius: 1rem; transition: all 0.2s ease; font-weight: 800; text-transform: uppercase; font-size: 12px; padding: 1.25rem 2.5rem; cursor: pointer; border: none; letter-spacing: 0.05em; }
        .btn-confirmar-pix:hover { background: #059669; transform: scale(1.02); box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4); }

        /* Estilos de Valor na Listagem */
        .badge-pagamento-verde { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 0.5rem 1rem; border-radius: 0.75rem; font-weight: 800; font-size: 13px; display: inline-block; }
        .texto-saldo-cinza { color: #94a3b8; font-weight: 600; font-size: 13px; }

        /* Card de Cópia Pix */
        .pix-copy-card { background: #ffffff; border: 2px dashed #cbd5e1; border-radius: 1.25rem; transition: all 0.2s ease; cursor: pointer; position: relative; overflow: hidden; }
        .pix-copy-card:hover { border-color: #10b981; background: #f0fdf4; border-style: solid; }
        
        /* Fundo especial para a sanfona aberta */
        .sanfona-ativa { box-shadow: inset 0 4px 6px -4px rgba(0, 0, 0, 0.05), inset 0 -4px 6px -4px rgba(0, 0, 0, 0.05); }
    </style>

    <div class="max-w-7xl mx-auto p-10" x-data="{ expandedId: null, copied: false }">

        <div class="main-card">
            <div class="card-header-gradient flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-light tracking-tight">Processador de <span class="font-black">Pagamentos</span></h2>
                    <p class="opacity-40 text-[10px] mt-1 uppercase font-bold tracking-[0.2em]">Competência: {{ $start->format('d/m') }} — {{ $end->format('d/m/y') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] uppercase font-bold opacity-40 mb-1">Total a ser pago</p>
                    <p class="text-3xl font-black text-emerald-400">R$ {{ number_format($wallets->sum('period_credits_sum'), 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="py-6 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest padding-alinhado">Colaborador</th>
                            <th class="py-6 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Valor a Pagar</th>
                            <th class="py-6 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Saldo em Conta</th>
                            <th class="py-6 padding-alinhado"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $groupedWallets = $wallets->groupBy(fn($item) => strtoupper(substr($item->collaborator->name, 0, 1)))->sortKeys();
                        @endphp

                        @forelse($groupedWallets as $letter => $items)
                            <tr><td colspan="4" class="category-row">{{ $letter }}</td></tr>
                            
                            @foreach($items as $wallet)
                            <tr class="row-intercalada border-b border-slate-50 transition-colors hover:bg-slate-50/80">
                                <td class="py-5 padding-alinhado">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-slate-700">{{ $wallet->collaborator->name }}</span>
                                        <span class="text-[9px] text-slate-400 font-bold uppercase">ID #{{ $wallet->collaborator->id }}</span>
                                    </div>
                                </td>

                                <td class="py-5 text-center">
                                    <span class="badge-pagamento-verde shadow-sm">
                                        R$ {{ number_format($wallet->period_credits_sum ?? 0, 2, ',', '.') }}
                                    </span>
                                </td>

                                <td class="py-5 text-center">
                                    <span class="texto-saldo-cinza">
                                        R$ {{ number_format($wallet->balance ?? 0, 2, ',', '.') }}
                                    </span>
                                </td>

                                <td class="py-5 text-right padding-alinhado">
                                    <button type="button" @click="expandedId = (expandedId == '{{ $wallet->id }}' ? null : '{{ $wallet->id }}')" class="btn-liquidar-sm shadow-sm">
                                        <span x-text="expandedId == '{{ $wallet->id }}' ? 'Fechar' : 'Liquidar'"></span>
                                    </button>
                                </td>
                            </tr>

                            {{-- PAINEL DE LIQUIDAÇÃO (SANFONA) --}}
                            <tr x-show="expandedId == '{{ $wallet->id }}'" x-collapse x-cloak>
                                <td colspan="4" class="bg-slate-50 border-b border-slate-200" style="padding: 0 !important;">
                                    
                                    <div class="w-full py-16 d-flex flex-column align-items-center justify-content-center text-center">
                                        
                                        <div class="w-full max-w-3xl px-6">
                                            
                                            {{-- Identificação --}}
                                            <div class="mb-4">
                                                <p class="text-slate-400 text-[11px] uppercase font-black tracking-[0.4em] mb-2">Pagamento para</p>
                                                <h3 class="text-3xl font-bold text-slate-800 tracking-tight">
                                                    {{ $wallet->collaborator->name }}
                                                </h3>
                                            </div>

                                            {{-- Container da Chave PIX --}}
                                            <div class="d-flex flex-column align-items-center mt-8">
                                                @php
                                                    $rawKey = $wallet->collaborator->pix_key ?? '';
                                                    $formattedKey = $rawKey;

                                                    // Formatação para destaque visual
                                                    if (strlen($rawKey) == 11 && is_numeric($rawKey)) {
                                                        $formattedKey = substr($rawKey, 0, 3) . '.' . substr($rawKey, 3, 3) . '.' . substr($rawKey, 6, 3) . '-' . substr($rawKey, 9);
                                                    } elseif (strlen($rawKey) == 13 && str_starts_with($rawKey, '55')) {
                                                        $formattedKey = '(' . substr($rawKey, 2, 2) . ') ' . substr($rawKey, 4, 5) . '-' . substr($rawKey, 9);
                                                    }
                                                @endphp

                                                <div @click="navigator.clipboard.writeText('{{ $rawKey }}'); 
                                                            copied = '{{ $wallet->id }}'; 
                                                            setTimeout(() => copied = false, 2000)" 
                                                    :class="{ 'bg-emerald-50 border-emerald-400': copied === '{{ $wallet->id }}' }"
                                                    class="inline-flex items-center gap-6 px-10 py-5 border-2 border-slate-300 rounded-2xl cursor-pointer transition-colors duration-200 group relative overflow-hidden"
                                                    style="background-color: #ffffff; border-style: dashed !important; min-width: 480px; justify-content: space-between;">
                                                    
                                                    <div class="flex flex-col items-start text-left">
                                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Chave PIX para transferência</span>
                                                        
                                                        {{-- CHAVE PIX: Negrito máximo e 15% maior que o texto padrão --}}
                                                        <span class="font-mono text-slate-900 tracking-tight" 
                                                            style="font-size: 1.15em !important; font-weight: 900 !important; display: block;">
                                                            {{ $formattedKey ?? 'SEM CHAVE PIX' }}
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="flex flex-col items-center border-l-2 border-slate-100 ps-6">
                                                        <span x-show="copied !== '{{ $wallet->id }}'" class="text-[11px] font-black text-emerald-600 uppercase tracking-widest">
                                                            Copiar
                                                        </span>
                                                    </div>

                                                    {{-- Overlay de Feedback (Piscada de cor ao copiar) --}}
                                                    <div x-show="copied === '{{ $wallet->id }}'" 
                                                        x-transition.opacity
                                                        class="absolute inset-0 bg-emerald-600 d-flex align-items-center justify-center text-white font-black text-sm tracking-[0.3em]"
                                                        style="z-index: 20;">
                                                        Copiado!
                                                    </div>
                                                </div>
                                            </div>

                                            <style>
                                                /* O "piscar" agora é apenas visual através da classe dinâmica do Alpine */
                                                .transition-colors {
                                                    transition: all 0.2s ease-in-out;
                                                }
                                            </style>


                                            {{-- Botão Centralizado --}}
                                            <div class="d-flex justify-content-center w-full mt-4">
                                                <form action="/admin/finance/processor/pay-wallet/{{ $wallet->id }}" method="POST" class="w-full" style="max-width: 450px;">
                                                    @csrf
                                                    <input type="hidden" name="amount" value="{{ $wallet->period_credits_sum }}">
                                                    
                                                    <button type="submit" class="btn-confirmar-pix w-full !py-4 shadow-2xl !text-sm !font-[900]">
                                                        Confirmar Pagamento Realizado
                                                    </button>
                                                </form>
                                            </div>
                                            
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="4" class="py-20 text-center text-slate-300 font-bold uppercase text-[10px] tracking-[0.3em]">Nenhum pagamento pendente no período</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>