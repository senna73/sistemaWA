<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::findOrCreate('Lista de usuários');
        Permission::findOrCreate('Formulário de criação dos usuários');
        Permission::findOrCreate('Salvar usuários');
        Permission::findOrCreate('Formulário de edição dos usuários');
        Permission::findOrCreate('Atualizar usuários');
        Permission::findOrCreate('Deletar usuários');

        Permission::findOrCreate('Lista de colaboradores');
        Permission::findOrCreate('Formulário de criação dos colaboradores');
        Permission::findOrCreate('Salvar colaboradores');
        Permission::findOrCreate('Formulário de edição dos colaboradores');
        Permission::findOrCreate('Atualizar colaboradores');
        Permission::findOrCreate('Deletar colaboradores');

        Permission::findOrCreate('Lista de estabelecimentos');
        Permission::findOrCreate('Formulário de criação dos estabelecimentos');
        Permission::findOrCreate('Salvar estabelecimentos');
        Permission::findOrCreate('Formulário de edição dos estabelecimentos');
        Permission::findOrCreate('Atualizar estabelecimentos');
        Permission::findOrCreate('Deletar estabelecimentos');

        Permission::findOrCreate('Lista de diárias');
        Permission::findOrCreate('Formulário de criação dos diárias');
        Permission::findOrCreate('Salvar diárias');
        Permission::findOrCreate('Formulário de edição dos diárias');
        Permission::findOrCreate('Atualizar diárias');
        Permission::findOrCreate('Deletar diárias');

        Permission::findOrCreate('Visualizar e inserir informações financeiras nas diárias');


        Permission::findOrCreate('Processar boletos e confirmar recebimento');
        Permission::findOrCreate('Gerir pagamento de colaboradores e custos');
        Permission::findOrCreate('Gestão dos centros de custo');
        Permission::findOrCreate('Visualizar livro razão');
        Permission::findOrCreate('Acesso aos dados de diárias');

        $user = User::where('id', '=', 1)->first();
        $user->givePermissionTo(Permission::all());
    }
}
