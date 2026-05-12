<?php

namespace App\Http\Controllers;

use App\BlueUtils\Number;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CityHasCollaborator;
use App\Models\Collaborator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

use function Laravel\Prompts\select;

class CollaboratorsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return View('app.collaborators.index', ['collaborators' => Collaborator::getActive()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $cities = City::all();
        return View('app.collaborators.edit', ['cities' => $cities]);
    }

    public function table(Request $request){
        $collaborators = Collaborator::query()
            ->where('active', '=', true)
            ->orderBy('name');

        return DataTables::of($collaborators)
            ->addColumn('name', function ($collaborator) {
                return $collaborator->name;
            })
            ->addColumn('actions', function ($collaborator) {
                return '
                    <div class="demo-inline-spacing">
                        <a type="button" class="btn btn-icon btn-primary" href="'. route('collaborators.edit', [$collaborator->id]) . '">
                            <span class="tf-icons bx bx-pencil"></span>
                        </a>
                        <button type="button" class="btn btn-icon btn-danger" onclick="remove(' . $collaborator->id . ')">
                            <span class="tf-icons bx bx-trash"></span>
                        </button>
                    </div>
                ';
            })
            ->rawColumns(['actions']) // Permite renderizar HTML no DataTables
            ->make(true);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'document' => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
                'pix_key' => ['required'],
            ], [
                'name.required' => 'O campo nome é obrigatório.',
                'name.string' => 'O nome deve ser um texto válido.',
                'name.max' => 'O nome não pode ter mais de 255 caracteres.',
                'document.required' => 'CPF é obrigatório.',
                'document.regex' => 'O CPF deve estar no formato correto (000.000.000-00).',
                'pix_key.required' => 'O campo Chave Pix é obrigatório.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => implode("\n", $validator->errors()->all()),
                ], 422);
            }

            $collaborator = Collaborator::create([
                'name' => $request->name,
                'document' => Number::onlyNumber($request->document),
                'pix_key' => $request->pix_key,
                'observation' => $request->observation,
                'is_leader' => $request->is_leader == 'on'? 1 :  0,
                'is_supervisor' => $request->is_supervisor == 'on'? 1 :  0,
                'is_extra' => $request->is_extra == 'on'? 1 :  0,
                'intermittent_contract' => $request->intermittent_contract == 'on'? 1 : 0,
                'city' => $request->city,
                'mobile' => $request->mobile,
            ]);

            $this->city_has_collaborator($collaborator, $request->input('cities_can_work', []));
            DB::commit();

            return response()->json([
                'title' => 'Sucesso!',
                'message' => 'Colaborador cadastrado com sucesso!',
                'type' => 'success'
            ], 201);

        } catch(Exception $exception) {

            DB::rollBack();

            return response()->json([
                'title' => 'Erro na validação',
                'message' => $exception->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $collaborator = Collaborator::findOrFail($id);
        $selectedCities = CityHasCollaborator::where('collaborator_id', $id)
            ->pluck('city_id')
            ->toArray();
        $cities = City::all();


        return view('app.collaborators.edit', [
            'collaborator' => $collaborator,
            'cities' => $cities,
            'selectedCities' => $selectedCities
        ]);
    }

    public function city_has_collaborator($collaborator, $cities)
    {
        CityHasCollaborator::where('collaborator_id', $collaborator->id)->delete();

        foreach ($cities as $city) {
            CityHasCollaborator::create([
                'collaborator_id' => $collaborator->id,
                'city_id' => $city,
                'active' => true,
            ]);
        }
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'document' => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
                'pix_key' => ['required'],
            ], [
                'name.required' => 'O campo nome é obrigatório.',
                'name.string' => 'O nome deve ser um texto válido.',
                'name.max' => 'O nome não pode ter mais de 255 caracteres.',
                'document.required' => 'CPF é obrigatório.',
                'document.regex' => 'O CPF deve estar no formato correto (000.000.000-00).',
                'pix_key.required' => 'O campo Chave Pix é obrigatório.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => implode("\n", $validator->errors()->all()),
                ], 422);
            }

            $collaborator = Collaborator::findOrFail($id);
            $collaborator->update([
                'name' => $request->name,
                'document' => Number::onlyNumber($request->document),
                'pix_key' => $request->pix_key,
                'observation' => $request->observation,
                'is_leader' => $request->is_leader == 'on'? 1 :  0,
                'is_supervisor' => $request->is_supervisor == 'on'? 1 :  0,
                'is_extra' => $request->is_extra == 'on'? 1 :  0,
                'intermittent_contract' => $request->intermittent_contract == 'on'? 1 : 0,
                'city' => $request->city,
                'mobile' => $request->mobile,

            ]);

            $this->city_has_collaborator($collaborator, $request->input('cities_can_work', []));
            DB::commit();

            return response()->json([
                'title' => 'Sucesso!',
                'message' => 'Colaborador cadastrado com sucesso!',
                'type' => 'success'
            ], 201);

        } catch(Exception $exception) {

            DB::rollBack();

            return response()->json([
                'title' => 'Erro na validação',
                'message' => $exception->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {

            DB::beginTransaction();

            $user = Collaborator::find($id);
            $user->active = false;
            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'Colaborador removido com sucesso!',
                'data' => $user
            ], 201);

        } catch(Exception $exception) {

            DB::rollBack();

            return response()->json([
                'title' => 'Erro na validação',
                'message' => $exception->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    public function getPixKey($id) {
        $collaborator = Collaborator::query()->where('id', '=', $id)->first();
        return $collaborator?->pix_key ?? "";
    }
}
