<?php

use App\Http\Controllers\AcordoValorExtraController;
use App\Http\Controllers\Finance\Admin\ProcessorController;
use App\Http\Controllers\Finance\Admin\BatchesController;
use App\Http\Controllers\Finance\Analytics\AnalyticsController;
use App\Http\Controllers\CompanyHasSectionController;
use App\Http\Controllers\DailyRateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\CollaboratorsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Finance\Admin\LeaderCostCenterController;
use App\Http\Controllers\Finance\collaborator\CollaboratorFinanceController;
use App\Http\Controllers\Finance\companies\CompanyAssignmentController;
use App\Http\Controllers\CollaboratorPortalController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\RhInboxController;
use App\Http\Controllers\Work\WorkHubController;
use App\Http\Controllers\Work\AccountingListController;
use App\Http\Controllers\Work\DailyRateReleaseController;
use App\Http\Controllers\OperationalDemandController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UniformsController;
use App\Livewire\CashFlow;
use App\Livewire\FinantialResults;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\CompanyHasSection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

    Route::middleware('auth')->group(function () {
    Route::post('/notificacoes/{notification}/lida', [WorkHubController::class, 'readNotification'])->name('notifications.read');

    Route::get('/meu-cadastro', [CollaboratorPortalController::class, 'show'])
        ->name('portal.show')
        ->middleware('permission:Portal do colaborador|Super admin');
    Route::get('/meu-saldo', [CollaboratorPortalController::class, 'earnings'])
        ->name('portal.earnings')
        ->middleware('permission:Portal do colaborador|Super admin');
    Route::get('/minhas-diarias', [CollaboratorPortalController::class, 'dailyRates'])
        ->name('portal.daily-rates')
        ->middleware('permission:Portal do colaborador|Super admin');
    Route::get('/minhas-solicitacoes', [CollaboratorPortalController::class, 'requests'])
        ->name('portal.requests')
        ->middleware('permission:Portal do colaborador|Super admin');
    Route::post('/minhas-solicitacoes', [CollaboratorPortalController::class, 'storeRequest'])
        ->name('portal.requests.store')
        ->middleware('permission:Portal do colaborador');
    Route::put('/meu-cadastro', [CollaboratorPortalController::class, 'update'])
        ->name('portal.update')
        ->middleware('permission:Portal do colaborador');
    Route::post('/meu-cadastro/inatividade/{audit}', [CollaboratorPortalController::class, 'inactivityResponse'])
        ->name('portal.inactivity.response')
        ->middleware('permission:Portal do colaborador');
    Route::post('/meu-cadastro/demissao', [CollaboratorPortalController::class, 'requestDismissal'])
        ->name('portal.dismissal')
        ->middleware('permission:Solicitar demissão');

    Route::middleware('permission:Acesso Work|Lista de diárias|Super admin')->group(function () {
        Route::get('/work/liberacoes/nova', [DailyRateReleaseController::class, 'create'])->name('work.releases.create');
        Route::post('/work/liberacoes', [DailyRateReleaseController::class, 'store'])->name('work.releases.store');
    });

    Route::middleware('permission:Acesso Work')->prefix('work')->group(function () {
        Route::get('/', [WorkHubController::class, 'index'])->name('work.home');
        Route::get('/demo/{board}', [WorkHubController::class, 'demo'])->name('work.demo')->where('board', 'contratacoes|demissoes');
        Route::get('/demo/{board}/{card}', [WorkHubController::class, 'demoCard'])->name('work.demo.card')->where('board', 'contratacoes|demissoes');
        Route::get('/cliomed', [WorkHubController::class, 'cliomed'])->name('work.cliomed')->middleware('permission:Gerir desligamentos');
        Route::get('/cliomed/cobranca.pdf', [WorkHubController::class, 'cliomedChargePdf'])->name('work.cliomed.charge')->middleware('permission:Gerir desligamentos');
        Route::get('/cliomed/nao-cadastrados.pdf', [WorkHubController::class, 'cliomedUnregisteredPdf'])->name('work.cliomed.unregistered')->middleware('permission:Gerir desligamentos');
        Route::post('/cliomed/inconsistencias', [WorkHubController::class, 'resolveCliomed'])->name('work.cliomed.resolve')->middleware('permission:Gerir desligamentos');
        Route::post('/cliomed/descartar', [WorkHubController::class, 'discardCliomed'])->name('work.cliomed.discard')->middleware('permission:Gerir desligamentos');
        Route::get('/contabilidade', [AccountingListController::class, 'show'])->name('work.accounting')->middleware('permission:Conferência contabilidade');
        Route::post('/contabilidade', [AccountingListController::class, 'store'])->name('work.accounting.store')->middleware('permission:Conferência contabilidade');
        Route::post('/contabilidade/{row}', [AccountingListController::class, 'apply'])->name('work.accounting.apply')->middleware('permission:Conferência contabilidade');
        Route::get('/liberacoes', [DailyRateReleaseController::class, 'index'])->name('work.releases.index');
        Route::post('/liberacoes/{release}', [DailyRateReleaseController::class, 'decide'])->name('work.releases.decide')->middleware('permission:Gerir desligamentos|Minhas Análises Direção|Super admin');
        Route::get('/solicitacao', [WorkHubController::class, 'requestForm'])->name('work.request')->middleware('permission:Solicitar desligamento');
        Route::post('/solicitacao', [WorkHubController::class, 'storeRequest'])->name('work.request.store')->middleware('permission:Solicitar desligamento');
        Route::get('/demandas', [OperationalDemandController::class, 'workQueue'])->name('work.demands')->middleware('permission:Atender demanda|Super admin');
        Route::get('/{project}', [WorkHubController::class, 'project'])->name('work.project')->where('project', 'offboarding|recruitment|finance|uniforms');
        Route::get('/offboarding/processos/{process}', [WorkHubController::class, 'show'])->name('work.offboarding.show');
        Route::get('/offboarding/processos/{process}/colaborador', [WorkHubController::class, 'collaboratorData'])->name('work.offboarding.collaborator');
        Route::get('/offboarding/processos/{process}/anexos/{attachment}', [WorkHubController::class, 'attachment'])->name('work.offboarding.attachment');
        Route::post('/offboarding/processos/{process}/documento', [WorkHubController::class, 'storeDocument'])->name('work.offboarding.document')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/verificar', [WorkHubController::class, 'startVerification'])->name('work.offboarding.start')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/registro', [WorkHubController::class, 'markRegistered'])->name('work.offboarding.register')->middleware('permission:Atendimentos da contabilidade');
        Route::post('/offboarding/processos/{process}/conferencia', [WorkHubController::class, 'conference'])->name('work.offboarding.conference')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/direcao', [WorkHubController::class, 'direction'])->name('work.offboarding.direction');
        Route::post('/offboarding/processos/{process}/exame', [WorkHubController::class, 'scheduleExam'])->name('work.offboarding.exam')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/aso', [WorkHubController::class, 'aso'])->name('work.offboarding.aso')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/falta', [WorkHubController::class, 'missExam'])->name('work.offboarding.miss')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/dispensa', [WorkHubController::class, 'waiver'])->name('work.offboarding.waiver')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/correio', [WorkHubController::class, 'mail'])->name('work.offboarding.mail')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/docs', [WorkHubController::class, 'docs'])->name('work.offboarding.docs')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/concluir', [WorkHubController::class, 'complete'])->name('work.offboarding.complete')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/transferencia', [WorkHubController::class, 'transfer'])->name('work.offboarding.transfer')->middleware('permission:Gerir desligamentos');
        Route::post('/offboarding/processos/{process}/cancelar', [WorkHubController::class, 'cancel'])->name('work.offboarding.cancel')->middleware('permission:Gerir desligamentos');
        Route::post('/inatividade/revisar', [WorkHubController::class, 'reviewInactivity'])->name('work.inactivity.review')->middleware('permission:Gerir desligamentos');
        Route::post('/inatividade/{audit}/tratativa', [WorkHubController::class, 'inactivityResponse'])->name('work.inactivity.response');
        Route::post('/inatividade/{audit}/abono', [WorkHubController::class, 'allowance'])->name('work.inactivity.allowance')->middleware('permission:Gerir desligamentos');
        Route::post('/clinicas/{card}/regularizar', [WorkHubController::class, 'resolveClinic'])->name('work.clinic.resolve')->middleware('permission:Gerir desligamentos');
        Route::post('/clinicas/semanal', [WorkHubController::class, 'weekly'])->name('work.clinic.weekly')->middleware('permission:Gerir desligamentos');
        Route::post('/clinicas/precos', [WorkHubController::class, 'storePrice'])->name('work.clinic.prices')->middleware('permission:Gerir desligamentos');
        Route::post('/custos/{entry}', [WorkHubController::class, 'updateCost'])->name('work.costs.update')->middleware('permission:Gerir desligamentos');
        Route::post('/cotas', [WorkHubController::class, 'updateQuota'])->name('work.quotas.update')->middleware('permission:Recrutamento');
        Route::post('/contratacoes', [WorkHubController::class, 'storeHire'])->name('work.hiring.store')->middleware('permission:Recrutamento');
        Route::post('/contratacoes/{candidate}/documento', [WorkHubController::class, 'hireDocument'])->name('work.hiring.document')->middleware('permission:Recrutamento');
        Route::post('/contratacoes/{candidate}/registro', [WorkHubController::class, 'verifyHire'])->name('work.hiring.verify')->middleware('permission:Atendimentos da contabilidade');
        Route::get('/contratacoes/{candidate}/anexos/{attachment}', [WorkHubController::class, 'hireAttachment'])->name('work.hiring.attachment');
    });

    Route::prefix('rh')->group(function () {
        Route::get('/inbox', [RhInboxController::class, 'index'])->name('rh.inbox')->middleware('permission:Inbox RH');
        Route::post('/tarefas/{task}/realocar', [RhInboxController::class, 'decideReallocate'])->name('rh.tasks.reallocate')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/demitir', [RhInboxController::class, 'decideDismiss'])->name('rh.tasks.dismiss')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/realocacao-concluir', [RhInboxController::class, 'completeReallocation'])->name('rh.tasks.reallocation-complete')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/seguir-demissao', [RhInboxController::class, 'proceedToDismissal'])->name('rh.tasks.proceed-dismissal')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/demissao-concluir', [RhInboxController::class, 'completeDismissal'])->name('rh.tasks.dismissal-complete')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/cancelar', [RhInboxController::class, 'cancelProcess'])->name('rh.tasks.cancel')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/inatividade-resolver', [RhInboxController::class, 'resolveInactivity'])->name('rh.tasks.inactivity-resolve')->middleware('permission:Gerir desligamentos');
        Route::post('/tarefas/{task}/inatividade-desligar', [RhInboxController::class, 'openOffboardingFromInactivity'])->name('rh.tasks.inactivity-offboard')->middleware('permission:Gerir desligamentos');
    });

    Route::middleware('permission:Abrir demanda|Atender demanda|Conferir demanda|Super admin')->prefix('demandas')->group(function () {
        Route::get('/', [OperationalDemandController::class, 'index'])->name('demands.index');
        Route::get('/nova', [OperationalDemandController::class, 'create'])->name('demands.create')->middleware('permission:Abrir demanda|Super admin');
        Route::post('/', [OperationalDemandController::class, 'store'])->name('demands.store')->middleware('permission:Abrir demanda|Super admin');
        Route::get('/{demand}', [OperationalDemandController::class, 'show'])->name('demands.show');
        Route::post('/{demand}/atender', [OperationalDemandController::class, 'start'])->name('demands.start')->middleware('permission:Atender demanda|Super admin');
        Route::post('/{demand}/aplicar', [OperationalDemandController::class, 'apply'])->name('demands.apply')->middleware('permission:Atender demanda|Super admin');
        Route::post('/{demand}/nota', [OperationalDemandController::class, 'note'])->name('demands.note')->middleware('permission:Atender demanda|Conferir demanda|Super admin');
        Route::post('/{demand}/conferencia', [OperationalDemandController::class, 'review'])->name('demands.review')->middleware('permission:Atender demanda|Super admin');
        Route::post('/{demand}/voltar', [OperationalDemandController::class, 'returnToProgress'])->name('demands.return')->middleware('permission:Conferir demanda|Super admin');
        Route::post('/{demand}/finalizar', [OperationalDemandController::class, 'finish'])->name('demands.finish')->middleware('permission:Conferir demanda|Super admin');
        Route::get('/{demand}/anexos/{attachment}', [OperationalDemandController::class, 'attachment'])->name('demands.attachment');
    });

    Route::middleware('permission:Agenda RH|Super admin')->prefix('agenda')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('/', [AgendaController::class, 'store'])->name('agenda.store');
        Route::post('/{item}/status', [AgendaController::class, 'status'])->name('agenda.status');
    });

    Route::post('/collaborators/{collaborator}/offboarding', [RhInboxController::class, 'requestForCollaborator'])
        ->name('collaborators.offboarding')
        ->middleware('permission:Solicitar desligamento');

    Route::middleware('permission:Super admin')->group(function () {
        Route::get('/configuracoes', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/configuracoes', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('users.index')->middleware('permission:Lista de usuários'); // Listar usuários
        Route::get('/table', [UsersController::class, 'table'])->name('users.table')->middleware('permission:Lista de usuários');
        Route::get('/deleted', [UsersController::class, 'deleted'])->name('users.deleted')->middleware('permission:Lista de usuários');
        Route::get('/deleted/table', [UsersController::class, 'deletedTable'])->name('users.deleted.table')->middleware('permission:Lista de usuários');
        Route::get('/create', [UsersController::class, 'create'])->name('users.create')->middleware('permission:Formulário de criação dos usuários'); // Formulário de criação
        Route::post('/', [UsersController::class, 'store'])->name('users.store')->middleware('permission:Salvar usuários'); // Salvar novo usuário
        Route::patch('/{id}/role', [UsersController::class, 'updateRole'])->name('users.role')->middleware('permission:Super admin');
        Route::get('/{id}/report/{type}', [UsersController::class, 'report'])->name('users.report')->middleware('permission:Lista de usuários');
        Route::get('/{id}/edit', [UsersController::class, 'edit'])->name('users.edit')->middleware('permission:Formulário de edição dos usuários'); // Formulário de edição
        Route::put('/{id}', [UsersController::class, 'update'])->name('users.update')->middleware('permission:Atualizar usuários'); // Atualizar usuário
        Route::delete('/{id}', [UsersController::class, 'destroy'])->name('users.destroy')->middleware('permission:Deletar usuários'); // Excluir usuário
    });

    Route::prefix('collaborators')->group(function () {
        Route::get('/', [CollaboratorsController::class, 'index'])->name('collaborators.index')->middleware('permission:Lista de colaboradores');
        Route::get('/table', [CollaboratorsController::class, 'table'])->name('collaborators.table')->middleware('permission:Lista de colaboradores');
        Route::get('/deleted', [CollaboratorsController::class, 'deleted'])->name('collaborators.deleted')->middleware('permission:Lista de colaboradores');
        Route::get('/deleted/table', [CollaboratorsController::class, 'deletedTable'])->name('collaborators.deleted.table')->middleware('permission:Lista de colaboradores');
        Route::get('/create', [CollaboratorsController::class, 'create'])->name('collaborators.create')->middleware('permission:Formulário de criação dos colaboradores');
        Route::post('/', [CollaboratorsController::class, 'store'])->name('collaborators.store')->middleware('permission:Salvar colaboradores');
        Route::get('/export-pdf', [CollaboratorsController::class, 'exportPdf'])->name('collaborators.export-pdf')->middleware('permission:Lista de colaboradores');
        Route::get('/{id}/report/{type}', [CollaboratorsController::class, 'report'])->name('collaborators.report')->middleware('permission:Lista de colaboradores');
        Route::get('/{id}/edit', [CollaboratorsController::class, 'edit'])->name('collaborators.edit')->middleware('permission:Formulário de edição dos colaboradores');
        Route::put('/{id}', [CollaboratorsController::class, 'update'])->name('collaborators.update')->middleware('permission:Atualizar colaboradores');
        Route::delete('/{id}', [CollaboratorsController::class, 'destroy'])->name('collaborators.destroy')->middleware('permission:Deletar colaboradores');
    });

    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('companies.index')->middleware('permission:Lista de estabelecimentos');
        Route::get('/table', [CompanyController::class, 'table'])->name('companies.table')->middleware('permission:Lista de estabelecimentos');
        Route::get('/create', [CompanyController::class, 'create'])->name('companies.create')->middleware('permission:Formulário de criação dos estabelecimentos');
        Route::post('/', [CompanyController::class, 'store'])->name('companies.store')->middleware('permission:Salvar estabelecimentos');
        Route::get('/{id}/edit', [CompanyController::class, 'edit'])->name('companies.edit')->middleware('permission:Formulário de edição dos estabelecimentos');
        Route::put('/{id}', [CompanyController::class, 'update'])->name('companies.update')->middleware('permission:Atualizar estabelecimentos');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->name('companies.destroy')->middleware('permission:Deletar estabelecimentos');

        Route::get('/hourly-rate/{id}', [CompanyController::class, 'getHourlyRate'])->name('companies.hourly-rate');
    });
    Route::delete('/company-has-section/remove', [CompanyHasSectionController::class, 'remove'])->name(name: 'companyHasSection.remove');

    Route::prefix('daily-rate')->group(function () {
        Route::get('/', [DailyRateController::class, 'index'])->name('daily-rate.index')->middleware('permission:Lista de diárias');
        Route::get('/table', [DailyRateController::class, 'table'])->name('daily-rate.table')->middleware('permission:Lista de diárias');
        Route::get('/create', [DailyRateController::class, 'create'])->name('daily-rate.create')->middleware('permission:Formulário de criação dos diárias');
        Route::post('/', [DailyRateController::class, 'store'])->name('daily-rate.store')->middleware('permission:Salvar diárias');
        Route::get('/{id}/edit', [DailyRateController::class, 'edit'])->name('daily-rate.edit')->middleware('permission:Formulário de edição dos diárias');
        Route::put('/{id}', [DailyRateController::class, 'update'])->name('daily-rate.update')->middleware('permission:Atualizar diárias');
        Route::delete('/{id}', [DailyRateController::class, 'destroy'])->name('daily-rate.destroy')->middleware('permission:Deletar diárias');
    });

    Route::prefix('rules/acordo-valor-extra')->middleware('permission:Visualizar e inserir informações financeiras nas diárias')->group(function () {

        Route::get('/', [AcordoValorExtraController::class, 'index'])->name('acordo-valor-extra.index');

        Route::get('/list/{company_id}', [AcordoValorExtraController::class, 'list'])->name('acordo-valor-extra.data.list');

        Route::get('/find/{company_id}/{colaborator_id}', [AcordoValorExtraController::class, 'findExtraValueAgreement'])->name('acordo-valor-extra.data.find');

        Route::get('/{id}', [AcordoValorExtraController::class, 'item'])->name('acordo-valor-extra.data.show');
        Route::post('/create', [AcordoValorExtraController::class, 'create'])->name('acordo-valor-extra.data.create');
        Route::delete('/delete/{id}', [AcordoValorExtraController::class, 'delete'])->name('acordo-valor-extra.data.delete');
    });


    Route::get('get-company-sections/{companyId}', [DailyRateController::class, 'getCompanySections'])->name('company.sections')->middleware('permission:Formulário de criação dos diárias');
    Route::get('get-company/{companyId}', function ($companyId) {
        Log::info(Company::findOrFail($companyId)->load('coordinator'));

        return Company::findOrFail($companyId)->load('coordinator');
    })->name('company.company');


    Route::get('get-colaborator/{colaboratorId}', function ($colaboratorId) {
        return Collaborator::findOrFail($colaboratorId);
    })->name('company.colaborator');
    Route::prefix('report')->group(function () {
        Route::get('/dailyrates', [ReportsController::class, 'dailyRates'])->name('report.daily-rates');
        Route::get('/financial', [ReportsController::class, 'financial'])->name('report.financial');
        Route::get('/registers', [ReportsController::class, 'registers'])->name('report.registers');
    });

    Route::get('/relatorios/financeiro/{start}/{end}', [ReportsController::class, 'extratoFinanceiro'])->name('relatorio.financeiro');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //Route::delete('/company-sections/remove', [CompanyHasSectionController::class, 'remove']);
    Route::get('/resultados-financeiros', FinantialResults::class)->name('finantial-results')
        ->middleware('permission:Visualizar e inserir informações financeiras nas diárias');

    //Route::get('/cash-flow', CashFlow::class)->name('finantial-results2');

});

