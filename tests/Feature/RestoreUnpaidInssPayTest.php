<?php

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\DailyRate;
use App\Models\FinancialBatches;
use App\Models\Section;
use App\Services\Finance\RestoreUnpaidInssPay;

function inssDaily(array $overrides = []): DailyRate
{
    $collaborator = Collaborator::factory()->create();
    $company = Company::query()->create([
        'name' => 'Loja INSS',
        'coordinator_value' => 0,
    ]);
    $section = Section::query()->create(['name' => 'Caixa']);

    return DailyRate::forceCreate(array_merge([
        'collaborator_id' => $collaborator->id,
        'company_id' => $company->id,
        'section_id' => $section->id,
        'start' => '2026-10-10 08:00:00',
        'pay_amount' => 104.07,
        'inss_paid' => 5.93,
        'profit' => 40,
        'active' => true,
        'status' => 'criado',
    ], $overrides));
}

it('devolve o inss nas diarias em aberto e nao mexe no lucro', function () {
    $open = inssDaily();
    $pendingBatch = inssDaily(['start' => '2026-10-12 08:00:00']);
    FinancialBatches::query()->create([
        'company_id' => $pendingBatch->company_id,
        'total_amount' => 200,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-15',
        'status' => 'pending',
    ]);

    $result = app(RestoreUnpaidInssPay::class)->run(true);

    expect($result['changed'])->toHaveCount(2);
    expect($result['skipped_paid'])->toBe(0);

    $open->refresh();
    $pendingBatch->refresh();

    expect((float) $open->pay_amount)->toBe(110.0);
    expect((float) $open->inss_paid)->toBe(0.0);
    expect((float) $open->profit)->toBe(40.0);
    expect((float) $pendingBatch->pay_amount)->toBe(110.0);
    expect((float) $pendingBatch->inss_paid)->toBe(0.0);
});

it('nao altera diaria ja recebida nem lote que ja creditou a carteira', function () {
    $received = inssDaily(['status' => 'processado']);
    $processing = inssDaily(['start' => '2026-10-20 08:00:00']);
    $completed = inssDaily(['start' => '2026-09-28 08:00:00']);
    $withoutDiscount = inssDaily([
        'pay_amount' => 110,
        'inss_paid' => 0,
    ]);
    $inactive = inssDaily(['active' => false]);
    $cancelled = inssDaily(['status' => 'cancelado']);

    FinancialBatches::query()->create([
        'company_id' => $processing->company_id,
        'total_amount' => 200,
        'period_start' => '2026-10-16',
        'period_end' => '2026-10-31',
        'status' => 'processing',
    ]);
    FinancialBatches::query()->create([
        'company_id' => $completed->company_id,
        'total_amount' => 200,
        'period_start' => '2026-09-16',
        'period_end' => '2026-09-30',
        'status' => 'completed',
    ]);

    $result = app(RestoreUnpaidInssPay::class)->run(true);

    expect($result['changed'])->toBe([]);
    expect($result['skipped_paid'])->toBe(3);

    foreach ([$received, $processing, $completed, $withoutDiscount, $inactive, $cancelled] as $daily) {
        $fresh = $daily->fresh();
        expect((float) $fresh->pay_amount)->toBe((float) $daily->pay_amount);
        expect(round((float) $fresh->inss_paid, 2))->toBe(round((float) $daily->inss_paid, 2));
    }
});

it('lista o reajuste sem gravar quando roda sem --apply', function () {
    $open = inssDaily();

    $this->artisan('finance:restore-unpaid-inss-pay')
        ->assertSuccessful()
        ->expectsOutputToContain('Nada foi gravado');

    expect((float) $open->fresh()->pay_amount)->toBe(104.07);
    expect(round((float) $open->fresh()->inss_paid, 2))->toBe(5.93);
});
