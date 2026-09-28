# Backlog — Sistema WA

Produto de controle de diárias, escala, RH e financeiro da Wa Merchandising.  
Marque `[x]` só quando o critério de aceite estiver fechado de ponta a ponta.

**Princípio:** o WhatsApp deixa de ser ferramenta de controle. A plataforma é o sistema de registro; o WhatsApp só replica estados (notificações).

**Revisão do código:** 23/09/2026.  
Legenda: **feito** · **parcial** · **não existe**. “Parcial” = tem pedaço no código, mas não cobre o pedido do negócio.

---

## Prioridade P0 — Imposto, empresas e competência fiscal

Objetivo: dois controles de imposto independentes, estabelecimento vinculado à empresa correta, cálculo alinhado às tabelas do Simples Nacional.

Hoje `Company` **é o estabelecimento/loja** (CNPJ, rede, coordenador). Não há pessoa jurídica fiscal separada da loja.

- [ ] **Parcial** — Separação de empresas (cadastro, permissões, dados financeiros isolados)  
  Já existe: CRUD de estabelecimentos, `user_has_company` (quais lojas o usuário lança), lotes/diárias com `company_id`.  
  Falta: duas empresas fiscais (WA vs outra), isolamento de caixa/imposto/permissões como tenant.

- [ ] **Parcial** — Dois controles de imposto (um por empresa), alíquotas/tabelas próprias e histórico  
  Já existe: `config_table.tax_default` e `inss_default` **globais**; campo na diária (`tax_paid`, `inss_paid`); `companies.not_flashing` zera INSS; política de INSS por data de cadastro do colaborador (`Collaborator::shouldDeductInss`, corte 2026-09-11).  
  Falta: imposto por empresa jurídica, histórico de tabela, não sobrescrever o default global a cada lançamento.

- [ ] **Parcial** — Estabelecimento → empresa (lançamentos herdam a regra)  
  Já existe: diária e lote nascem do estabelecimento (`company_id`).  
  Falta: o estabelecimento **apontar** para qual empresa fiscal vale (hoje são a mesma entidade).

- [ ] **Não existe** — Cálculo conforme tabelas oficiais do Simples Nacional (anexos, faixas, versão da tabela)  
  Imposto hoje é um % (ou valor) digitado/default, não faixa do Simples.

- [ ] **Parcial** — Bloquear lançamento de diárias quando já existe nota processada no período  
  Já existe **na criação** (`DailyRateController::store` → `hasProcessedFinancialBatch`): lote `processing`/`completed` **ou** nota com `received = true` no intervalo da loja.  
  Falta: mesma regra em **editar/excluir**; mensagem/cobertura de testes; garantir que “nota processada” = competência fechada, não só `received`.

Infra que **já sustenta** isso: `FinancialBatches` + `FinancialBatcheInvoices`, fechamento (`FechamentoBatchService`), status do lote e das diárias (`criado` / `processado` / `cancelado`).

Critérios de aceite (P0) — ainda abertos:

- Diária de um estabelecimento nunca cai no imposto da empresa errada
- Alterar tabela do Simples não reescreve competências já fechadas
- Lançar **ou editar** diária em período com nota processada é recusado com mensagem clara

---

## P1 — Escala, vagas e células (sair do Trello)

- [ ] **Parcial** — Grupos/células com limite de contratação / vagas  
  Já existe: campo texto `collaborators.group` (tags, filtro, PDF por grupo, analytics).  
  Falta: entidade célula ligada à loja, **limite de vagas**, enforcement.

- [ ] **Não existe** — Cadastro de vagas por estabelecimento (ex.: Bistek 04 tem 17 vagas)

- [ ] **Parcial** — Escala por líder/loja  
  Já existe: `LeaderCostCenter` (líder financeiro da loja), `coordinator_id` na loja, `is_leader` no colaborador (regra de comissão). `UserHasCompany` limita lojas no **lançamento** de diária, não a escala.  
  Falta: grade de escala / turnos / alocação do dia.

- [ ] **Não existe** — Indicador de escala que não fecha (vaga sem alocado)

- [ ] **Não existe** — Busca automática de colaboradores quando a vaga está vazia

- [ ] **Não existe** — Visão ocupação (20 colaboradores vs. 17 vagas da célula)

Critérios de aceite (P1) — ainda abertos.

---

## P1 — WhatsApp como replicação de estado (não como controle)

- [ ] **Parcial** — Eventos de notificação (fila, idempotência, opt-in)  
  Já existe: `WhatsAppNotifier` chamado no desligamento e na inatividade — **só grava log**. Sem job, sem Evolution/Meta, sem opt-in. Comentário no código: provedor entra depois.

- [ ] **Não existe** — Notificar estados de diárias (criada, confirmada, alterada, recusada, processada)  
  Diária tem `status` criado/processado/cancelado; **ninguém notifica**.

- [ ] **Não existe** — Notificar vagas em aberto

- [ ] **Parcial** — Notificar demissões / desligamentos  
  Hook `notifyOffboardingStage` existe; entrega real não.

- [ ] **Não existe** — Ao bater ponto, notificar no WhatsApp

- [ ] **Parcial** — Histórico na plataforma como fonte da verdade  
  Diárias, lotes e processos de desligamento já são o registro. WhatsApp ainda não replica; operação de escala ainda vive fora do sistema.

---

## P1 — Presença, ponto e faltas (respaldo da empresa)