Route::middleware(['auth', 'permission:Processar boletos e confirmar recebimento'])->group(function () {
    Route::get('/index', [BatchesController::class, 'index'])->name('admin.batches.index');
    Route::get('/create', [BatchesController::class, 'create'])->name('admin.batches.create');
    Route::get('/show{batch}', [BatchesController::class, 'show'])->name('admin.batches.show');
    Route::post('/store', [BatchesController::class, 'store'])->name('admin.batches.store');
    Route::post('/process', [BatchesController::class, 'process'])->name('admin.batches.process');
    Route::post('/receipt', [BatchesController::class, 'confirm_receipt'])->name('admin.batches.confirm-receipt');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/collaborator/earnings', [CollaboratorFinanceController::class, 'index'])->name('admin.collaborator.earnings');
    Route::get('/collaborator/earnings/{id}', [CollaboratorFinanceController::class, 'get_wallet'])->name('admin.collaborator.earnings.single');
});

Route::middleware(['auth', 'permission:Gerir pagamento de colaboradores e custos'])->group(function () {
    Route::prefix('admin/finance/processor')
        ->name('admin.finance.processor.')
        ->group(function () {
            Route::get('/', [ProcessorController::class, 'index'])->name('index');
            Route::get('/collaborators', [ProcessorController::class, 'collaboratorPayments'])->name('collaborators');
            Route::get('/pix', [ProcessorController::class, 'pixCosts'])->name('pix');
            Route::post('/pay-wallet/{collaborator}', [ProcessorController::class, 'payWallet'])->name('pay-wallet');
            Route::post('/pay-pix/{cost}', [ProcessorController::class, 'payPix'])->name('pay-pix');
            Route::post('/reject-pix/{cost}', [ProcessorController::class, 'rejectPix'])->name('reject-pix');
        });
});

