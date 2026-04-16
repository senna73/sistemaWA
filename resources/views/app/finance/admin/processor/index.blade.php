<x-app-layout>
    <style>
        .master-card {
            background: white;
            border-radius: 2.5rem;
            box-shadow: 0 40px 100px rgba(15, 23, 42, 0.08);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .header-section {
            background: #0f172a;
            padding: 4rem 3.5rem;
            color: white;
            text-align: center;
        }

        .options-container {
            display: grid;
            grid-template-cols: 1fr;
        }

        @media (min-width: 768px) {
            .options-container {
                grid-template-columns: 1fr 1fr;
            }
            .divider-vertical {
                border-right: 1px solid #f1f5f9;
            }
        }

        .option-box {
            padding: 4rem 3.5rem;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .option-box:hover {
            background-color: #f8fafc;
        }

        .icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .btn-access {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            border-radius: 1rem;
            background: #0f172a;
            color: white !important;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            transition: all 0.2s;
            margin-top: 2rem;
        }

        .btn-access:hover {
            transform: scale(1.05);
            background: #000;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
    </style>

    <div class="min-h-screen bg-[#fcfcfd] py-20 px-6">
        <div class="max-w-5xl mx-auto">
            
            <div class="master-card">
                <div class="header-section">
                    <span class="text-[10px] font-black uppercase tracking-[0.4em] opacity-40 mb-3 block">Financeiro / Liquidação</span>
                    <h1 class="text-4xl font-black tracking-tight italic">Centro de <span class="opacity-40">Operações</span></h1>
                </div>

                <div class="options-container">
                    
                    <div class="option-box divider-vertical">
                        <div>
                            <div class="icon-circle bg-blue-50 text-blue-600">
                                <i class="bx bx-user-circle"></i>
                            </div>
                            <div class="flex items-center mb-4">
                                <span class="status-dot bg-blue-500 animate-pulse"></span>
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest">{{ $countCollaborators ?? 0 }} Carteiras</span>
                            </div>
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-3">Colaboradores</h2>
                            <p class="text-slate-500 text-sm leading-relaxed">
                                Processamento de saldos acumulados, bônus e diárias de serviço. Interface para zerar débitos individuais.
                            </p>
                        </div>
                        <a href="{{ route('admin.finance.processor.collaborators') }}" class="btn-access">
                            Abrir Carteiras <i class="bx bx-right-arrow-alt ml-2 text-lg"></i>
                        </a>
                    </div>

                    <div class="option-box">
                        <div>
                            <div class="icon-circle bg-emerald-50 text-emerald-600">
                                <i class="bx bx-qr-scan"></i>
                            </div>
                            <div class="flex items-center mb-4">
                                <span class="status-dot bg-emerald-500"></span>
                                <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">{{ $countPixPending ?? 0 }} Pendentes</span>
                            </div>
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-3">Custos Operacionais</h2>
                            <p class="text-slate-500 text-sm leading-relaxed">
                                Liquidação de comprovantes via PIX para manutenção, combustível e insumos emergenciais.
                            </p>
                        </div>
                        <a href="{{ route('admin.finance.processor.pix') }}" class="btn-access">
                            Processar PIX <i class="bx bx-right-arrow-alt ml-2 text-lg"></i>
                        </a>
                    </div>

                </div>

                <div class="bg-slate-50 p-6 text-center border-t border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center justify-center">
                        <i class="bx bx-lock-alt mr-2 text-base"></i> Transações protegidas e auditadas pelo sistema central
                    </p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>