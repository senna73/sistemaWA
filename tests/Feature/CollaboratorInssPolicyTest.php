<?php

use App\Models\Collaborator;

it('nao deduz inss para colaboradores cadastrados antes de 2026-09-21', function () {
    $collaborator = new Collaborator([
        'created_at' => '2026-09-20 23:59:59',
    ]);

    expect($collaborator->shouldDeductInss())->toBeFalse();
    expect(Collaborator::inssAmount($collaborator->shouldDeductInss()))->toBe(0.0);
});

it('deduz inss fixo da tabela para colaboradores cadastrados a partir de 2026-09-21', function () {
    $collaborator = new Collaborator([
        'created_at' => '2026-09-21 00:00:00',
    ]);

    expect($collaborator->shouldDeductInss())->toBeTrue();
    expect(Collaborator::inssAmount(true))->toBe(5.93);
});
