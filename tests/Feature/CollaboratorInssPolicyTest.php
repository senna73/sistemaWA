<?php

use App\Models\Collaborator;

it('nao desconta inss na folha enquanto a deducao estiver suspensa', function () {
    expect(Collaborator::INSS_PAYROLL_DEDUCTION_ENABLED)->toBeFalse();

    foreach (['2026-09-20 23:59:59', '2026-09-21 00:00:00', '2026-11-09 12:00:00'] as $createdAt) {
        $collaborator = new Collaborator([
            'created_at' => $createdAt,
        ]);

        expect($collaborator->shouldDeductInss())->toBeFalse();
        expect(Collaborator::inssAmount($collaborator->shouldDeductInss()))->toBe(0.0);
    }
});

it('guarda o corte e o valor de tabela para reativar o desconto em janeiro de 2027', function () {
    $beforeCutoff = new Collaborator([
        'created_at' => '2026-09-20 23:59:59',
    ]);
    $onCutoff = new Collaborator([
        'created_at' => '2026-09-21 00:00:00',
    ]);

    expect(Collaborator::INSS_EFFECTIVE_FROM)->toBe('2026-09-21');
    expect(Collaborator::INSS_AMOUNT)->toBe(5.93);
    expect($beforeCutoff->registeredOnOrAfterInssCutoff())->toBeFalse();
    expect($onCutoff->registeredOnOrAfterInssCutoff())->toBeTrue();
    expect(Collaborator::inssAmount(true))->toBe(5.93);
    expect(Collaborator::inssAmount(false))->toBe(0.0);
});
