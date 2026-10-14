<?php

namespace App\Support;

use App\Models\ConfigTable;
use App\Models\User;

class RhActivitySettings
{
    public const DEMANDS = 'demands';

    public const OFFBOARDING = 'offboarding';

    public const RECRUITMENT = 'recruitment';

    public const CLIOMED = 'cliomed';

    public const ACCOUNTING = 'accounting';

    public const RELEASES = 'releases';

    public const FINANCE = 'finance';

    public const UNIFORMS = 'uniforms';

    public const INBOX = 'inbox';

    public const OFFBOARDING_REQUEST = 'offboarding_request';

    public const INACTIVITY = 'inactivity';

    /**
     * Papéis de acesso que o diretor pode liberar em cada área.
     *
     * @return array<string, string>
     */
    public static function audienceRoles(): array
    {
        return [
            'rh' => 'RH',
            'accounting' => 'Contabilidade',
            'coordinator' => 'Coordenador',
            'leader' => 'Líder',
            'employee' => 'Funcionário',
        ];
    }

    /**
     * Setor da agenda (work_function). É o parâmetro que restringe a tela a quem opera aquele setor.
     *
     * @return array<string, string>
     */
    public static function audienceSectors(): array
    {
        return PopCatalog::workFunctions();
    }

    /**
     * @return array<string, string>
     */
    public static function sectorShortLabels(): array
    {
        return [
            'dp_assistant' => 'DP',
            'people_management' => 'Gestão de pessoas',
            'admin_finance' => 'Adm/Fin',
            'operational_control' => 'Controle operacional',
            'recruitment' => 'Recrutamento',
        ];
    }

    /**
     * @return array<string, array{flag: string, label: string, hint: string, audiences: list<string>, sectors: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            self::DEMANDS => [
                'flag' => 'rh_activity_demands_enabled',
                'label' => 'Demandas a atender',
                'hint' => 'Fila operacional do RH: Pix, cadastro, declaração e pedidos da loja.',
                'audiences' => ['rh'],
                'sectors' => [],
            ],
            self::OFFBOARDING => [
                'flag' => 'rh_activity_offboarding_enabled',
                'label' => 'Gestão RH Demissional',
                'hint' => 'Quadro de desligamentos, inatividade e transferências para o RH.',
                'audiences' => ['rh', 'accounting'],
                'sectors' => [],
            ],
            self::RECRUITMENT => [
                'flag' => 'rh_activity_recruitment_enabled',
                'label' => 'Processo de contratação',
                'hint' => 'Quadro admissional: documentos, exame, ASO, contabilidade e cadastro na loja.',
                'audiences' => ['rh', 'accounting'],
                'sectors' => [],
            ],
            self::CLIOMED => [
                'flag' => 'rh_activity_cliomed_enabled',
                'label' => 'Conferência Cliomed',
                'hint' => 'Planilha da semana, inconsistências e fechamento da base.',
                'audiences' => ['rh'],
                'sectors' => [],
            ],
            self::ACCOUNTING => [
                'flag' => 'rh_activity_accounting_enabled',
                'label' => 'Conferência da contabilidade',
                'hint' => 'Lista da contabilidade como base oficial da conferência.',
                'audiences' => ['rh', 'accounting'],
                'sectors' => [],
            ],
            self::RELEASES => [
                'flag' => 'rh_activity_releases_enabled',
                'label' => 'Liberações de diária',
                'hint' => 'Pedidos do coordenador e aprovação do RH para diária bloqueada.',
                'audiences' => ['rh', 'coordinator'],
                'sectors' => [],
            ],
            self::FINANCE => [
                'flag' => 'rh_activity_finance_enabled',
                'label' => 'Financeiro e DRE',
                'hint' => 'Entradas, saídas e resultados por loja no RH Controle.',
                'audiences' => ['rh'],
                'sectors' => [],
            ],
            self::UNIFORMS => [
                'flag' => 'rh_activity_uniforms_enabled',
                'label' => 'Operação e uniformes',
                'hint' => 'Solicitações, estoque e entregas no RH Controle.',
                'audiences' => ['rh'],
                'sectors' => [],
            ],
            self::INBOX => [
                'flag' => 'rh_activity_inbox_enabled',
                'label' => 'Acompanhamento RH',
                'hint' => 'Inbox da direção para acompanhar a fila do RH.',
                'audiences' => ['rh'],
                'sectors' => [],
            ],
            self::OFFBOARDING_REQUEST => [
                'flag' => 'rh_activity_offboarding_request_enabled',
                'label' => 'Solicitar desligamento',
                'hint' => 'Card e menu para o coordenador abrir demissão ou transferência.',
                'audiences' => ['coordinator'],
                'sectors' => [],
            ],
            self::INACTIVITY => [
                'flag' => 'rh_activity_inactivity_enabled',
                'label' => 'Inatividade da equipe',
                'hint' => 'Justificativa de 18 dias sem diária na tela da coordenação.',
                'audiences' => ['coordinator'],
                'sectors' => [],
            ],
        ];
    }

    public static function flag(string $key): string
    {
        return self::catalog()[$key]['flag'];
    }

    public static function audienceFlag(string $key): string
    {
        return 'rh_activity_'.$key.'_audiences';
    }

    public static function sectorFlag(string $key): string
    {
        return 'rh_activity_'.$key.'_sectors';
    }

    public static function enabled(string $key): bool
    {
        return ConfigTable::enabled(self::flag($key), true);
    }

    /**
     * @return list<string>
     */
    public static function audiences(string $key): array
    {
        $allowed = array_keys(self::audienceRoles());
        $stored = ConfigTable::json(self::audienceFlag($key));
        $raw = is_array($stored) ? $stored : (self::catalog()[$key]['audiences'] ?? []);

        return array_values(array_intersect($allowed, $raw));
    }

