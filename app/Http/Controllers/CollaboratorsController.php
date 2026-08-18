<?php

namespace App\Http\Controllers;

use App\BlueUtils\Number;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CityHasCollaborator;
use App\Models\Collaborator;
use App\Models\MedicalClinic;
use App\Models\UniformSize;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;

class CollaboratorsController extends Controller
{
    public function index()
    {
        $groups = Collaborator::whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->pluck('group');

        return view('app.collaborators.index', [
            'collaborators' => Collaborator::getActive(),
            'groups' => $groups
        ]);
    }

    public function create()
    {
        $cities = City::all();
        $available_clinics = MedicalClinic::getActive();
        $sizes = UniformSize::pluck('name');
        $groups = Collaborator::whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->pluck('group')
            ->toArray();

        return view('app.collaborators.edit', [
            'collaborator'      => null,
            'cities'            => $cities,
            'selectedCities'    => [],
            'available_clinics' => $available_clinics,
            'sizes'             => $sizes,
            'groups'            => $groups,
        ]);
    }

    public function edit(string $id)
    {
        $collaborator = Collaborator::findOrFail($id);

        $selectedCities = CityHasCollaborator::where('collaborator_id', $id)
            ->pluck('city_id')
            ->toArray();

        $cities = City::all();
        $available_clinics = MedicalClinic::getActive();
        $sizes = UniformSize::pluck('name');
        $groups = Collaborator::whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->pluck('group')
            ->toArray();

        return view('app.collaborators.edit', [
            'collaborator'      => $collaborator,
            'cities'            => $cities,
            'selectedCities'    => $selectedCities,
            'available_clinics' => $available_clinics,
            'sizes'             => $sizes,
            'groups'            => $groups,
        ]);
    }

    public function store(Request $request)
    {
        return $this->saveCollaborator($request);
    }

    public function update(Request $request, string $id)
    {
        return $this->saveCollaborator($request, $id);
    }

