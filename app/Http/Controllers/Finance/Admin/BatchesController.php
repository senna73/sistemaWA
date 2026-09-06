<?php

namespace App\Http\Controllers\Finance\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyHasSection;
use App\Models\ConfigTable;
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
use Illuminate\Support\Str;

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
        
        $dailyRates = DailyRate::with(['collaborator', 'leader', 'coordinator']) 
            ->where('company_id', $batch->company_id)
            ->where('active', true)
            ->whereBetween('start', [$batch->period_start->startOfDay(), $batch->period_end->endOfDay()])
            ->get();

        $financeiro = [
            'receita_bruta'      => $dailyRates->sum('earned'),
            'repasse_liquido'    => $dailyRates->sum('pay_amount') - $dailyRates->sum('employee_discount'),
            'comissoes_lider'    => $dailyRates->sum('leader_comission'),
            'custos_coordenador' => $dailyRates->sum('coordinator_amount'), 
            'custos_operacao'    => $dailyRates->sum('transportation') + $dailyRates->sum('feeding'),
            'impostos_taxas'     => $dailyRates->sum('tax_paid') + $dailyRates->sum('inss_paid'),
        ];

        $financeiro['lucro_real'] = $financeiro['receita_bruta'] - (
            $financeiro['repasse_liquido'] + 
            $financeiro['comissoes_lider'] + 
            $financeiro['custos_coordenador'] + 
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

        // ADICIONADO: Agrupamento dos valores por coordenador para o extrato
        $movCoordenador = $dailyRates->whereNotNull('coordinator_id')->groupBy('coordinator_id')->map(fn($g) => [
            'nome' => $g->first()->coordinator->name ?? 'Coordenador Não Identificado',
            'valor' => $g->sum('coordinator_amount'),
            'tipo' => 'Coordenação'
        ]);

        // ALTERADO: Concatenando também o movimento dos coordenadores no extrato final
        $extratoFinanceiro = $movColab->concat($movLider)->concat($movCoordenador)->filter(fn($i) => $i['valor'] > 0);

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
    
    public function calculateDailyRates(FinancialBatches $batch)
    {
        if ($batch->status !== 'pending') {
            return redirect()->back()->with(
                'error',
                "Não é possível recalcular as diárias: o lote está com status '{$batch->status}'."
            );
        }

        $company = Company::findOrFail($batch->company_id);
        $inssDefault = (float) ConfigTable::getValue('inss_default');
        $taxDefault = (float) ConfigTable::getValue('tax_default');

        $dailyRates = DailyRate::with('collaborator')
            ->where('company_id', $batch->company_id)
            ->where('active', true)
            ->whereBetween('start', [
                Carbon::parse($batch->period_start)->startOfDay(),
                Carbon::parse($batch->period_end)->endOfDay(),
            ])
            ->get();

        $dailyRateIds = $dailyRates->modelKeys();
        $operationId = (string) Str::uuid();
        $beforeSnapshot = $dailyRates->mapWithKeys(function (DailyRate $dailyRate) {
            return [$dailyRate->id => $dailyRate->getAttributes()];
        })->all();

        Log::info(implode(PHP_EOL, [
            '========== RECALCULO DE DIARIAS - INICIO ==========',
            "operation_id: {$operationId}",
            "batch_id: {$batch->id}",
            "company_id: {$batch->company_id}",
            "period_start: {$batch->period_start->toDateString()}",
            "period_end: {$batch->period_end->toDateString()}",
            'total_records: ' . count($beforeSnapshot),
            'daily_rate_ids: ' . implode(', ', $dailyRateIds),
            '=====================================================',
        ]));

        $auditRecords = DB::transaction(function () use ($dailyRates, $dailyRateIds, $beforeSnapshot, $operationId, $batch, $company, $inssDefault, $taxDefault) {
            foreach ($dailyRates as $dailyRate) {
                $section = CompanyHasSection::where('company_id', $dailyRate->company_id)
                    ->where('section_id', $dailyRate->section_id)
                    ->where('active', true)
                    ->firstOrFail();

                $collaborator = $dailyRate->collaborator;
                $payRate = match (true) {
                    $collaborator?->is_leader === 1 => (float) $section->leaderPay,
                    $collaborator?->is_extra === 1 => (float) $section->extra,
                    $collaborator?->is_supervisor === 1 => (float) $section->supervisorPay,
                    default => (float) $section->employeePay,
                };

                $earnedRate = (float) $section->earned;
                $hoursWorked = 0.0;
                if ($section->perHour && $dailyRate->start && $dailyRate->end) {
                    $hoursWorked = $dailyRate->start->diffInMinutes($dailyRate->end) / 60;
                    $payRate *= $hoursWorked;
                    $earnedRate *= $hoursWorked;
                }

                $addition = (float) $dailyRate->addition;
                $feeding = (float) $dailyRate->feeding;
                $transportation = (float) $dailyRate->transportation;
                $employeeDiscount = (float) $dailyRate->employee_discount;
                $leaderCommission = $collaborator?->is_leader ? 0.0 : (float) $section->leaderComission;
                $coordinatorValue = (float) ($company->coordinator_value ?? 0);
                $inssPaid = $company->not_flashing ? 0.0 : $inssDefault;
                $taxPaid = $earnedRate * ($taxDefault / 100);
                $payAmount = $payRate + $addition + $feeding - $employeeDiscount;
                $profit = $earnedRate * (1 - ($taxDefault / 100))
                    - $payAmount
                    - $transportation
                    - $inssPaid
                    - $leaderCommission
                    - $coordinatorValue;

                $dailyRate->update([
                    'hourly_rate' => $section->perHour ? (float) $section->employeePay : $payRate,
                    'total_time' => $section->perHour ? $this->formatHours($hoursWorked) : $dailyRate->total_time,
                    'pay_amount' => $payAmount,
                    'leader_comission' => $leaderCommission,
                    'feeding' => $feeding,
                    'inss_paid' => $inssPaid,
                    'tax_paid' => $taxPaid,
                    'earned' => $earnedRate,
                    'profit' => $profit,
                    'coordinator_id' => $company->coordinator_id,
                    'coordinator_amount' => $coordinatorValue,
                ]);
            }

            $afterSnapshot = DailyRate::whereIn('id', $dailyRateIds)
                ->get()
                ->mapWithKeys(function (DailyRate $dailyRate) {
                    return [$dailyRate->id => $dailyRate->getAttributes()];
                })->all();

            $auditRecords = [];
            foreach (array_unique(array_merge(array_keys($beforeSnapshot), array_keys($afterSnapshot))) as $dailyRateId) {
                $before = $beforeSnapshot[$dailyRateId] ?? null;
                $after = $afterSnapshot[$dailyRateId] ?? null;
                $dailyRate = $dailyRates->firstWhere('id', $dailyRateId);

                $changes = [];
                foreach (array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))) as $field) {
                    $beforeValue = $before[$field] ?? null;
                    $afterValue = $after[$field] ?? null;

                    if ($beforeValue !== $afterValue) {
                        $changes[$field] = [
                            'before' => $beforeValue,
                            'after' => $afterValue,
                        ];
                    }
                }

                $auditRecords[] = [
                    'daily_rate_id' => $dailyRateId,
                    'collaborator_id' => $dailyRate?->collaborator_id,
                    'collaborator_name' => $dailyRate?->collaborator?->name,
                    'section_id' => $dailyRate?->section_id,
                    'operation_id' => $operationId,
                    'changed' => !empty($changes),
                    'before' => $before,
                    'after' => $after,
                    'changes' => $changes,
                ];
            }

            return $auditRecords;
        });

        $changedRecords = collect($auditRecords)->where('changed', true)->count();

        foreach ($auditRecords as $auditRecord) {
            Log::info($this->formatDailyRateAuditLog($auditRecord, $batch));
        }

        Log::info(implode(PHP_EOL, [
            '========== RECALCULO DE DIARIAS - FIM ==========',
            "operation_id: {$operationId}",
            "batch_id: {$batch->id}",
            'total_records: ' . count($auditRecords),
            "changed_records: {$changedRecords}",
            'unchanged_records: ' . (count($auditRecords) - $changedRecords),
            '=================================================',
        ]));

        return redirect()->back()->with(
            'success',
            "{$dailyRates->count()} diária(s) recalculada(s) para o período do lote."
        );
    }

    private function formatHours(float $hours): string
    {
        $minutes = (int) round($hours * 60);

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatDailyRateAuditLog(array $auditRecord, FinancialBatches $batch): string
    {
        $before = $auditRecord['before'] ?? [];
        $after = $auditRecord['after'] ?? [];
        $fields = array_unique(array_merge(array_keys($before), array_keys($after)));
        $lines = [
            '',
            '------------------------------------------------------------',
            'AUDITORIA DE DIARIA',
            "operation_id: {$auditRecord['operation_id']}",
            "batch_id: {$batch->id}",
            "daily_rate_id: {$auditRecord['daily_rate_id']}",
            "collaborator_id: " . ($auditRecord['collaborator_id'] ?? 'null'),
            'collaborator_name: ' . ($auditRecord['collaborator_name'] ?? 'null'),
            "section_id: " . ($auditRecord['section_id'] ?? 'null'),
            'status: ' . ($auditRecord['changed'] ? 'ALTERADO' : 'INALTERADO'),
            'COMPARACAO CAMPO A CAMPO:',
        ];

        foreach ($fields as $field) {
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;
            $changed = $beforeValue !== $afterValue ? 'SIM' : 'NAO';

            $lines[] = "  {$field}:";
            $lines[] = '    ANTES:    ' . $this->formatLogValue($beforeValue);
            $lines[] = '    DEPOIS:   ' . $this->formatLogValue($afterValue);
            $lines[] = "    ALTERADO: {$changed}";
        }

        $lines[] = '------------------------------------------------------------';

        return implode(PHP_EOL, $lines);
    }

    private function formatLogValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

}