    /**
     * @return list<string>
     */
    public static function sectors(string $key): array
    {
        $allowed = array_keys(self::audienceSectors());
        $stored = ConfigTable::json(self::sectorFlag($key));
        $raw = is_array($stored) ? $stored : (self::catalog()[$key]['sectors'] ?? []);

        return array_values(array_intersect($allowed, $raw));
    }

    /**
     * @return list<string>
     */
    public static function roleLabels(string $key): array
    {
        $roles = self::audienceRoles();
        $labels = [];

        foreach (self::audiences($key) as $role) {
            if (isset($roles[$role])) {
                $labels[] = $roles[$role];
            }
        }

        return $labels;
    }

    /**
     * Setores da agenda visíveis no card do diretor.
     * Se a área é do RH e nenhum setor foi marcado, mostra todos: qualquer setor do RH vê.
     *
     * @return list<string>
     */
    public static function displaySectors(string $key): array
    {
        $selected = self::sectors($key);
        if ($selected !== []) {
            return $selected;
        }

        if (in_array('rh', self::audiences($key), true)) {
            return array_keys(self::audienceSectors());
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function sectorLabels(string $key): array
    {
        $short = self::sectorShortLabels();
        $full = self::audienceSectors();

        return array_values(array_map(
            fn (string $sector) => $short[$sector] ?? $full[$sector] ?? $sector,
            self::displaySectors($key)
        ));
    }

    /**
     * @return list<string>
     */
    public static function audienceLabels(string $key): array
    {
        return array_merge(self::roleLabels($key), self::sectorLabels($key));
    }

    public static function allowedFor(?User $user, string $key): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $roles = self::audiences($key);
        $sectors = self::sectors($key);

        if ($roles === [] && $sectors === []) {
            return false;
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return false;
        }

        if ($sectors !== [] && ! in_array((string) $user->work_function, $sectors, true)) {
            return false;
        }

        return true;
    }

    public static function visible(string $key, ?User $user = null): bool
    {
        if ($user?->isSuperAdmin()) {
            return true;
        }

        if (! self::enabled($key)) {
            return false;
        }

        return self::allowedFor($user, $key);
    }

    public static function abortUnlessVisible(string $key, ?User $user = null): void
    {
        abort_unless(self::visible($key, $user), 403);
    }
}
