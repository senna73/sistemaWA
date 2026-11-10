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

### O que não muda

Conferência de registro no INSS na admissão e no desligamento segue como está. Diárias já gravadas não são reescritas por esta suspensão; lançamento novo e recálculo de lote passam a gravar `inss_paid` = 0 e a diária integral do colaborador.
