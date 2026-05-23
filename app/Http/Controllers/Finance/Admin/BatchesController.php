<?php

namespace App\Http\Controllers\Finance\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DailyRate;
use App\Models\FinancialBatches;
use App\Services\Finance\FechamentoBatchService;
use Carbon\Carbon;
use App\Models\FinancialBatcheInvoices as Invoice;
use App\Models\FinancialBatcheInvoices;
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
        $request->validate([
            'batch_id' => 'required|exists:financial_batches,id',
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'exists:financial_batch_invoices,id',
        ]);

        try {
            $batch = FinancialBatches::findOrFail($request->batch_id);
            $notasProcessadas = 0;

            foreach ($request->invoice_ids as $invoiceId) {
                $foiPaga = $this->fechamentoService->liquidarNotaFiscal($invoiceId, $batch->id);
                if ($foiPaga) {
                    $notasProcessadas++;
                }
            }

            $totalInvoices = $batch->invoices()->count();
            $paidInvoices = $batch->invoices()->where('received', true)->count();

            if ($totalInvoices === $paidInvoices) {
                $batch->update(['status' => 'completed']);
                return redirect()->back()->with('success', "Todas as notas foram liquidadas e o capital empresarial foi atualizado. Lote finalizado!");
            }

            if ($notasProcessadas > 0) {
                return redirect()->back()->with('success', "{$notasProcessadas} nota(s) marcada(s) como paga(s) e repassada(s) ao capital empresarial!");
            }

            return redirect()->back()->with('error', 'As notas selecionadas já haviam sido pagas anteriormente.');

        } catch (\Exception $e) {
            Log::error("Erro ao liquidar notas do lote #{$request->batch_id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro interno ao processar o pagamento: ' . $e->getMessage());
        }
    }
    
    public function show(FinancialBatches $batch) 
    {
        $batch->load('invoices');
        
        $dailyRates = DailyRate::with(['collaborator', 'leader']) 
            ->where('company_id', $batch->company_id)
            ->where('active', true)
            ->whereBetween('start', [$batch->period_start->startOfDay(), $batch->period_end->endOfDay()])
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

        $companies = Company::orderBy('name', 'asc')->get();

        return view('app.finance.admin.batches.show', compact(
            'batch', 
            'extratoFinanceiro', 
            'financeiro', 
            'company', 
            'companies'
        ));
    }

    public function store(Request $request)
    {
        $totalBatchAmount = $this->parseCurrency($request->total_amount);

        $batch = FinancialBatches::create([
            'company_id'   => $request->company_id,
            'total_amount' => $totalBatchAmount,
            'period_start' => $request->period_start,
            'period_end'   => $request->period_end,
            'description'  => $request->description,
        ]);

        $numbers      = $request->input('invoice_numbers', []);
        $amounts      = $request->input('invoice_amounts', []);
        $descriptions = $request->input('invoice_descriptions', []);

        foreach ($numbers as $index => $number) {
            if (!empty($number)) {
                
                $invoiceAmount = isset($amounts[$index]) 
                    ? $this->parseCurrency($amounts[$index]) 
                    : 0.0;

                $batch->invoices()->create([
                    'invoice_number' => $number,
                    'amount'         => $invoiceAmount,
                    'description'    => $descriptions[$index] ?? null,
                ]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Lote financeiro criado e todas as notas salvas individualmente!'
        ]);
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

    public function update(Request $request, $id)
    {
        $batch = FinancialBatches::findOrFail($id);

        $totalAmount = $this->parseCurrency($request->total_amount);

        $batch->update([
            'company_id'   => $request->company_id,
            'total_amount' => $totalAmount,
            'period_start' => $request->period_start,
            'period_end'   => $request->period_end,
            'description'  => $request->description,
        ]);

        $batch->invoices()->delete();

        $numbers      = $request->input('invoice_numbers', []);
        $amounts      = $request->input('invoice_amounts', []);
        $descriptions = $request->input('invoice_descriptions', []);

        foreach ($numbers as $index => $number) {
            if (!empty($number)) {
                
                $invoiceAmount = isset($amounts[$index]) 
                    ? $this->parseCurrency($amounts[$index]) 
                    : 0.0;

                $batch->invoices()->create([
                    'invoice_number' => $number,
                    'amount'         => $invoiceAmount,
                    'description'    => $descriptions[$index] ?? null,
                    'received'       => false,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Lote e notas fiscais atualizados com sucesso!');
    }

    private function parseCurrency($value): float
    {
        if (empty($value)) return 0.0;

        if (is_numeric($value)) return (float) $value;

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } 
        elseif (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }
}