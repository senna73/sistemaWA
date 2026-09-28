<?php

namespace App\Services\Work;

use Illuminate\Support\Collection;

class WorkHubDemo
{
    public function hiring(): array
    {
        return $this->stageBoard('contratacoes', $this->hirePipeline(), $this->hiringStores());
    }

    public function dismissals(): array
    {
        return $this->stageBoard('demissoes', $this->dismissalPipeline(), $this->dismissalStores());
    }

    public function card(string $slug): ?array
    {
        $card = $this->cards()[$slug] ?? null;
        if ($card === null) {
            return null;
        }

        $card['documents'] = $this->withSources($card['documents'] ?? []);

        return $card;
    }

    /**
     * @param  list<array{status: string, label: string}>  $documents
     * @return list<array{status: string, label: string, src: ?string}>
     */
    private function withSources(array $documents): array
    {
        return array_map(function (array $document): array {
            $document['src'] = $document['status'] === 'anexado'
                ? $this->sourceFor($document['label'])
                : null;
            $document['stage'] = $this->mapStep($document['label']);

            return $document;
        }, $documents);
    }

    private function sourceFor(string $label): ?string
    {
        $map = [
            'RG / documento de identificação' => 'img/demo-docs/rg-camila.png',
            'Carta a punho do colaborador' => 'img/demo-docs/carta-punho.png',
            'ASO admissional' => 'img/demo-docs/aso-admissional.png',
            'ASO demissional' => 'img/demo-docs/aso-demissional.png',
            'Declaração de dispensa de exame' => 'img/demo-docs/declaracao-dispensa.png',
            'Comprovante de correio / AR' => 'img/demo-docs/comprovante-correio.png',
        ];

        return $map[$label] ?? null;
    }

    /**
     * @param  array<string, string>  $pipeline
     * @param  list<array<string, mixed>>  $stores
     */
    private function stageBoard(string $mode, array $pipeline, array $stores): array
    {
        $columns = [];
        foreach ($pipeline as $key => $name) {
            $columns[$key] = [
                'id' => $key,
                'name' => $name,
                'city' => null,
                'cards' => [],
            ];
        }

        $allowed = array_keys($pipeline);
        foreach ($stores as $store) {
            foreach ($store['cards'] as $card) {
                if (! empty($card['slug'])) {
                    $detail = $this->card($card['slug']) ?? [];
                    $merged = array_merge($card, $detail);
                    $key = $this->pipelineKey($merged['steps'] ?? [], $allowed);
                    $columns[$key]['cards'][] = $merged;
                    continue;
                }

                $card['store'] = $store['name'];
                $columns['vagas']['cards'][] = $card;
            }
        }

        $columnList = collect(array_values($columns));

        return [
            'mode' => $mode,
            'columns' => $columnList,
            'store_count' => $columnList->count(),
            'openings' => collect($columns['vagas']['cards'] ?? [])->where('style', 'open-slot')->count(),
            'opening_count' => $columnList->sum(fn (array $column) => collect($column['cards'])->whereIn('style', ['hire-slot', 'opening-slot'])->count()),
            'excess_count' => collect($columns['vagas']['cards'] ?? [])->where('style', 'excess-slot')->count(),
            'process_count' => $columnList->sum(fn (array $column) => count($column['cards'])),
        ];
    }

    /**
     * @param  list<array{ok: bool, label: string}>  $steps
     * @param  list<string>  $allowed
     */
    private function pipelineKey(array $steps, array $allowed): string
    {
        $pending = collect($steps)->first(fn (array $step) => empty($step['ok']));
        $key = $this->mapStep($pending['label'] ?? '');

        if (! in_array($key, $allowed, true)) {
            $key = $allowed[array_key_last($allowed)] === 'vagas'
                ? ($allowed[count($allowed) - 2] ?? 'vagas')
                : ($allowed[0] ?? 'vagas');
        }

        return $key;
    }

