<?php

namespace App\Http\Controllers;

use App\Models\Collaborator;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\UserHasCompany;
use App\Support\AccessControl;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class UsersController extends Controller
{
    public function index()
    {
        return view('app.users.index', [
            'roles' => AccessControl::assignableRoles(),
            'canManageRoles' => $this->actorCanManageRoles(),
        ]);
    }

    public function table(Request $request)
    {
        $users = User::query()
            ->where('active', '=', true)
            ->when($request->filled('role'), function ($query) use ($request) {
                $role = $request->string('role')->toString();
                $query->where('role', $role);
            })
            ->orderBy('name');

        $canManageRoles = $this->actorCanManageRoles();
        $roles = AccessControl::assignableRoles();

        return DataTables::of($users)
            ->addColumn('name', function ($user) {
                return $user->name;
            })
            ->addColumn('role', function ($user) use ($canManageRoles, $roles) {
                $current = $user->role ?: 'employee';

                if (! $canManageRoles) {
                    return e($user->roleLabel());
                }

                $options = '';
                foreach ($roles as $value => $label) {
                    $selected = $current === $value ? 'selected' : '';
                    $options .= '<option value="'.e($value).'" '.$selected.'>'.e($label).'</option>';
                }

                return '<select class="form-select form-select-sm user-role-select" data-user-id="'.$user->id.'">'.$options.'</select>';
            })
            ->addColumn('actions', function ($user) {
                return '
                    <div class="demo-inline-spacing">
                        <a type="button" class="btn btn-icon btn-primary" href="'. route('users.edit', [$user->id]) . '">
                            <span class="tf-icons bx bx-pencil"></span>
                        </a>
                        <button type="button" class="btn btn-icon btn-danger" onclick="remove(' . $user->id . ')">
                            <span class="tf-icons bx bx-trash"></span>
                        </button>
                    </div>
                ';
            })
            ->rawColumns(['role', 'actions'])
            ->make(true);
    }

    public function deleted()
    {
        return view('app.users.deleted', [
            'roles' => AccessControl::assignableRoles(),
        ]);
    }

    public function deletedTable(Request $request)
    {
        $users = User::query()
            ->where('active', '=', false)
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->where('role', $request->string('role')->toString());
            })
            ->orderBy('name');

        return DataTables::of($users)
            ->addColumn('name', fn ($user) => $user->name)
            ->addColumn('email', fn ($user) => $user->email)
            ->addColumn('role', fn ($user) => e($user->roleLabel()))
            ->addColumn('updated_at', fn ($user) => optional($user->updated_at)->format('d/m/Y H:i'))
            ->addColumn('actions', function ($user) {
                $name = e($user->name);
                $reportUrl = route('users.report', ['id' => $user->id, 'type' => '__TYPE__']);

                return '<div class="demo-inline-spacing">
                    <button type="button" class="btn btn-icon btn-info" title="Relatórios"
                        onclick="openDeletedReports('.$user->id.', \''.addslashes($name).'\', \''.$reportUrl.'\')">
                        <span class="tf-icons bx bx-file"></span>
                    </button>
                </div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function report(Request $request, string $id, string $type)
    {
        User::findOrFail($id);

        $request->merge([
            'user_id' => [$id],
            'collaborator_id' => null,
        ]);

        $reports = app(ReportsController::class);

        return match ($type) {
            'registers' => $reports->registers($request),
            'daily-rates' => $reports->dailyRates($request),
            default => abort(404),
        };
    }

    public function create()
    {
        return view('app.users.edit', $this->formPayload());
    }

    public function store_user_has_company($allowedCompanies, User $user)
    {
        if (!is_array($allowedCompanies)) {
            $allowedCompanies = [];
        }
        //Remove anteriores
        DB::table('user_has_company')->where('user_id', $user->id)->delete();
        //Adiciona os novos
        foreach ($allowedCompanies as $companyId) {
            DB::table('user_has_company')->insert([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {

            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'lowercase',
                    'email',
                    'max:255',
                    Rule::unique(User::class)->where('active', true),
                ],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'role' => ['nullable', Rule::in(AccessControl::roleKeys())],
                'mobile' => ['nullable', 'string', 'max:20'],
            ], [
                'name.required' => 'O campo nome é obrigatório.',
                'name.string' => 'O nome deve ser um texto válido.',
                'name.max' => 'O nome não pode ter mais de 255 caracteres.',

                'email.required' => 'O campo e-mail é obrigatório.',
                'email.string' => 'O e-mail deve ser um texto válido.',
                'email.lowercase' => 'O e-mail deve estar em letras minúsculas.',
                'email.email' => 'O e-mail informado não é válido.',
                'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
                'email.unique' => 'Este e-mail já está em uso.',

                'password.required' => 'O campo senha é obrigatório.',
                'password.confirmed' => 'A confirmação da senha não confere.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => implode("\n", $validator->errors()->all()),
                ], 422);
            }

            $role = $this->actorCanManageRoles()
                ? ($request->input('role') ?: 'employee')
                : 'employee';

            User::releaseInactiveConflicts($request->email, $request->collaborator_id ?: null);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'collaborator_id' => $request->collaborator_id ?: null,
                'mobile' => $request->mobile,
                'role' => $role,
            ]);

            event(new Registered($user));

            $this->applyRoleAndPermissions($user, $role, $this->permissionsFromRequest($request), true);

            $this->store_user_has_company($request->allowed_companies, $user);

            DB::commit();

            return response()->json([
                'title' => 'Sucesso!',
                'message' => 'Usuário cadastrado com sucesso!',
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



    public function edit($id)
    {
        $user = User::findOrFail($id);

        $selectedCompanies = UserHasCompany::where('user_id', $user->id)
            ->where('active', true)
            ->pluck('company_id')
            ->toArray();

        return view('app.users.edit', $this->formPayload([
            'user' => $user,
            'selectedCompanies' => $selectedCompanies,
        ]));
    }

    public function update(Request $request, $id){
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)
                ->where('active', true)
                ->whereNot('id', $id),
            ],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['nullable', Rule::in(AccessControl::roleKeys())],
            'mobile' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'O campo nome é obrigatório.',
            'name.string' => 'O nome deve ser um texto válido.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.string' => 'O e-mail deve ser um texto válido.',
            'email.lowercase' => 'O e-mail deve estar em letras minúsculas.',
            'email.email' => 'O e-mail informado não é válido.',
            'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
            'email.unique' => 'Este e-mail já está em uso por outro usuário.',
            
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => implode("\n", $validator->errors()->all()),
            ], 422);
        }
        
        DB::beginTransaction();
        try {

            $user = User::findOrFail($id);

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'collaborator_id' => $request->collaborator_id ?: null,
                'mobile' => $request->mobile,
            ]);
            if ($request->filled('password')) {
                $user->password = $request->password;
                $user->save();
            }

            $role = $this->actorCanManageRoles()
                ? ($request->input('role') ?: ($user->role ?: 'employee'))
                : ($user->role ?: 'employee');

            $this->applyRoleAndPermissions($user, $role, $this->permissionsFromRequest($request), false);

            $this->store_user_has_company($request->allowed_companies, $user);
            
            DB::commit();
            return response()->json([
                'title' => 'Sucesso!',
                'message' => 'Usuário atualizado com sucesso!',
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

    public function updateRole(Request $request, $id)
    {
        abort_unless($this->actorCanManageRoles(), 403);

        $validator = Validator::make($request->all(), [
            'role' => ['required', Rule::in(AccessControl::roleKeys())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Oops!',
                'message' => implode("\n", $validator->errors()->all()),
                'type' => 'error',
            ], 422);
        }

        $user = User::findOrFail($id);
        $this->applyRoleAndPermissions($user, $request->string('role')->toString(), null, false);

        return response()->json([
            'title' => 'Sucesso!',
            'message' => 'Papel atualizado para '.$user->fresh()->roleLabel().'.',
            'type' => 'success',
        ]);
    }

    public function destroy($id){
        try {

            DB::beginTransaction();

            $user = User::find($id);
            $user->deactivate();

            DB::commit();

            return response()->json([
                'message' => 'Usuário removido com sucesso!',
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

    private function actorCanManageRoles(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    private function formPayload(array $extra = []): array
    {
        $canManageRoles = $this->actorCanManageRoles();

        $permissions = Permission::query()
            ->when(! $canManageRoles, function ($query) {
                $query->where('name', '!=', AccessControl::PERMISSION_SUPER_ADMIN);
            })
            ->get();

        return array_merge([
            'permissions' => $permissions,
            'collaborators' => Collaborator::getActiveLeaders(),
            'companies' => Company::getActive(),
            'roles' => AccessControl::assignableRoles(),
            'canManageRoles' => $canManageRoles,
        ], $extra);
    }

    private function permissionsFromRequest(Request $request): array
    {
        $permissions = $request->input('permissions', []);

        return is_array($permissions) ? $permissions : [];
    }

    private function applyRoleAndPermissions(User $user, string $role, ?array $permissionsInput, bool $isCreate): void
    {
        $legacyRoles = ['admin', 'dev', 'company'];
        $hasFormPermissions = $permissionsInput !== null;

        if (in_array($role, $legacyRoles, true)) {
            $user->role = $role;
            $user->save();
        } else {
            AccessControl::applyToUser($user, $role, ! $hasFormPermissions);
        }

        if ($hasFormPermissions) {
            $user->syncPermissions($this->permissionIdsFromRequest($user, $permissionsInput, $isCreate));
        }
    }

    private function permissionIdsFromRequest(User $user, array $permissionsInput, bool $isCreate): array
    {
        $ids = array_keys($permissionsInput, 'on', true);
        if ($ids === []) {
            $ids = array_keys($permissionsInput);
        }

        $ids = array_values(array_filter($ids, fn ($id) => is_numeric($id)));

        $superAdminId = Permission::query()
            ->where('name', AccessControl::PERMISSION_SUPER_ADMIN)
            ->value('id');

        if (! $this->actorCanManageRoles()) {
            $ids = array_filter($ids, fn ($id) => (int) $id !== (int) $superAdminId);

            if (! $isCreate && $superAdminId && $user->hasPermissionTo(AccessControl::PERMISSION_SUPER_ADMIN)) {
                $ids[] = $superAdminId;
            }
        }

        return array_values(array_unique($ids));
    }
}
