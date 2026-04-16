<?php

namespace App\Http\Controllers\Finance\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DailyRate;
use App\Models\FinancialBatches;
use App\Services\Finance\FechamentoBatchService;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchesController extends Controller
{
    protected $fechamentoService;
    public function __construct(FechamentoBatchService $fechamentoService) 
    {
        $this->fechamentoService = $fechamentoService;
    }

    public function index()
    {
        $query = FinancialBatches::with('company')
            ->select('financial_batches.*')
            ->addSelect(['total_earned' => \App\Models\DailyRate::selectRaw('SUM(earned)')
                ->whereColumn('company_id', 'financial_batches.company_id')
                ->where('active', true)
                ->whereRaw('DATE(daily_rate.start) >= financial_batches.period_start')
                ->whereRaw('DATE(daily_rate.start) <= financial_batches.period_end')
            ])
            ->orderByRaw("
                CASE 
                    WHEN status = 'processing' THEN 0 
                    WHEN status = 'pending' THEN 1 
                    WHEN status = 'completed' THEN 2 
                    ELSE 3 
                END ASC
            ")
            ->orderBy('period_start', 'desc');

        $batches = $query->paginate(10);

        $companies = \App\Models\Company::getActive();
        
        return view('app.finance.admin.batches.index', compact('batches', 'companies'));
    }

    // Tela de criação de novo lote
    public function create()
    {

        return view('app.finance.admin.batches.create');
    }

    public function confirm_receipt(Request $request)
    {
        try {
            $this->fechamentoService->processarRecebimento($request->batch_id);
            return back()->with('success', 'Pagamento confirmado e saldo adicionado ao caixa!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(FinancialBatches $batch) 
    {
        $dailyRates = DailyRate::with(['collaborator', 'leader']) 
            ->where('company_id', $batch->company_id)
            ->where('active', true)
            ->whereBetween('start', [$batch->period_start, $batch->period_end])
            ->get();

        $financeiro = [
            'receita_bruta'    => $dailyRates->sum('earned'),
            'repasse_liquido'  => $dailyRates->sum('pay_amount') - $dailyRates->sum('employee_discount'),
            'comissoes_lider'  => $dailyRates->sum('leader_comission'),
            'custos_operacao'  => $dailyRates->sum('transportation') + $dailyRates->sum('feeding'),
            'impostos_taxas'   => $dailyRates->sum('tax_paid') + $dailyRates->sum('inss_paid'),
        ];

        $financeiro['lucro_real'] = $financeiro['receita_bruta'] - (
            $financeiro['repasse_liquido'] + 
            $financeiro['comissoes_lider'] + 
            $financeiro['custos_operacao'] + 
            $financeiro['impostos_taxas']
        );

        $movColab = $dailyRates->groupBy('collaborator_id')->map(fn($g) => [
            'nome' => $g->first()->collaborator->name ?? 'N/A',
            'valor' => $g->sum('pay_amount') - $g->sum('employee_discount'),
            'tipo' => 'Repasse'
        ]);

        $movLider = $dailyRates->groupBy('user_id')->map(fn($g) => [
            'nome' => $g->first()->leader->name ?? 'N/A',
            'valor' => $g->sum('leader_comission'),
            'tipo' => 'Comissão'
        ]);

        $extratoFinanceiro = $movColab->concat($movLider)->filter(fn($i) => $i['valor'] > 0);

        $company = Company::find($batch->company_id);
        return view('app.finance.admin.batches.show', compact('batch', 'extratoFinanceiro', 'financeiro', 'company'));
    }

    public function store(Request $request)
    {
        if ($request->has('total_amount')) {
            $cleanAmount = str_replace(['.', ','], ['', '.'], $request->total_amount);
            $request->merge(['total_amount' => $cleanAmount]);
        }

        $validated = $request->validate([
            'company_id'   => 'required|exists:companies,id',
            'total_amount' => 'required|numeric|min:0',
            'status'       => 'nullable|in:pending,processing,completed,canceled',
            'metadata'     => 'nullable|array',
            'period_start' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    $lastBatch = DB::table('financial_batches')
                        ->where('company_id', $request->company_id)
                        ->orderBy('period_end', 'desc')
                        ->first();

                    if ($lastBatch) {
                        $lastEnd = Carbon::parse($lastBatch->period_end);
                        $newStart = Carbon::parse($value);
                        $expectedStart = $lastEnd->copy()->addDay();

                        if (!$newStart->isSameDay($expectedStart)) {
                            $fail("Para esta empresa, o novo lote deve iniciar obrigatoriamente em " . $expectedStart->format('d/m/Y') . " (um dia após o último lote).");
                        }
                    }
                    
                },
            ],
            'period_end' => [
                'required',
                'date',
                'after:period_start',
                function ($attribute, $value, $fail) use ($request) {
                    $start = Carbon::parse($request->period_start);
                    $end = Carbon::parse($value);

                    if ($start->diffInDays($end) < 6) {
                        $fail('O período do lote deve ser de pelo menos 7 dias (uma semana).');
                    }

                    $overlap = DB::table('financial_batches')
                        ->where('company_id', $request->company_id)
                        ->where(function ($query) use ($start, $end) {
                            $query->whereBetween('period_start', [$start, $end])
                                ->orWhereBetween('period_end', [$start, $end]);
                        })
                        ->exists();

                    if ($overlap) {
                        $fail('O período selecionado entra em conflito com um lote já registrado.');
                    }
                },
            ],
        ]);

        $validated['remainder_amount'] = $request->remainder_amount ?? 0;

        $batch = FinancialBatches::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Lote financeiro criado com sucesso!',
            'data'    => $batch
        ], 201);
    }

    public function process(Request $request, FechamentoBatchService $service)
    {
        try {
            
            $valorAjuste = (float) $request->input('centro_custo_loja', 0);
            $batch_id = $request->batch_id;

            $service->processarFechamento($batch_id, $valorAjuste);
            
            return redirect()->back()->with('success', 'Lote processado e carteiras atualizadas com sucesso!');
        } catch (\Exception $e) {
            Log::info($e);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}