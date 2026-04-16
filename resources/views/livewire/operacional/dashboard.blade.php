<div> 
    <style>
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #e5e7eb !important;
            border-radius: 0.75rem !important;
            min-height: 48px !important;
            padding: 4px 12px !important;
            transition: all 0.2s;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #3b82f6 !important;
            ring: 2px;
            ring-color: #dbeafe;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    </style>

    {{-- Header de Contexto --}}
    <div class="max-w-4xl mx-auto mb-6">
        <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight">Gestão Financeira</h2>
        <p class="text-gray-500 text-sm">Gerencie seus saldos e registre novos gastos com precisão.</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($myCostCenters as $center)
            <label class="relative bg-white p-5 rounded-2xl shadow-sm border-2 transition-all cursor-pointer block
                {{ $selectedCostCenter == $center->id ? 'border-blue-500 bg-blue-50/30' : 'border-gray-100' }}">
                
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">
                        {{ $center->name }}
                    </span>
                    
                    <input type="radio"
                        name="cost_center_choice"
                        value="{{ $center->id }}"
                        wire:model.live="selectedCostCenter"
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded-full focus:ring-blue-500">
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <span class="block text-[9px] font-bold text-blue-600 uppercase mb-1 ml-1">Saldo disponível</span>
                        <div class="text-lg font-black text-blue-700">
                            R$ {{ number_format($center->balance, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
                
                @if($selectedCostCenter == $center->id)
                    <div class="absolute -top-2 -right-2 bg-blue-600 text-white text-[10px] px-2 py-1 rounded-lg font-bold shadow-sm">
                        SELECIONADO
                    </div>
                @endif
            </label>
        @endforeach
    </div>
    {{-- Exibe erro de validação para o usuário --}}
    @error('selectedCostCenter') 
        <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> 
    @enderror


    {{-- Separador com Gradiente --}}
    <div class="max-w-4xl mx-auto relative h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent my-10"></div>
    
    {{-- Formulário de Registro --}}
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden">
            <div class="bg-gray-50/50 px-8 py-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">Registrar Novo Gasto</h3>
            </div>

            <div class="p-8">
                @if (session()->has('message'))
                    <div class="mb-8 flex items-center gap-3 p-4 bg-emerald-50 text-emerald-700 rounded-2xl border border-emerald-100 animate-pulse">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 m0 0l4-4m-4 4L5 9"></path></svg>
                        <span class="font-medium">{{ session('message') }}</span>
                    </div>
                @endif

                <form wire:submit.prevent="store" class="space-y-6">
                    
                    {{-- Seletor de Método de Pagamento --}}
                    <div class="grid grid-cols-2 gap-4 p-2 bg-gray-100 rounded-2xl mb-8">
                        <button type="button" 
                            wire:click="$set('payment_method', 'pix')"
                            class="flex items-center justify-center gap-3 py-4 rounded-2xl font-black transition-all border-2 
                            {{ $payment_method === 'pix' ? 'bg-emerald-50 border-emerald-500 text-emerald-700 shadow-sm' : 'bg-transparent border-transparent text-gray-500 hover:bg-gray-100' }}">
                            
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" 
                                class="{{ $payment_method === 'pix' ? 'stroke-emerald-700' : 'stroke-gray-500' }}" 
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                                <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                                <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                                <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                                <rect x="7" y="7" width="3" height="3"></rect>
                                <rect x="14" y="7" width="3" height="3"></rect>
                                <rect x="7" y="14" width="3" height="3"></rect>
                                <path d="M14 14h3v3h-3z"></path>
                            </svg>

                            PAGAMENTO EXTERNO
                        </button>
                        
                        <button type="button" 
                            wire:click="$set('payment_method', 'wallet_transfer')"
                            class="flex items-center justify-center gap-3 py-4 rounded-xl font-black transition-all border-2 {{ $payment_method === 'wallet_transfer' ? 'bg-blue-50 border-blue-500 text-blue-700 shadow-sm' : 'bg-transparent border-transparent text-gray-500 hover:bg-gray-200' }}">
                            <i class='bx bx-wallet-alt text-2xl'></i>
                            TRANSFERÊNCIA
                        </button>
                    </div>

                    {{-- Grid Principal Unificado --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                        
                        {{-- Input condicional para Transferência --}}
                        @if($payment_method === 'wallet_transfer')
                            <div class="md:col-span-2 p-5 bg-blue-50/50 rounded-2xl border-2 border-dashed border-blue-200 animate-in slide-in-from-top-2 duration-300" wire:ignore>
                                <label class="block text-xs font-black text-blue-700 uppercase mb-2 ml-1">Colaborador Destino (Carteira)</label>
                                
                                <select id="select-colaborador-destino" class="w-full">
                                    <option value="">Pesquise pelo nome do colaborador...</option>
                                    @foreach($collaborators as $collab)
                                        <option value="{{ $collab->id }}">{{ $collab->name }}</option>
                                    @endforeach
                                </select>
                                
                                <p class="mt-2 text-[10px] text-blue-500 italic font-medium">
                                    <i class='bx bx-info-circle'></i> Esta operação creditará o valor na carteira do colaborador selecionado.
                                </p>
                            </div>
                        @endif

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Descrição do Gasto</label>
                            <input type="text" wire:model="description" 
                                class="w-full px-4 py-3 rounded-xl border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all placeholder-gray-400" 
                                placeholder="Ex: Manutenção preventiva de frota">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Categoria</label>
                            <select wire:model="category_id" class="w-full px-4 py-3 rounded-xl border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all">
                                <option value="">Selecione...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Valor (R$)</label>
                                <input type="number" step="0.01" wire:model="value" 
                                    class="w-full px-4 py-3 rounded-xl border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all font-bold text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Data</label>
                                <input type="date" wire:model="date" 
                                    class="w-full px-4 py-3 rounded-xl border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all">
                            </div>
                        </div>

                        {{-- Divisão de Custos --}}
                        <div class="md:col-span-2 mt-2" wire:ignore>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">
                                Dividir custo com outros Líderes?
                            </label>
                            <select id="select2-lideres" class="w-full" multiple="multiple">
                                @foreach($leaders as $leader)
                                    <option value="{{ $leader->id }}">{{ $leader->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-[10px] text-gray-400 italic">
                                * O valor será dividido igualmente entre você e os selecionados.
                            </p>
                        </div>

                        {{-- Footer do Formulário --}}
                        <div class="md:col-span-2 flex items-center justify-between pt-6 border-t border-gray-50 mt-4">
                            <p class="text-[10px] text-gray-400 italic">Preenchimento obrigatório em todos os campos.</p>
                            <button type="submit" class="group flex items-center gap-2 bg-blue-600 text-white px-10 py-4 rounded-2xl font-black hover:bg-blue-700 hover:shadow-xl hover:shadow-blue-200 transition-all active:scale-95">
                                <i class='bx bx-check-double text-2xl'></i>
                                SALVAR REGISTRO
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function initSelect2() {
            $('#select2-lideres').select2({
                placeholder: "Selecione os líderes...",
                allowClear: true,
                width: '100%'
            }).on('change', function () {
                @this.set('selectedLeaders', $(this).val());
            });

            $('#select-colaborador-destino').select2({
                placeholder: "Digite o nome para pesquisar...",
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() { return "Nenhum colaborador encontrado"; }
                }
            }).on('change', function () {
                @this.set('target_user_id', $(this).val());
            });
        }

        document.addEventListener('livewire:initialized', () => {
            initSelect2();
            
            Livewire.hook('morph.updated', (el, component) => {
                initSelect2();
            });
        });

        window.addEventListener('reset-select2', event => {
            $('#select2-lideres').val(null).trigger('change');
            $('#select-colaborador-destino').val(null).trigger('change');
        });

        document.addEventListener('livewire:navigated', initSelect2);
    </script>
</div>