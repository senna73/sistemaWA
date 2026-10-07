<?php

use App\Models\AgendaItem;
use App\Models\User;
use App\Services\Rh\AgendaService;
use App\Services\Rh\CliomedReportParser;
use App\Support\AccessControl;
use App\Support\PopCatalog;
use App\Support\SimpleXlsx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function importActor(string $role): User
{
    AccessControl::seed();
    $user = User::factory()->create(['role' => $role]);
    AccessControl::applyToUser($user, $role);

    return $user->fresh();
}

function writeSpreadsheet(array $rows, string $sheetTarget = 'worksheets/sheet1.xml', bool $cellRefs = true): string
{
    $strings = [];
    $cells = '';
    $r = 1;
    foreach ($rows as $line) {
        $cells .= '<row r="'.$r.'">';
        $c = 0;
        foreach ($line as $value) {
            $idx = count($strings);
            $strings[] = $value;
            $ref = $cellRefs ? ' r="'.chr(65 + $c).$r.'"' : '';
            $cells .= '<c'.$ref.' t="s"><v>'.$idx.'</v></c>';
            $c++;
        }
        $cells .= '</row>';
        $r++;
    }

    $shared = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    foreach ($strings as $text) {
        $shared .= '<si><t>'.htmlspecialchars($text, ENT_XML1).'</t></si>';
    }
    $shared .= '</sst>';

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wa-sheet-'.uniqid().'.xlsx';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Dados" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="'.$sheetTarget.'"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$cells.'</sheetData></worksheet>');
    $zip->addFromString('xl/sharedStrings.xml', $shared);
    $zip->close();

    return $path;
}

it('lets a leader with Agenda RH receive an activity and be notified', function () {
    $anderson = importActor('super_admin');
    $geisiane = User::factory()->create([
        'name' => 'Geisyane Salles',
        'email' => 'annesallex@gmail.com',
        'role' => 'leader',
        'active' => true,
    ]);
    AccessControl::applyToUser($geisiane, 'leader');
    $geisiane->givePermissionTo(PopCatalog::PERMISSION_AGENDA);
    $geisiane = $geisiane->fresh();

    $this->actingAs($anderson)
        ->get(route('agenda.index'))
        ->assertOk()
        ->assertSee('Geisyane Salles');

    $item = app(AgendaService::class)->create($anderson, [
        'title' => 'Pedir Relatório da Cliomed',
        'assignee_id' => $geisiane->id,
        'type' => 'weekly',
        'recurrence' => 'weekly',
        'due_at' => now()->addHour(),
    ]);

    expect($item->assignee_id)->toBe($geisiane->id);
    expect($geisiane->fresh()->unreadNotifications)->not->toBeEmpty();
    expect($geisiane->fresh()->unreadNotifications->first()->data['title'])->toBe('Nova atividade na agenda');
});

it('prefers the configured agenda assignee', function () {
    $other = importActor('rh');
    $geisiane = User::factory()->create(['role' => 'rh', 'email' => 'rh.geisiane@example.com', 'active' => true]);
    AccessControl::applyToUser($geisiane, 'rh');
    config(['rh.agenda_assignee_email' => 'rh.geisiane@example.com']);

    expect(app(AgendaService::class)->defaultAssignee($other)?->id)->toBe($geisiane->id);
});

it('reads xlsx even when the workbook points to /xl/worksheets/sheet1.xml', function () {
    $path = writeSpreadsheet([
        ['Nome Unidade', 'Nome Funcionário'],
        ['WA', 'ANA TESTE'],
    ], '/xl/worksheets/sheet1.xml');

    $rows = SimpleXlsx::rows($path);
    unlink($path);

    expect($rows[0][1])->toBe('Nome Funcionário');
    expect($rows[1][1])->toBe('ANA TESTE');
});

it('reads xlsx cells without the r attribute and colaborador headers', function () {
    $path = writeSpreadsheet([
        ['Unidade', 'Nome do Colaborador'],
        ['WA', 'BRUNA SILVA'],
    ], 'worksheets/sheet1.xml', false);

    $rows = app(CliomedReportParser::class)->parse($path, 'relatorio.xlsx');
    unlink($path);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['name'])->toBe('BRUNA SILVA');
});

it('shows a validation error instead of crashing when the cliomed file is unreadable', function () {
    Storage::fake('local');
    $rh = importActor('rh');
    $check = app(\App\Services\Rh\ClinicPanelService::class)->ensureWeeklyCheck();
    $upload = UploadedFile::fake()->createWithContent('relatorio.xlsx', 'isto-nao-e-xlsx');

    $this->actingAs($rh)
        ->post(route('work.clinic.weekly'), [
            'check_id' => $check->id,
            'attachment' => $upload,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('attachment');
});

it('still refuses agenda assignees without agenda access', function () {
    $rh = importActor('rh');
    $employee = importActor('employee');

    expect(fn () => app(AgendaService::class)->create($rh, [
        'title' => 'Rotina',
        'assignee_id' => $employee->id,
        'type' => 'once',
        'due_at' => now(),
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(AgendaItem::query()->count())->toBe(0);
});
