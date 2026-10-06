<?php

namespace App\Support;

use App\Models\ConfigTable;
use App\Models\User;

class RhActivitySettings
{
    public const DEMANDS = 'demands';

    public const OFFBOARDING = 'offboarding';

    public const CLIOMED = 'cliomed';

    public const ACCOUNTING = 'accounting';

    public const RELEASES = 'releases';

    public const FINANCE = 'finance';

    public const UNIFORMS = 'uniforms';

    public const INBOX = 'inbox';

    public const OFFBOARDING_REQUEST = 'offboarding_request';

    public const INACTIVITY = 'inactivity';

    /**
     * @return array<string, array{flag: string, label: string, hint: string}>
     */
    public static function catalog(): array
    {
        return [
            self::DEMANDS => [
                'flag' => 'rh_activity_demands_enabled',
                'label' => 'Demandas a atender',
                'hint' => 'Fila operacional do RH: Pix, cadastro, declaração e pedidos da loja.',
            ],
            self::OFFBOARDING => [
                'flag' => 'rh_activity_offboarding_enabled',
                'label' => 'Gestão RH Demissional',
                'hint' => 'Quadro de desligamentos, inatividade e transferências para o RH.',
            ],
            self::CLIOMED => [
                'flag' => 'rh_activity_cliomed_enabled',
                'label' => 'Conferência Cliomed',
                'hint' => 'Planilha da semana, inconsistências e fechamento da base.',
            ],
            self::ACCOUNTING => [
                'flag' => 'rh_activity_accounting_enabled',
                'label' => 'Conferência da contabilidade',
                'hint' => 'Lista da contabilidade como base oficial da conferência.',
            ],
            self::RELEASES => [
                'flag' => 'rh_activity_releases_enabled',
                'label' => 'Liberações de diária',
                'hint' => 'Pedidos do coordenador e aprovação do RH para diária bloqueada.',
            ],
            self::FINANCE => [
                'flag' => 'rh_activity_finance_enabled',
                'label' => 'Financeiro e DRE',
                'hint' => 'Entradas, saídas e resultados por loja no RH Controle.',
            ],
            self::UNIFORMS => [
                'flag' => 'rh_activity_uniforms_enabled',
                'label' => 'Operação e uniformes',
                'hint' => 'Solicitações, estoque e entregas no RH Controle.',
            ],
            self::INBOX => [
                'flag' => 'rh_activity_inbox_enabled',
                'label' => 'Acompanhamento RH',
                'hint' => 'Inbox da direção para acompanhar a fila do RH.',
            ],
            self::OFFBOARDING_REQUEST => [
                'flag' => 'rh_activity_offboarding_request_enabled',
                'label' => 'Solicitar desligamento',
                'hint' => 'Card e menu para o coordenador abrir demissão ou transferência.',
            ],
            self::INACTIVITY => [
                'flag' => 'rh_activity_inactivity_enabled',
                'label' => 'Inatividade da equipe',
                'hint' => 'Justificativa de 18 dias sem diária na tela da coordenação.',
            ],
        ];
    }

    public static function flag(string $key): string
    {
        return self::catalog()[$key]['flag'];
    }

    public static function enabled(string $key): bool
    {
        return ConfigTable::enabled(self::flag($key), true);
    }

    public static function visible(string $key, ?User $user = null): bool
    {
        if ($user?->isSuperAdmin()) {
            return true;
        }

        return self::enabled($key);
    }

    public static function abortUnlessVisible(string $key, ?User $user = null): void
    {
        abort_unless(self::visible($key, $user), 403);
    }
}
