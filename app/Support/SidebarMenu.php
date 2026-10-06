<?php

namespace App\Support;

use App\Models\User;
use App\Services\Work\WorkHubService;
use Illuminate\Http\Request;

class SidebarMenu
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(?User $user, ?Request $request = null): array
    {
        if (! $user) {
            return [];
        }

        $request ??= request();
        $rendered = [];

        foreach (self::definition($user) as $item) {
            $normalized = self::normalize($item, $user, $request);
            if ($normalized !== null) {
                $rendered[] = $normalized;
            }
        }

        return $rendered;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function definition(User $user): array
    {
        $portalSuper = $user->isSuperAdmin();

        return [
            [
                'id' => 'dashboard',
                'label' => 'Menu Principal',
                'icon' => 'bx-home-circle',
                'route' => 'dashboard',
                'active' => ['dashboard'],
            ],
            [
                'id' => 'portal',
                'label' => 'Portal',
                'icon' => 'bx-id-card',
                'children' => [
                    [
                        'id' => 'portal-profile',
                        'label' => $portalSuper ? 'Cadastro do colaborador' : 'Meu cadastro',
                        'icon' => 'bx-id-card',
                        'route' => 'portal.show',
                        'active' => ['portal.show'],
                        'visible' => fn (User $actor) => $actor->seesCollaboratorPortal(),
                    ],
                    [
                        'id' => 'portal-earnings',
                        'label' => $portalSuper ? 'Saldo do colaborador' : 'Meu saldo',
                        'icon' => 'bx-wallet',
                        'route' => 'portal.earnings',
                        'active' => ['portal.earnings'],
                        'visible' => fn (User $actor) => $actor->seesPortalEarnings(),
                    ],
                    [
                        'id' => 'portal-daily-rates',
                        'label' => $portalSuper ? 'Diárias do colaborador' : 'Diárias',
                        'icon' => 'bx-calendar',
                        'route' => 'portal.daily-rates',
                        'active' => ['portal.daily-rates'],
                        'visible' => fn (User $actor) => $actor->seesPortalDailyRates(),
                    ],
                    [
                        'id' => 'portal-requests',
                        'label' => $portalSuper ? 'Solicitações do colaborador' : 'Solicitações',
                        'icon' => 'bx-send',
                        'route' => 'portal.requests',
                        'active' => ['portal.requests*'],
                        'visible' => fn (User $actor) => $actor->seesCollaboratorPortal(),
                    ],
                ],
            ],
            [
                'id' => 'rh',
                'label' => 'RH',
                'icon' => 'bx-briefcase',
                'children' => [
                    [
                        'id' => 'rh-work',
                        'label' => 'RH Controle',
                        'icon' => 'bx-briefcase',
                        'route' => 'work.home',
                        'active' => ['work.home', 'work.project', 'work.cliomed', 'work.accounting', 'work.releases*', 'work.demo*'],
                        'visible' => fn (User $actor) => $actor->can(AccessControl::PERMISSION_WORK),
                    ],
                    [
                        'id' => 'rh-agenda',
                        'label' => 'Agenda',
                        'icon' => 'bx-calendar-event',
                        'route' => 'agenda.index',
                        'active' => ['agenda.*'],
                        'visible' => fn (User $actor) => $actor->can(PopCatalog::PERMISSION_AGENDA),
                    ],
                    [
                        'id' => 'rh-offboarding',
                        'label' => 'Solicitar desligamento',
                        'icon' => 'bx-user-minus',
                        'route' => 'work.request',
                        'active' => ['work.request', 'work.request.store'],
                        'visible' => fn (User $actor) => $actor->can(AccessControl::PERMISSION_WORK)
                            && $actor->can(AccessControl::PERMISSION_REQUEST_OFFBOARDING)
                            && RhActivitySettings::visible(RhActivitySettings::OFFBOARDING_REQUEST, $actor),
                    ],
                    [
                        'id' => 'rh-inbox',
                        'label' => 'Acompanhamento RH',
                        'icon' => 'bx-task',
                        'route' => 'rh.inbox',
                        'active' => ['rh.inbox'],
                        'visible' => fn (User $actor) => $actor->can(AccessControl::PERMISSION_RH_INBOX)
                            && app(WorkHubService::class)->seesGestorDuty($actor)
                            && RhActivitySettings::visible(RhActivitySettings::INBOX, $actor),
                    ],
                    [
                        'id' => 'rh-demands',
                        'label' => 'Demandas a atender',
                        'icon' => 'bx-message-square-dots',
                        'route' => 'work.demands',
                        'active' => ['work.demands'],
                        'visible' => fn (User $actor) => $actor->isRh()
                            && $actor->can(PopCatalog::PERMISSION_HANDLE_DEMAND)
                            && RhActivitySettings::visible(RhActivitySettings::DEMANDS, $actor),
                    ],
                    [
                        'id' => 'rh-collaborators',
                        'label' => 'Colaboradores',
                        'icon' => 'bx-group',
                        'route' => 'collaborators.index',
                        'active' => ['collaborators.index'],
                        'visible' => fn (User $actor) => $actor->can('Lista de colaboradores'),
                    ],
                    [
                        'id' => 'rh-collaborators-deleted',
                        'label' => 'Colaboradores apagados',
                        'icon' => 'bx-user-x',
                        'route' => 'collaborators.deleted',
                        'active' => ['collaborators.deleted'],
                        'visible' => fn (User $actor) => $actor->can('Lista de colaboradores'),
                    ],
                ],
            ],
            [
                'id' => 'demands',
                'label' => 'Demandas',
                'icon' => 'bx-message-square-dots',
                'route' => 'demands.index',
                'active' => ['demands.*'],
                'visible' => fn (User $actor) => ! $actor->isRh() && $actor->canany([
                    PopCatalog::PERMISSION_OPEN_DEMAND,
                    PopCatalog::PERMISSION_HANDLE_DEMAND,
                    PopCatalog::PERMISSION_REVIEW_DEMAND,
                ]),
            ],
            [
                'id' => 'operation',
                'label' => 'Operação',
                'icon' => 'bx-store',
                'children' => [
                    [
                        'id' => 'operation-companies',
                        'label' => 'Estabelecimentos',
                        'icon' => 'bx-store',
                        'route' => 'companies.index',
                        'active' => ['companies.index'],
                        'visible' => fn (User $actor) => $actor->can('Lista de estabelecimentos'),
                    ],
                    [
                        'id' => 'operation-daily-rates',
                        'label' => 'Diárias',
                        'icon' => 'bx-calendar-event',
                        'route' => 'daily-rate.index',
                        'active' => ['daily-rate.index'],
                        'visible' => fn (User $actor) => $actor->can('Lista de diárias'),
                    ],
                    [
                        'id' => 'operation-uniforms',
                        'label' => 'Uniformes',
                        'icon' => 'bx-closet',
                        'route' => 'admin.uniforms.index',
                        'active' => ['admin.uniforms.*'],
                        'visible' => fn (User $actor) => ! $actor->isCollaboratorRole(),
                    ],
                ],
            ],
            [
                'id' => 'finance',
                'label' => 'Financeiro',
                'icon' => 'bx-wallet',
                'children' => [
                    [
                        'id' => 'finance-payments',
                        'label' => 'Centro de Pagamentos',
                        'icon' => 'bx-credit-card',
                        'route' => 'admin.finance.processor.index',
                        'active' => ['admin.finance.processor.index'],
                        'visible' => fn (User $actor) => $actor->can('Gerir pagamento de colaboradores e custos'),
                    ],
                    [
                        'id' => 'finance-batches',
                        'label' => 'Processamento',
                        'icon' => 'bx-cog',
                        'route' => 'admin.batches.index',
                        'active' => ['admin.batches.index'],
                        'visible' => fn (User $actor) => $actor->can('Processar boletos e confirmar recebimento'),
                    ],
                    [
                        'id' => 'finance-leader-cost-center',
                        'label' => 'Centro de Custo',
                        'icon' => 'bx-list-check',
                        'route' => 'admin.leader.cost-center.index',
                        'active' => ['admin.leader.cost-center.index'],
                        'visible' => fn (User $actor) => $actor->can('Gestão dos centros de custo'),
                    ],
                    [
                        'id' => 'finance-cost-centers',
                        'label' => 'Gestão de Centros',
                        'icon' => 'bx-buildings',
                        'route' => 'cost-centers.index',
                        'active' => ['cost-centers.index'],
                        'visible' => fn (User $actor) => $actor->can('Gestão dos centros de custo'),
                    ],
                    [
                        'id' => 'finance-results',
                        'label' => 'Analytics Financeiro',
                        'icon' => 'bx-pie-chart-alt-2',
                        'route' => 'finantial-results',
                        'active' => ['finantial-results'],
                        'visible' => fn (User $actor) => $actor->can('Visualizar e inserir informações financeiras nas diárias'),
                    ],
                    [
                        'id' => 'finance-analytics',
                        'label' => 'Análise de Dados',
                        'icon' => 'bx-bar-chart-alt-2',
                        'route' => 'analytics.index',
                        'active' => ['analytics.*'],
                        'visible' => fn (User $actor) => $actor->can('Acesso aos dados de diárias'),
                    ],
                    [
                        'id' => 'finance-ledger',
                        'label' => 'Capital Empresarial',
                        'icon' => 'bx-book-content',
                        'route' => 'admin.finance.ledger.index',
                        'active' => ['admin.finance.ledger.index'],
                        'visible' => fn (User $actor) => $actor->can('Visualizar livro razão'),
                    ],
                ],
            ],
            [
                'id' => 'admin',
                'label' => 'Administração',
                'icon' => 'bx-cog',
                'children' => [
                    [
                        'id' => 'admin-users',
                        'label' => 'Usuários',
                        'icon' => 'bx-user',
                        'route' => 'users.index',
                        'active' => ['users.index'],
                        'visible' => fn (User $actor) => $actor->can('Lista de usuários'),
                    ],
                    [
                        'id' => 'admin-users-deleted',
                        'label' => 'Usuários apagados',
                        'icon' => 'bx-user-x',
                        'route' => 'users.deleted',
                        'active' => ['users.deleted'],
                        'visible' => fn (User $actor) => $actor->can('Lista de usuários'),
                    ],
                    [
                        'id' => 'admin-settings',
                        'label' => 'Configurações',
                        'icon' => 'bx-cog',
                        'route' => 'settings.index',
                        'active' => ['settings.*'],
                        'visible' => fn (User $actor) => $actor->isSuperAdmin(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function normalize(array $item, User $user, Request $request): ?array
    {
        if (! self::isVisible($item, $user)) {
            return null;
        }

        $children = [];
        foreach ($item['children'] ?? [] as $child) {
            $normalizedChild = self::normalize($child, $user, $request);
            if ($normalizedChild !== null) {
                $children[] = $normalizedChild;
            }
        }

        if ($children === [] && ! isset($item['route'])) {
            return null;
        }

        if (count($children) === 1) {
            return $children[0];
        }

        if (count($children) > 1) {
            $childActive = collect($children)->contains(fn (array $child) => $child['active']);

            return [
                'id' => $item['id'],
                'type' => 'group',
                'label' => $item['label'],
                'icon' => $item['icon'],
                'active' => $childActive,
                'children' => $children,
            ];
        }

        $patterns = $item['active'] ?? [$item['route']];

        return [
            'id' => $item['id'],
            'type' => 'link',
            'label' => $item['label'],
            'icon' => $item['icon'],
            'url' => route($item['route']),
            'active' => $request->routeIs(...$patterns),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isVisible(array $item, User $user): bool
    {
        $visible = $item['visible'] ?? true;

        if (is_callable($visible)) {
            return (bool) $visible($user);
        }

        return (bool) $visible;
    }
}
