<?php

use App\Models\User;
use App\Support\AccessControl;

it('shows mock hiring and dismissal boards for the client demo', function () {
    AccessControl::seed();
    $user = User::factory()->create(['role' => 'super_admin']);
    AccessControl::applyToUser($user, 'super_admin');

    $this->actingAs($user)
        ->get(route('work.demo', 'contratacoes'))
        ->assertOk()
        ->assertSee('Contratações por etapa')
        ->assertSee('Camila Ferreira')
        ->assertSee('1. Documentos pessoais')
        ->assertSee('3. ASO → RH')
        ->assertSee('ilustrativos');

    $this->actingAs($user)
        ->get(route('work.demo', 'demissoes'))
        ->assertOk()
        ->assertSee('Demissões por etapa')
        ->assertSee('Julio Cesar')
        ->assertSee('Aguardando atendimento do RH')
        ->assertSee('1. Carta a punho')
        ->assertSee('Excesso de cota')
        ->assertSee('Bruno Dias');

    $this->actingAs($user)
        ->get(route('work.demo.card', ['demissoes', 'julio-cesar']))
        ->assertOk()
        ->assertSee('Aguardando atendimento do RH')
        ->assertSee('Bistek 04')
        ->assertSee('Carta a punho do colaborador')
        ->assertSee('Clique para enviar a imagem agora.')
        ->assertSee('Bloqueado até anexar Carta a punho do colaborador.')
        ->assertSee('Etapas do POP');
});
