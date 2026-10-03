<?php

namespace App\Support;

class PopCatalog
{
    public const PERMISSION_ACCOUNTING_LIST = 'Conferência contabilidade';

    public const PERMISSION_OPEN_DEMAND = 'Abrir demanda';

    public const PERMISSION_HANDLE_DEMAND = 'Atender demanda';

    public const PERMISSION_REVIEW_DEMAND = 'Conferir demanda';

    public const PERMISSION_AGENDA = 'Agenda RH';

    /**
     * @return array<string, string>
     */
    public static function inactivityJustifications(): array
    {
        return [
            'doenca' => 'Doença / atestado',
            'falta_diaria' => 'Falta de diária na loja',
            'sem_escala' => 'Sem escala no período',
            'aguardando_chamada' => 'Aguardando chamada',
            'mudanca_loja' => 'Mudança de loja',
            'erro_lancamento' => 'Erro de lançamento',
            'sem_retorno' => 'Colaborador sem retorno',
            'outro' => 'Outro motivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function demandCategories(): array
    {
        return [
            'declaracao' => 'Declaração',
            'transferencia_grupo' => 'Transferência de grupo',
            'atualizacao_cadastro' => 'Atualização de cadastro',
            'troca_pix' => 'Troca de chave Pix',
            'duvida_diaria' => 'Dúvida sobre diária/pagamento',
            'desligamento' => 'Desligamento',
            'problema_grupo' => 'Problema no grupo',
            'solicitacao_loja' => 'Solicitação da loja/coordenador',
            'outros' => 'Outros',
        ];
    }

    /**
     * Pedidos que o colaborador abre no portal. Cada um vira atividade + demanda.
     *
     * @return array<string, string>
     */
    public static function collaboratorRequestCategories(): array
    {
        return array_intersect_key(self::demandCategories(), array_flip([
            'troca_pix',
            'atualizacao_cadastro',
            'declaracao',
            'transferencia_grupo',
            'duvida_diaria',
            'problema_grupo',
            'outros',
        ]));
    }

    /**
     * Registros obrigatórios de cada categoria de demanda (POP operacional).
     *
     * @return array<string, list<string>>
     */
    public static function demandRecordLinks(): array
    {
        return [
            'declaracao' => ['collaborator'],
            'transferencia_grupo' => ['collaborator', 'company'],
            'atualizacao_cadastro' => ['collaborator'],
            'troca_pix' => ['collaborator'],
            'duvida_diaria' => ['collaborator'],
            'desligamento' => ['collaborator', 'offboarding_process'],
            'problema_grupo' => ['collaborator', 'company'],
            'solicitacao_loja' => ['company'],
            'outros' => [],
        ];
    }

    /**
     * Atividades geradas pelos POPs de desligamento e contratação.
     *
     * @return array<string, array{label: string, links: list<string>}>
     */
    public static function processActivityLinks(): array
    {
        return [
            'offboarding_groups' => [
                'label' => 'Retirar de grupos WhatsApp e da célula',
                'links' => ['collaborator', 'offboarding_process', 'company'],
            ],
            'hiring_exam' => [
                'label' => 'Agendar exame admissional',
                'links' => ['candidate', 'company'],
            ],
            'hiring_store' => [
                'label' => 'Atualizar cadastro na loja / primeira escala',
                'links' => ['candidate', 'company'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function demandStatuses(): array
    {
        return [
            'awaiting' => 'Aguardando atendimento',
            'in_progress' => 'Em atendimento',
            'review' => 'Em conferência',
            'done' => 'Finalizado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function agendaTypes(): array
    {
        return [
            'once' => 'Tarefa única',
            'daily' => 'Diária',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
            'bimonthly' => 'A cada dois meses',
            'operational_demand' => 'Demanda operacional',
            'collaborator_request' => 'Solicitação do colaborador',
            'offboarding_groups' => 'Retirar de grupos',
            'hiring_exam' => 'Exame admissional',
            'hiring_store' => 'Cadastro na loja',
            'appointment' => 'Compromisso de agenda',
            'personal' => 'Pessoal',
            'professional' => 'Profissional',
            'routine' => 'Rotina fixa',
        ];
    }

    /**
     * Para que serve cada tipo de atividade na agenda.
     *
     * @return array<string, string>
     */
    public static function agendaUtilities(): array
    {
        return [
            'once' => 'Tarefa avulsa do RH/coordenador, sem recorrência.',
            'daily' => 'Rotina que se repete todo dia após concluir.',
            'weekly' => 'Rotina semanal (ex.: conferência Cliomed).',
            'monthly' => 'Rotina mensal.',
            'bimonthly' => 'Rotina a cada dois meses.',
            'operational_demand' => 'Atendimento de demanda: gera card operacional com conferência.',
            'collaborator_request' => 'Pedido aberto pelo colaborador (Pix, cadastro, declaração).',
            'offboarding_groups' => 'POP de desligamento: tirar da célula e dos grupos WhatsApp.',
            'hiring_exam' => 'POP de contratação: agendar exame admissional do candidato.',
            'hiring_store' => 'POP de contratação: cadastro na loja e primeira escala.',
            'appointment' => 'Compromisso com horário, sem card operacional.',
            'personal' => 'Anotação pessoal do responsável.',
            'professional' => 'Compromisso profissional avulso.',
            'routine' => 'Rotina fixa com recorrência escolhida à parte.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function agendaRecurrences(): array
    {
        return [
            'once' => 'Uma vez',
            'monday' => 'Toda segunda',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
            'bimonthly' => 'A cada dois meses',
            'date' => 'Data específica',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function agendaStatuses(): array
    {
        return [
            'pending' => 'Pendente',
            'in_progress' => 'Em andamento',
            'in_review' => 'Em análise',
            'done' => 'Finalizado',
            'late' => 'Atrasado',
            'cancelled' => 'Cancelado',
        ];
    }

    /**
     * @return list<string>
     */
    public static function agendaAssigneeRoles(): array
    {
        return ['rh', 'coordinator', 'super_admin'];
    }
}