Route::middleware(['auth', 'permission:Gestão dos centros de custo'])->group(function () {
    Route::get('/leader/cost-center', [LeaderCostCenterController::class, 'render'])->name('admin.leader.cost-center.index');
    Route::get('/admin/centros-de-custo', [CompanyAssignmentController::class, 'index'])->name('cost-centers.index');
    Route::patch('/admin/companies/{company}/assign-leader', [CompanyAssignmentController::class, 'updateLeader'])->name('companies.update-leader');
});

Route::middleware(['auth', 'permission:Visualizar livro razão'])->group(function () {
    Route::prefix('admin/finance/ledger')
        ->name('admin.finance.ledger.')
        ->group(function () {
            Route::get('/', [App\Http\Controllers\Finance\Admin\LedgerController::class, 'index'])->name('index');
        });
});

Route::middleware(['auth', 'permission:Acesso aos dados de diárias'])->group(function () {
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/collaborators', [AnalyticsController::class, 'collaborators'])->name('collaborators');
        Route::get('/establishments', [AnalyticsController::class, 'establishments'])->name('establishments');
    });
});


Route::get('/admin/finance/analytics/pdf', [AnalyticsController::class, 'exportPdf'])->name('analytics.pdf');
Route::get('/admin/finance/analytics/pdf/ativos', [AnalyticsController::class, 'exportAtivosPdf'])->name('analytics.ativos.pdf');


Route::prefix('admin/batches')->name('admin.batches.')->group(function () {
    Route::put('/{batch}', [BatchesController::class, 'update'])->name('update');
});

Route::get('/admin/batches/{batch}/calculate-daily-rates', [BatchesController::class, 'calculateDailyRates'])
    ->middleware(['auth', 'permission:Processar boletos e confirmar recebimento'])
    ->name('daily_rate.calculate');

Route::middleware(['auth'])->prefix('admin/uniforms')->name('admin.uniforms.')->group(function () {
    Route::get('/', [UniformsController::class, 'index'])->name('index');

    Route::post('/deliver/{id}', [UniformsController::class, 'deliver'])->name('deliver');

    Route::post('/deliver-batch', [UniformsController::class, 'deliverBatch'])->name('deliver-batch');

    Route::get('/report-pdf', [UniformsController::class, 'generateReportPdf'])->name('report-pdf');
});

Route::get('/', function () {
    return view('landing-page');
})->name('landing.page');

require __DIR__ . '/auth.php';