    /**
     * Centraliza a lógica de salvamento e atualização do colaborador.
     */
    private function saveCollaborator(Request $request, ?string $id = null)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'name'              => ['required', 'string', 'max:255'],
                'document'          => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
                'pix_key'           => ['required'],
                'medical_clinic_id' => ['nullable', 'exists:medical_clinics,id'],
                'leave_end_date'    => ['nullable', 'date_format:d/m/Y'],
                'uniform_size'      => ['nullable', 'string', Rule::in(['PP', 'P', 'M', 'G', 'GG', 'XG', 'EXG'])],
            ], [
                'name.required'              => 'O campo nome é obrigatório.',
                'name.string'                => 'O nome deve ser um texto válido.',
                'name.max'                   => 'O nome não pode ter mais de 255 caracteres.',
                'document.required'          => 'CPF é obrigatório.',
                'document.regex'             => 'O CPF deve estar no formato correto (000.000.000-00).',
                'pix_key.required'           => 'O campo Chave Pix é obrigatório.',
                'medical_clinic_id.exists'   => 'A clínica médica selecionada é inválida.',
                'leave_end_date.date_format' => 'A data de fim da licença/afastamento deve ser uma data válida no formato DD/MM/AAAA.',
                'uniform_size.in'            => 'O tamanho do uniforme selecionado é inválido.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'title'   => 'Erro na validação',
                    'message' => implode("\n", $validator->errors()->all()),
                    'type'    => 'error'
                ], 422);
            }

            $leaveEndDate = null;
            if ($request->filled('leave_end_date')) {
                try {
                    $leaveEndDate = Carbon::createFromFormat('d/m/Y', $request->leave_end_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $leaveEndDate = Carbon::parse($request->leave_end_date)->format('Y-m-d');
                }
            }

            $data = [
                'name'                       => $request->name,
                'document'                   => Number::onlyNumber($request->document),
                'pix_key'                    => $request->pix_key,
                'observation'                => $request->observation,
                'is_leader'                  => $request->boolean('is_leader'),
                'is_supervisor'              => $request->boolean('is_supervisor'),
                'is_extra'                   => $request->boolean('is_extra'),
                'intermittent_contract'      => $request->boolean('intermittent_contract'),
                'city'                       => $request->city,
                'mobile'                     => $request->mobile,
                'group'                      => $request->group,
                'examined_medical_clinic_id' => $request->medical_clinic_id ?: null,
                'leave_end_date'             => $leaveEndDate,
                'uniform_size'               => $request->uniform_size ?: null,
            ];

            $collaborator = $id ? Collaborator::findOrFail($id) : new Collaborator();
            $collaborator->fill($data)->save();

            $this->city_has_collaborator($collaborator, $request->input('cities_can_work', []));

            DB::commit();

            return response()->json([
                'title'   => 'Sucesso!',
                'message' => $id ? 'Colaborador atualizado com sucesso!' : 'Colaborador cadastrado com sucesso!',
                'type'    => 'success'
            ], $id ? 200 : 201);
        } catch (Exception $exception) {
            DB::rollBack();

            return response()->json([
                'title'   => 'Erro ao processar',
                'message' => $exception->getMessage(),
                'type'    => 'error'
            ], 500);
        }
    }

    public function city_has_collaborator($collaborator, $cities)
    {
        CityHasCollaborator::where('collaborator_id', $collaborator->id)->delete();

        foreach ($cities as $city) {
            CityHasCollaborator::create([
                'collaborator_id' => $collaborator->id,
                'city_id'         => $city,
                'active'          => true,
            ]);
        }
    }

    public function table(Request $request)
    {
        $query = Collaborator::where('active', true);

        if ($request->has('groups') && is_array($request->groups) && count($request->groups) > 0) {
            $query->whereIn('group', $request->groups);
        }

        $collaborators = $query->get();

        return DataTables::of($collaborators)
            ->addColumn('name', fn($collaborator) => $collaborator->name)
            ->addColumn('actions', function ($collaborator) {
                return '
                    <div class="demo-inline-spacing">
                        <a type="button" class="btn btn-icon btn-primary" href="' . route('collaborators.edit', [$collaborator->id]) . '">
                            <span class="tf-icons bx bx-pencil"></span>
                        </a>
                        <button type="button" class="btn btn-icon btn-danger" onclick="remove(' . $collaborator->id . ')">
                            <span class="tf-icons bx bx-trash"></span>
                        </button>
                    </div>
                ';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }



    public function exportPdf(Request $request)
    {
        $query = Collaborator::where('active', true);

        if ($request->has('groups') && is_array($request->groups) && count($request->groups) > 0) {
            $query->whereIn('group', $request->groups);
        }

        $collaborators = $query->withMax(['dailyRates' => function ($q) {
            $q->where('active', true);
        }], 'start')
        ->orderBy('group')
        ->orderBy('name')
        ->get()
        ->map(function ($collaborator) {
            $lastDate = $collaborator->daily_rates_max_start 
                ? Carbon::parse($collaborator->daily_rates_max_start)->startOfDay() 
                : null;

            if ($lastDate) {
                $daysInactive = (int) $lastDate->diffInDays(now()->startOfDay(), false);

                if ($lastDate->isFuture()) {
                    $collaborator->status_tag = 'success';
                    $collaborator->status_label = 'Agendado';
                } elseif ($daysInactive > 20) {
                    $collaborator->status_tag = 'danger';
                    $collaborator->status_label = "{$daysInactive} dias sem diária";
                } elseif ($daysInactive >= 7) {
                    $collaborator->status_tag = 'warning';
                    $collaborator->status_label = "{$daysInactive} dias sem diária";
                } else {
                    $collaborator->status_tag = 'success';
                    $collaborator->status_label = $daysInactive === 0 ? 'Hoje' : "Há {$daysInactive} dia(s)";
                }
            } else {
                $collaborator->status_tag = 'danger';
                $collaborator->status_label = 'Sem diárias';
            }

            $collaborator->last_daily_date = $lastDate;

            return $collaborator;
        });

        $groupedCollaborators = $collaborators->groupBy('group');

        $html = View::make('reports.collaboratorsPerGroup', [
            'groupedCollaborators' => $groupedCollaborators,
            'totalGeneral'        => $collaborators->count()
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('colaboradores.pdf', ['Attachment' => false]);
    }

    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            $user = Collaborator::findOrFail($id);
            $user->active = false;
            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'Colaborador removido com sucesso!',
                'data'    => $user
            ], 200);
        } catch (Exception $exception) {
            DB::rollBack();

            return response()->json([
                'title'   => 'Erro na ação',
                'message' => $exception->getMessage(),
                'type'    => 'error'
            ], 500);
        }
    }

    public function getPixKey($id)
    {
        $collaborator = Collaborator::where('id', $id)->first();
        return $collaborator?->pix_key ?? "";
    }
}
