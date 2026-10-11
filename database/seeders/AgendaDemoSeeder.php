<?php

namespace Database\Seeders;

use App\Models\AgendaItem;
use App\Models\AgendaSchedule;
use App\Models\User;
use App\Services\Rh\AgendaService;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;

class AgendaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $director = User::query()->where('email', AccessControl::SUPER_ADMIN_BOOTSTRAP_EMAIL)->first()
            ?? User::query()->where('role', 'super_admin')->where('active', true)->orderBy('id')->first();

        if (! $director) {
            $this->command?->warn('Nenhum super admin encontrado. Rode o DatabaseSeeder antes.');

            return;
        }

        $people = [
            'ana.martins@teste.wa' => ['Ana Martins', 'dp_assistant'],
            'juliana.souza@teste.wa' => ['Juliana Souza', 'people_management'],
            'ana.oliveira@teste.wa' => ['Ana Oliveira', 'admin_finance'],
            'ricardo.lima@teste.wa' => ['Ricardo Lima', 'operational_control'],
            'camila.rocha@teste.wa' => ['Camila Rocha', 'recruitment'],
        ];

        $users = [];
        foreach ($people as $email => [$name, $function]) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => 'teste123',
                    'role' => 'employee',
                    'active' => true,
                    'email_verified_at' => now(),
                    'work_function' => $function,
                ]);
            }

            AccessControl::applyToUser($user, 'employee');
            $user->forceFill([
                'name' => $name,
                'password' => 'teste123',
                'active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'work_function' => $function,
            ])->save();

            $users[$function] = $user->fresh();
        }

        $month = now()->format('Y-m');
        $schedules = [
            [
                'title' => 'Fechamento mensal',
                'work_function' => 'admin_finance',
                'recurrence' => 'monthly',
                'anchor_day' => 28,
                'due_time' => '18:00',
                'priority' => 'alta',
                'effort_hours' => 4,
                'description' => 'Fecha a folha. Fica programada até entrar na janela de 7 dias.',
            ],
            [
                'title' => 'Conferência de segunda',
                'work_function' => 'operational_control',
                'recurrence' => 'monday',
                'due_time' => '17:00',
                'priority' => 'normal',
                'effort_hours' => 2,
                'description' => 'Checklist das lojas, toda segunda.',
            ],
            [
                'title' => 'Ponto do dia',
                'work_function' => 'dp_assistant',
                'recurrence' => 'daily',
                'due_time' => '12:00',
                'priority' => 'normal',
                'effort_hours' => 1,
                'description' => 'Conferência diária de ponto.',
            ],
            [
                'title' => 'Triagem da semana',
                'work_function' => 'recruitment',
                'recurrence' => 'weekly',
                'anchor_weekday' => 3,
                'due_time' => '16:00',
                'priority' => 'normal',
                'effort_hours' => 3,
                'description' => 'Triagem de currículos, toda quarta.',
            ],
        ];

        foreach ($schedules as $data) {
            AgendaSchedule::query()->updateOrCreate(
                ['title' => $data['title']],
                [
                    ...$data,
                    'active' => true,
                    'created_by' => $director->id,
                ],
            );
        }

        $samples = [
            ['Folha de Pagamento', 'admin_finance', 'ana.oliveira@teste.wa', AgendaItem::STATUS_DONE, "{$month}-03 18:00:00", 'alta', 4, null],
            ['Admissões', 'admin_finance', 'ana.oliveira@teste.wa', AgendaItem::STATUS_DONE, "{$month}-06 18:00:00", 'normal', 2, null],
            ['Benefícios', 'admin_finance', 'ana.oliveira@teste.wa', AgendaItem::STATUS_IN_PROGRESS, "{$month}-14 18:00:00", 'normal', 3, null],
            ['Escala do ponto', 'admin_finance', 'ana.oliveira@teste.wa', AgendaItem::STATUS_PENDING, "{$month}-10 09:00:00", 'alta', 1, null],
            ['Conferir documentos', 'admin_finance', null, AgendaItem::STATUS_PENDING, "{$month}-16 18:00:00", 'normal', null, null],
            ['Análise de contrato', 'operational_control', 'ricardo.lima@teste.wa', AgendaItem::STATUS_IN_PROGRESS, "{$month}-12 18:00:00", 'alta', 2, null],
            ['Checklist da loja', 'operational_control', 'ricardo.lima@teste.wa', AgendaItem::STATUS_PENDING, "{$month}-08 18:00:00", 'normal', 1, null],
            ['Atualizar férias', 'dp_assistant', 'ana.martins@teste.wa', AgendaItem::STATUS_IN_REVIEW, "{$month}-13 18:00:00", 'normal', 2, 'Falta o comprovante do período.'],
            ['Avaliação de desempenho', 'people_management', 'juliana.souza@teste.wa', AgendaItem::STATUS_PENDING, "{$month}-15 18:00:00", 'normal', 2, null],
            ['Entrevista técnica', 'recruitment', 'camila.rocha@teste.wa', AgendaItem::STATUS_DONE, "{$month}-07 15:00:00", 'normal', 1, null],
        ];

        foreach ($samples as [$title, $function, $email, $status, $due, $priority, $hours, $note]) {
            $assignee = $email ? User::query()->where('email', $email)->first() : null;
            AgendaItem::query()->updateOrCreate(
                ['title' => $title, 'work_function' => $function],
                [
                    'description' => 'Dado de demonstração da agenda por função.',
                    'assignee_id' => $assignee?->id,
                    'created_by' => $director->id,
                    'due_at' => $due,
                    'priority' => $priority,
                    'effort_hours' => $hours,
                    'type' => 'routine',
                    'status' => $status,
                    'recurrence' => 'once',
                    'correction_note' => $note,
                ],
            );
        }

        $created = app(AgendaService::class)->materialize();
        $this->command?->info("Agenda de demonstração pronta. Atividades geradas agora: {$created}.");
    }
}
