<x-app-layout>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            }
        }
    </script>
    <div x-data="{ tab: $persist('dashboard') }" class="min-h-screen bg-gray-50">        
        {{-- Navbar --}}
        <nav class="sticky top-0 z-50 w-full bg-white border-b border-gray-200 shadow-md">
            <div class="max-w-7xl mx-auto">
                <div class="flex h-14">
                    <div class="flex w-full">
                        <button 
                            @click="tab = 'dashboard'" 
                            :class="tab === 'dashboard' ? 'text-blue-600 border-blue-600 bg-blue-50/50' : 'text-gray-400 border-transparent hover:text-gray-600 hover:bg-gray-50'"
                            class="flex-1 inline-flex items-center justify-center px-4 border-b-[3px] text-[11px] font-black uppercase tracking-[0.15em] transition-all duration-200">
                            Dashboard
                        </button>

                        <button 
                            @click="tab = 'historico'" 
                            :class="tab === 'historico' ? 'text-blue-600 border-blue-600 bg-blue-50/50' : 'text-gray-400 border-transparent hover:text-gray-600 hover:bg-gray-50'"
                            class="flex-1 inline-flex items-center justify-center px-4 border-x border-gray-100 border-b-[3px] text-[11px] font-black uppercase tracking-[0.15em] transition-all duration-200">
                            Histórico
                        </button>

                        <button 
                            @click="tab = 'solicitacoes'" 
                            :class="tab === 'solicitacoes' ? 'text-blue-600 border-blue-600 bg-blue-50/50' : 'text-gray-400 border-transparent hover:text-gray-600 hover:bg-gray-50'"
                            class="flex-1 inline-flex items-center justify-center px-4 border-b-[3px] text-[11px] font-black uppercase tracking-[0.15em] transition-all duration-200">
                            Solicitações
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Abas --}}
        <main class="py-8">
            <div class="max-w-7xl mx-auto px-4">
                
                {{-- Aba: Dashboard --}}
                <div x-show="tab === 'dashboard'" x-cloak>
                    <livewire:operacional.dashboard />
                </div>

                {{-- Aba: Histórico --}}
                <div x-show="tab === 'historico'" x-cloak>
                    <livewire:operacional.historico />
                </div>

                {{-- Aba: Solicitações --}}
                <div x-show="tab === 'solicitacoes'" x-cloak>
                    <livewire:operacional.solicitacoes />
                </div>

            </div>
        </main>
    </div>
</x-app-layout>
<style>
    .custom-pagination nav > div:first-child { display: none; }
    
    .custom-pagination svg {
        width: 1.25rem !important;
        height: 1.25rem !important;
        display: inline-block !important;
        vertical-align: middle;
    }

    .custom-pagination span[aria-current="page"] span {
        background-color: #3b82f6 !important;
        color: white !important;
    }

    [x-cloak] { display: none !important; }

    nav.sticky { backdrop-filter: blur(8px); background-color: rgba(255, 255, 255, 0.9); }
</style>