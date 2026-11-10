# Débito técnico — Sistema WA

Registro do que foi desligado de propósito e precisa voltar. Não é backlog de funcionalidade nova.

---

## DT-001 — Reativar o desconto de INSS na folha em janeiro/2027

**Status:** aberto  
**Registrado em:** 09/11/2026  
**Prazo:** janeiro de 2027  
**TODO:** `docs/TODO.md` (seção “Prazo — janeiro/2027”)

### O que está suspenso

Em 09/11/2026 o desconto de INSS deixou de sair da diária do colaborador. A condicional por data de cadastro (`created_at` a partir de 21/09/2026) continua no código, mas não é aplicada. Todo colaborador recebe a diária integral, sem o abatimento de R$ 5,93.

A chave está em `App\Models\Collaborator::INSS_PAYROLL_DEDUCTION_ENABLED` (`false`).

### Por que

A regra que tinha subido descontava INSS direto na folha de quem foi cadastrado a partir de 21/09/2026. O combinado é suspender esse desconto agora e religá-lo em janeiro de 2027.

### Como desfazer em janeiro/2027

1. Em `app/Models/Collaborator.php`, voltar `INSS_PAYROLL_DEDUCTION_ENABLED` para `true`.
2. Confirmar que a tela de diária (`resources/views/app/daily-rate/edit.blade.php`) volta a abater o INSS do pagamento do colaborador. Ela lê `inss_deduction_enabled`, enviado por `DailyRateController`.
3. Confirmar o recálculo de lote em `app/Http/Controllers/Finance/Admin/BatchesController.php`. Ele usa `Collaborator::shouldDeductInss()`.
4. Ajustar `tests/Feature/CollaboratorInssPolicyTest.php`: com a chave ligada, cadastrado antes de 21/09/2026 continua em 0; a partir dessa data volta a descontar 5,93.
5. Marcar este item e o TODO correspondente como feitos.

### Regra que volta a valer

- Corte: `Collaborator::INSS_EFFECTIVE_FROM` = `2026-09-21` (`registeredOnOrAfterInssCutoff()`).
- Valor: `Collaborator::INSS_AMOUNT` = 5,93 (INSS + INSS 13º). Não incide sobre ajuda de custo.
- Empresa `not_flashing` (não intermitente) continua sem INSS.
- Colaborador sem `created_at`, ou cadastrado antes do corte, continua sem desconto.

### Onde a suspensão atua

- `Collaborator::shouldDeductInss()`
- Lançamento e edição de diária (`DailyRateController`, `resources/views/app/daily-rate/edit.blade.php`)
- Recálculo de lote (`BatchesController`)

### Diárias já lançadas

Lançamento novo e recálculo de lote passam a gravar `inss_paid` = 0 e a diária integral.

Quem ainda não foi pago e ficou com o desconto gravado (por exemplo 104,07 em vez de 110) é reajustado pelo comando `php artisan finance:restore-unpaid-inss-pay`. Sem `--apply` ele só lista. Com `--apply` devolve o INSS ao `pay_amount` e zera `inss_paid`. O lucro da diária não muda.

Não entra no reajuste:

- diária com status `processado` (já recebida)
- diária dentro de lote `processing` ou `completed` (a carteira já foi creditada com o valor antigo)
- diária cancelada ou inativa

### O que não muda

Conferência de registro no INSS na admissão e no desligamento segue como está.