- [ ] **Não existe** — Confirmação de presença na diária (quem, quando, evidência)

- [ ] **Não existe** — Janela de 48h → obrigação de comparecer

- [ ] **Não existe** — Confirmou e faltou sem justificativa legal → cobrança / falta no dossiê

- [ ] **Parcial** — Controle de faltas  
  Já existe: `leave_end_date` (afastamento) e analytics de inatividade (ex. 45 dias sem diária).  
  Falta: falta pontual ligada à diária confirmada.

- [ ] **Não existe** — Ponto integrado à diária e à escala  
  O que existe: horários `start`/`end`/`total_time` na diária e PDF “Comprovante de Ponto” (folha para assinatura). Não é batida de ponto.  
  Extra: login de colaborador por CPF (`CollaboratorAccessService`) — canal possível para o app confirmar presença, ainda sem essa tela.

---

## P2 — Processo demissional e documentação (RH + contabilidade)

Quem dispara o pedido: líder/coordenador (e também o próprio colaborador, `ORIGIN_COLLABORATOR`). RH decide realocar ou demitir.

### Contratação / dossiê (guarda 2 anos)

- [ ] **Não existe** — Upload dos documentos de contratação  
  `OffboardingDocuments` é casca vazia (comentário: retenção 2 anos, upload depois).

- [ ] **Parcial** — Agendar exame admissional  
  Já existe: catálogo `medical_clinics` + `examined_medical_clinic_id` no colaborador. Sem agenda nem workflow.

- [ ] **Não existe** — ASO → RH (arquivo + status)

- [ ] **Não existe** — Encaminhar ASO/docs à contabilidade (`requestDismissalAso` vazio)

- [ ] **Não existe** — E-mail aos estabelecimentos (`notifyEstablishments` vazio; `Company` não tem e-mail de contato)

- [ ] **Não existe** — Retenção 2 anos + arquivamento (só intenção no comentário)

### Desligamento / realocação

- [x] **Feito (fluxo de estados)** — Pedido de desligamento + decisão RH realocar **ou** demitir  
  `OffboardingService`, `OffboardingProcess`, eventos de status, testes `OffboardingFlowTest`. Inbox em `rh.inbox`. Origem coordenador ou colaborador.

- [ ] **Parcial** — Fluxo 1 realocação  
  Tarefa `reallocation_followup` e status `realocacao`.  
  Falta: oferta concreta de outra loja/célula, sinalização “poucas diárias” pelo colaborador, troca de líder na escala (hoje líder é centro de custo financeiro).

- [x] **Feito (bloqueio operacional)** — Em demissão / demitido, **não lança diária** (`OffboardingProcess::blocksDailyRatesFor` no create e no update)

- [ ] **Parcial** — Fluxo 2 demissão  
  Status `demissao` → `concluido_demitido`, tarefa `dismissal_followup`, colaborador `active = false`.  
  Falta: checklist (docs, ASO, e-mail, corte de escala/ponto).

- [ ] **Não existe** — Checklist demissional completo

---

## P2 — RH por lista de tarefas + dashboard de rendimento

- [x] **Feito (núcleo)** — Inbox RH (`RhInboxController`, `RhTask`)  
  Tipos: inatividade, decisão de desligamento, follow-up de realocação, follow-up de demissão.  
  `InactiveCollaboratorDetector` abre tarefa de inatividade e chama o notifier (log).  
  Falta na lista: exame, ASO, docs faltando, e-mail ao estabelecimento.

- [ ] **Parcial** — Dashboard de rendimento  
  Já existe: analytics financeiro/operacional (ativos/inativos por grupo, cidade, clínica, PDFs de inatividade). Dashboard operacional Livewire = **rateio de custo**, não rendimento de escala.  
  Falta: ocupação de vagas, faltas, ciclo admissional/demissional, células que não fecham.

---

## Já no sistema (fora do pedido original, mas útil)

| Peça | Onde |
|------|------|
| Lotes, notas, recebimento, fechamento | `FinancialBatches`, `BatchesController` |
| Carteira do colaborador / ledger / caixa | wallet, `Ledger`, `CashAccount` (caixa global, não por empresa fiscal) |
| Uniformes | módulo próprio |
| Permissões Spatie (incl. Inbox RH / Gerir desligamentos) | rotas `rh.*` |
| Primeiro acesso colaborador (CPF) | `CollaboratorAccessService` |

---

## Ordem sugerida de entrega (ajustada ao que já existe)

1. **Empresa fiscal ≠ estabelecimento** + imposto por empresa + Simples (tabelas versionadas) + **bloquear create e update** de diária com competência fechada  
2. Células, vagas, escala por líder, buracos de alocação  
3. Confirmação de presença, ponto, faltas e cobrança pós-48h  
4. Ligar `WhatsAppNotifier` a um provedor + eventos de diária/vaga/ponto (o gancho de desligamento/inatividade já chama o serviço)  
5. Preencher `OffboardingDocuments` (upload, ASO, e-mail, retenção 2 anos) e tarefas RH desses tipos  
6. Dashboard de rendimento (escala + faltas + ciclo RH), não só analytics financeiro  

---

## Fora de escopo (por enquanto)

- Usar comunidade WhatsApp como rede operacional de escala (substituir, não aprofundar)
- Recriar o Trello como cópia; o sistema deve ter células, limites e tarefas nativas
- Recálculo retroativo de notas já processadas