    private function mapStep(string $label): string
    {
        $text = mb_strtolower($label);

        return match (true) {
            str_contains($text, 'documentos pessoais') => 'documentos',
            str_contains($text, 'aguardando atendimento') => 'atendimento',
            str_contains($text, 'carta') => 'carta',
            str_contains($text, 'confer') => 'conferencia',
            str_contains($text, 'direção') || str_contains($text, 'direcao') => 'direcao',
            str_contains($text, 'aso →') || str_contains($text, 'aso ->') => 'aso',
            str_contains($text, 'correio') => 'correio',
            str_contains($text, 'agendar exame') || str_contains($text, 'exame admissional') => 'exame',
            str_contains($text, 'exame') || str_contains($text, 'aso') => 'exame',
            str_contains($text, 'registro no inss') || str_contains($text, 'verificação') => 'contabilidade',
            str_contains($text, 'primeira escala') => 'loja',
            str_contains($text, 'contabilidade') => 'contabilidade',
            str_contains($text, 'cadastro') || str_contains($text, 'escala') || str_contains($text, 'loja') => 'loja',
            str_contains($text, 'baixa') => 'baixa',
            default => 'vagas',
        };
    }

    /**
     * @return array<string, string>
     */
    private function hirePipeline(): array
    {
        return [
            'documentos' => '1. Documentos pessoais',
            'exame' => '2. Exame admissional',
            'aso' => '3. ASO → RH',
            'contabilidade' => '4. Encaminhar à contabilidade',
            'loja' => '5. Cadastro na loja',
            'vagas' => 'Vagas em aberto',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dismissalPipeline(): array
    {
        return [
            'atendimento' => 'Aguardando atendimento do RH',
            'carta' => '1. Carta a punho',
            'conferencia' => '2. Conferência WA × INSS',
            'direcao' => '3. Decisão da Direção',
            'exame' => '4. Marcação de exame / ASO',
            'correio' => '5. Correio / AR',
            'contabilidade' => '6. Contabilidade',
            'baixa' => '7. Baixa e WhatsApp',
            'vagas' => 'Vagas e excesso',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hiringStores(): array
    {
        return [
            $this->column('Bistek 04', 'Blumenau', 17, 15, [
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 1 de 2', 'Cota 17 · 15 pessoas ativas nesta loja.'),
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 2 de 2', 'Liberada após desligamento de Carla Nunes.'),
                $this->makeCard('hire-slot', 'Contratação em andamento', 'Camila Ferreira', 'Exame admissional · Cliomed 26/09 09h · Repositora', 'camila-ferreira'),
                $this->makeCard('hire-slot', 'Contratação em andamento', 'Pedro Alves', 'Documentação na contabilidade · Caixa FDS', 'pedro-alves'),
            ], opening: 2),
            $this->column('Top Belchior', 'Blumenau', 12, 11, [
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 1 de 1', 'Cota 12 · 11 pessoas ativas nesta loja.'),
                $this->makeCard('hire-slot', 'Atualização no sistema', 'Leticia Rocha', 'ASO anexado · aguardando cadastro na loja', 'leticia-rocha'),
            ], opening: 1),
            $this->column('Top Tribess', 'Blumenau', 10, 9, [
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 1 de 1', 'Setor: Hortifruti · 1 vaga FDS.'),
                $this->makeCard('hire-slot', 'Em atendimento', 'Rafael Souza', 'Teste prático agendado · Açougue', 'rafael-souza'),
            ], opening: 1),
            $this->column('Bistek 06 (Fresh)', 'Balneário Camboriú', 8, 8, [
                $this->makeCard('hire-slot', 'Contratação em andamento', 'Juliana Costa', 'Aguardando ASO admissional · Mercearia', 'juliana-costa'),
            ], opening: 1),
            $this->column('Mercado Garcia Matriz', 'Blumenau', 14, 12, [
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 1 de 2', 'Cota 14 · 12 pessoas ativas nesta loja.'),
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 2 de 2', 'Turno tarde · Padaria.'),
            ]),
            $this->column('Super S', 'Blumenau', 9, 9, []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dismissalStores(): array
    {
        return [
            $this->column('Bistek 04', 'Blumenau', 17, 16, [
                $this->makeCard('opening-slot', 'Vaga em processo de abertura', 'Julio Cesar', 'DEMISSÃO · Atendimento RH · Repositor', 'julio-cesar'),
                $this->makeCard('opening-slot', 'Vaga em processo de abertura', 'Carla Nunes', 'PEDIDO DE DEMISSÃO · Marcação de exame · Caixa', 'carla-nunes'),
            ], opening: 2),
            $this->column('Top Belchior', 'Blumenau', 12, 13, [
                $this->makeCard('opening-slot', 'Vaga em processo de abertura', 'Marcos Silva', 'DEMISSÃO POR INATIVIDADE · Minhas Análises · Mercearia', 'marcos-silva'),
                $this->makeCard('excess-slot', 'Excesso de cota', 'Bruno Dias', 'Acima da cota de 12 · Extra no FDS'),
            ], excess: 1, opening: 1),
            $this->column('Top Tribess', 'Blumenau', 10, 10, [
                $this->makeCard('opening-slot', 'Vaga em processo de abertura', 'Fernanda Lima', 'TRANSFERÊNCIA · Análise da Direção · Hortifruti', 'fernanda-lima'),
            ], opening: 1),
            $this->column('Bistek 06 (Fresh)', 'Balneário Camboriú', 8, 7, [
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 1 de 1', 'Desligamento concluído em 22/09 · Mercearia.'),
            ]),
            $this->column('Mercado Garcia Matriz', 'Blumenau', 14, 14, [
                $this->makeCard('opening-slot', 'Vaga em processo de abertura', 'Anderson Ribeiro', 'DEMISSÃO · Aguardando contabilidade · Padaria', 'anderson-ribeiro'),
            ], opening: 1),
            $this->column('Super S', 'Blumenau', 9, 8, [
                $this->makeCard('open-slot', 'Vaga em aberto', 'Vaga livre 1 de 1', 'Cota 9 · 8 pessoas ativas nesta loja.'),
                $this->makeCard('opening-slot', 'Vaga em processo de abertura', 'Patricia Gomes', 'DEMISSÃO · Demissão via correio · Caixa', 'patricia-gomes'),
            ], opening: 1),
        ];
    }

    private function cards(): array
    {
        return [
            'camila-ferreira' => [
                'kind' => 'Contratação',
                'name' => 'Camila Ferreira',
                'store' => 'Bistek 04',
                'role' => 'Repositora',
                'stage' => 'Exame admissional agendado',
                'body' => 'Vaga aberta após desligamento. Exame na Cliomed em 26/09 às 09h.',
                'flow' => 'hire',
                'steps' => [
                    ['ok' => true, 'label' => 'Documentos pessoais'],
                    ['ok' => true, 'label' => 'Agendar exame admissional'],
                    ['ok' => false, 'label' => 'ASO → RH'],
                    ['ok' => false, 'label' => 'Encaminhar à contabilidade'],
                    ['ok' => false, 'label' => 'Atualizar cadastro na loja'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'RG / documento de identificação'],
                    ['status' => 'anexado', 'label' => 'Foto 3x4'],
                    ['status' => 'pendente', 'label' => 'ASO admissional'],
                    ['status' => 'pendente', 'label' => 'Comprovante de agendamento Cliomed'],
                ],
            ],
            'pedro-alves' => [
                'kind' => 'Contratação',
                'name' => 'Pedro Alves',
                'store' => 'Bistek 04',
                'role' => 'Caixa FDS',
                'stage' => 'Documentação na contabilidade',
                'body' => 'ASO ok. Falta conferência de CTPS e cadastro no sistema da loja.',
                'flow' => 'hire',
                'steps' => [
                    ['ok' => true, 'label' => 'Documentos pessoais'],
                    ['ok' => true, 'label' => 'Exame admissional'],
                    ['ok' => true, 'label' => 'ASO → RH'],
                    ['ok' => false, 'label' => 'Encaminhar à contabilidade'],
                    ['ok' => false, 'label' => 'Verificação do registro no INSS'],
                    ['ok' => false, 'label' => 'Atualizar cadastro na loja'],
                    ['ok' => false, 'label' => 'Primeira escala'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'RG / documento de identificação'],
                    ['status' => 'anexado', 'label' => 'CTPS'],
                    ['status' => 'anexado', 'label' => 'ASO admissional'],
                    ['status' => 'pendente', 'label' => 'Comprovante enviado à contabilidade'],
                ],
            ],
            'leticia-rocha' => [
                'kind' => 'Contratação',
                'name' => 'Leticia Rocha',
                'store' => 'Top Belchior',
                'role' => 'Mercearia',
                'stage' => 'Atualização no sistema',
                'body' => 'ASO anexado. Aguardando o coordenador confirmar a primeira escala.',
                'flow' => 'hire',
                'steps' => [
                    ['ok' => true, 'label' => 'Documentos pessoais'],
                    ['ok' => true, 'label' => 'ASO → RH'],
                    ['ok' => true, 'label' => 'Encaminhar à contabilidade'],
                    ['ok' => false, 'label' => 'Atualizar cadastro na loja'],
                    ['ok' => false, 'label' => 'Primeira escala'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'RG / documento de identificação'],
                    ['status' => 'anexado', 'label' => 'ASO admissional'],
                    ['status' => 'pendente', 'label' => 'Print da escala na loja'],
                ],
            ],
            'rafael-souza' => [
                'kind' => 'Contratação',
                'name' => 'Rafael Souza',
                'store' => 'Top Tribess',
                'role' => 'Açougue',
                'stage' => 'Em atendimento',
                'body' => 'Teste prático com o líder da loja. 1 vaga FDS no hortifruti/açougue.',
                'flow' => 'hire',
                'steps' => [
                    ['ok' => false, 'label' => 'Documentos pessoais'],
                    ['ok' => false, 'label' => 'Agendar exame admissional'],
                    ['ok' => false, 'label' => 'ASO → RH'],
                    ['ok' => false, 'label' => 'Encaminhar à contabilidade'],
                ],
                'documents' => [
                    ['status' => 'pendente', 'label' => 'RG / documento de identificação'],
                    ['status' => 'pendente', 'label' => 'ASO admissional'],
                ],
            ],
            'juliana-costa' => [
                'kind' => 'Contratação',
                'name' => 'Juliana Costa',
                'store' => 'Bistek 06 (Fresh)',
                'role' => 'Mercearia',
                'stage' => 'Aguardando ASO admissional',
                'body' => 'Cota cheia; esta contratação cobre a próxima saída prevista.',
                'flow' => 'hire',
                'steps' => [
                    ['ok' => true, 'label' => 'Documentos pessoais'],
                    ['ok' => true, 'label' => 'Agendar exame admissional'],
                    ['ok' => false, 'label' => 'ASO → RH'],
                    ['ok' => false, 'label' => 'Encaminhar à contabilidade'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'RG / documento de identificação'],
                    ['status' => 'anexado', 'label' => 'Comprovante de agendamento Cliomed'],
                    ['status' => 'pendente', 'label' => 'ASO admissional'],
                ],
            ],
            'julio-cesar' => [
                'kind' => 'Demissão',
                'name' => 'Julio Cesar',
                'store' => 'Bistek 04',
                'role' => 'Repositor',
                'stage' => 'Aguardando atendimento do RH',
                'body' => 'Solicitação do coordenador. Conferência de diárias WA × INSS e carta ainda pendentes.',
                'flow' => 'dismissal',
                'steps' => [
                    ['ok' => false, 'label' => 'Aguardando atendimento do RH'],
                    ['ok' => false, 'label' => 'Carta a punho'],
                    ['ok' => false, 'label' => 'Conferência WA × INSS'],
                    ['ok' => false, 'label' => 'Registro na contabilidade'],
                    ['ok' => false, 'label' => 'Marcação de exame / ASO'],
                    ['ok' => false, 'label' => 'Documentos da contabilidade'],
                    ['ok' => false, 'label' => 'Baixa e WhatsApp'],
                ],
                'documents' => [
                    ['status' => 'pendente', 'label' => 'Carta a punho do colaborador'],
                    ['status' => 'pendente', 'label' => 'ASO demissional'],
                    ['status' => 'pendente', 'label' => 'Print da conferência de diárias'],
                ],
            ],
            'carla-nunes' => [
                'kind' => 'Pedido de demissão',
                'name' => 'Carla Nunes',
                'store' => 'Bistek 04',
                'role' => 'Caixa',
                'stage' => 'Marcação de exame',
                'body' => 'Pedido da colaboradora. Exame demissional na Cliomed. Vaga já sinalizada para abertura.',
                'flow' => 'dismissal',
                'steps' => [
                    ['ok' => true, 'label' => 'Carta a punho'],
                    ['ok' => true, 'label' => 'Conferência WA × INSS'],
                    ['ok' => true, 'label' => 'Registro na contabilidade'],
                    ['ok' => false, 'label' => 'Marcação de exame / ASO'],
                    ['ok' => false, 'label' => 'Documentos da contabilidade'],
                    ['ok' => false, 'label' => 'Baixa e WhatsApp'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'Carta a punho do colaborador'],
                    ['status' => 'anexado', 'label' => 'RG / documento de identificação'],
                    ['status' => 'pendente', 'label' => 'ASO demissional'],
                    ['status' => 'pendente', 'label' => 'Comprovante de agendamento Cliomed'],
                ],
            ],
            'marcos-silva' => [
                'kind' => 'Demissão por inatividade',
                'name' => 'Marcos Silva',
                'store' => 'Top Belchior',
                'role' => 'Mercearia',
                'stage' => 'Minhas Análises — Direção',
                'body' => '25 dias sem diária e sem abono. Loja ficou 1 pessoa acima da cota com extra de FDS.',
                'flow' => 'dismissal',
                'steps' => [
                    ['ok' => true, 'label' => 'Auditoria de inatividade'],
                    ['ok' => false, 'label' => 'Decisão da Direção'],
                    ['ok' => false, 'label' => 'Marcação de exame / ASO'],
                    ['ok' => false, 'label' => 'Documentos da contabilidade'],
                    ['ok' => false, 'label' => 'Baixa e WhatsApp'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'Print da auditoria de inatividade'],
                    ['status' => 'pendente', 'label' => 'ASO demissional'],
                    ['status' => 'pendente', 'label' => 'Carta a punho do colaborador'],
                ],
            ],
            'fernanda-lima' => [
                'kind' => 'Transferência',
                'name' => 'Fernanda Lima',
                'store' => 'Top Tribess',
                'role' => 'Hortifruti',
                'stage' => 'Análise da Direção',
                'body' => 'Sem vaga aceita na loja de destino. Custo e momento da empresa em análise.',
                'flow' => 'dismissal',
                'steps' => [
                    ['ok' => true, 'label' => 'Oferta de lojas'],
                    ['ok' => true, 'label' => 'Resposta do colaborador'],
                    ['ok' => false, 'label' => 'Decisão da Direção'],
                    ['ok' => false, 'label' => 'Troca de loja no cadastro'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'Print das lojas oferecidas'],
                    ['status' => 'pendente', 'label' => 'Confirmação da nova loja'],
                ],
            ],
            'anderson-ribeiro' => [
                'kind' => 'Demissão',
                'name' => 'Anderson Ribeiro',
                'store' => 'Mercado Garcia Matriz',
                'role' => 'Padaria',
                'stage' => 'Aguardando contabilidade',
                'body' => 'ASO anexado. Falta baixa e envio dos documentos finais.',
                'flow' => 'dismissal',
                'steps' => [
                    ['ok' => true, 'label' => 'Carta a punho'],
                    ['ok' => true, 'label' => 'Conferência WA × INSS'],
                    ['ok' => true, 'label' => 'Marcação de exame / ASO'],
                    ['ok' => false, 'label' => 'Documentos da contabilidade'],
                    ['ok' => false, 'label' => 'Baixa e WhatsApp'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'Carta a punho do colaborador'],
                    ['status' => 'anexado', 'label' => 'ASO demissional'],
                    ['status' => 'pendente', 'label' => 'Documentação devolvida pela contabilidade'],
                ],
            ],
            'patricia-gomes' => [
                'kind' => 'Demissão',
                'name' => 'Patricia Gomes',
                'store' => 'Super S',
                'role' => 'Caixa',
                'stage' => 'Demissão via correio',
                'body' => 'Não compareceu ao exame. Declaração anexada. Rastreio dos correios pendente.',
                'flow' => 'dismissal',
                'steps' => [
                    ['ok' => true, 'label' => 'Carta a punho'],
                    ['ok' => true, 'label' => 'Falta ao exame / declaração de dispensa'],
                    ['ok' => false, 'label' => 'Comprovante de correio (AR)'],
                    ['ok' => false, 'label' => 'Documentos da contabilidade'],
                    ['ok' => false, 'label' => 'Baixa e WhatsApp'],
                ],
                'documents' => [
                    ['status' => 'anexado', 'label' => 'Carta a punho do colaborador'],
                    ['status' => 'anexado', 'label' => 'Declaração de dispensa de exame'],
                    ['status' => 'pendente', 'label' => 'Comprovante de correio / AR'],
                ],
            ],
        ];
    }

    private function column(string $name, string $city, int $quota, int $occupied, array $cards, int $opening = 0, int $excess = 0): array
    {
        return [
            'id' => null,
            'name' => $name,
            'city' => $city,
            'quota' => $quota,
            'occupied' => $occupied,
            'open' => max(0, $quota - $occupied),
            'opening' => $opening,
            'excess' => $excess,
            'cards' => $cards,
        ];
    }

    private function makeCard(string $style, string $tag, string $title, string $body, ?string $slug = null): array
    {
        return compact('style', 'tag', 'title', 'body', 'slug');
    }
}
