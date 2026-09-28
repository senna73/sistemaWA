<x-app-layout>
    <style>
        .batches-page .page-header {
            margin-bottom: 1.5rem;
        }

        .batches-page .page-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .batches-page .page-header p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .batches-page .card-surface {
            background: #fff;
            border: 1px solid #f3f4f6;
            border-radius: 16px;
            overflow: hidden;
        }

        .batches-page .history-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .batches-page .history-header h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #374151;
            margin: 0;
        }

        .batches-page .history-header p {
            margin: 0.15rem 0 0;
            font-size: 0.8rem;
            color: #9ca3af;
        }

        .batches-pagination {
            padding: 0.75rem 1.25rem;
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }

        .batches-pagination .pagination {
            margin: 0;
            gap: 0.25rem;
        }

        .batches-pagination .page-link {
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            font-size: 0.8125rem;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.4rem;
        }

        .batches-pagination .page-item .page-link svg,
        .batches-pagination svg {
            width: 14px !important;
            height: 14px !important;
        }

        .batches-pagination nav[role="navigation"] > div:first-child {
            display: none;
        }

        .batches-pagination nav[role="navigation"] > div:last-child {
            display: flex !important;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
        }

        .batches-pagination p {
            margin: 0;
            font-size: 0.8rem;
        }
    </style>

    <div class="batches-page">
        <div class="page-header d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-2">
            <div>
                <h1>Gestão de Lotes Financeiros</h1>
                <p>Monitore e processe os fechamentos de períodos.</p>
            </div>
        </div>

        <div class="card-surface mb-4">
            @include('app.finance.admin.batches.create')
        </div>

        <div class="card-surface mb-4">
            <x-finance.batch-stats :batches="$batches" />
        </div>

        <div class="card-surface">
            <div class="history-header">
                <div>
                    <h2>Histórico de Lotes</h2>
                    <p>Consulte o detalhamento de todos os períodos processados</p>
                </div>
            </div>

            <x-finance.batch-table :batches="$batches" />

            @if ($batches->hasPages())
                <div class="batches-pagination">
                    {{ $batches->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
