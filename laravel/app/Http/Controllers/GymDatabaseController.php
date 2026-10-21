<?php

namespace App\Http\Controllers;

use App\Services\OriginalGymSql;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GymDatabaseController extends Controller
{
    public function __construct(private OriginalGymSql $sql) {}

    public function index(): View
    {
        $database = DB::connection()->getDatabaseName();
        $counts = [];
        foreach (OriginalGymSql::TABLES as $table => $key) {
            $counts[$table] = DB::table($table)->count();
        }
        $objects = DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $database)->select('ROUTINE_NAME', 'ROUTINE_TYPE')->get();
        $triggers = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $database)->select('TRIGGER_NAME')->get();

        return view('gym.database', compact('counts', 'objects', 'triggers'));
    }

    public function table(string $table): View
    {
        abort_unless(isset(OriginalGymSql::TABLES[$table]), 404);
        $columns = array_values(array_diff(Schema::getColumnListing($table), ['MATKHAU', 'OTPCODE', 'PUBLIC_KEY_USED']));

        return view('gym.database-table', ['table' => $table, 'columns' => $columns, 'rows' => DB::table($table)->select($columns)->orderByDesc(OriginalGymSql::TABLES[$table])->paginate(30)]);
    }

    public function audit(Request $request): View
    {
        $data = $request->validate(['username' => 'nullable|string|max:50']);
        $sets = $this->sql->call('SP_GET_AUDIT_LOGS', [$data['username'] ?? null, null]);

        return view('gym.audit', ['logins' => $sets[0] ?? [], 'actions' => DB::table('gym_action_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')->select('l.*', 'u.name')->orderByDesc('l.id')->paginate(30)]);
    }

    public function accounts(): View
    {
        $accounts = [];
        $error = null;
        try {
            $accounts = array_values(array_filter($this->sql->call('GET_SQL_USERS', [], 'gym_admin')[0] ?? [], fn ($row) => str_starts_with($row->USERNAME, 'gym_web_') && ! in_array($row->USERNAME, config('gym.database_roles'), true)));
        } catch (QueryException|\PDOException $exception) {
            $error = 'Kết nối quản trị CSDL chưa có quyền thực hiện. Kiểm tra cấu hình GYM_ADMIN_DB_*.';
        }

        return view('gym.database-accounts', ['accounts' => $accounts, 'error' => $error, 'roles' => config('gym.database_roles')]);
    }

    public function accountAction(Request $request): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:create,grant,lock,unlock', 'username' => ['required', 'string', 'max:50', 'regex:/^gym_web_[a-zA-Z0-9_]{1,40}$/'], 'password' => 'required_if:action,create|nullable|string|min:12|max:50', 'role' => ['required_if:action,grant', 'nullable', Rule::in(config('gym.database_roles'))]]);
        if (in_array($data['username'], config('gym.database_roles'), true) || $data['username'] === config('database.connections.mysql.username')) {
            abort(403);
        }
        $procedure = match ($data['action']) {
            'create' => 'SP_CREATE_MANAGER_USER', 'grant' => 'SP_GRANT_ROLE_TO_USER', 'lock' => 'SP_LOCK_USER', 'unlock' => 'SP_UNLOCK_USER'
        };
        $arguments = match ($data['action']) {
            'create' => [$data['username'], $data['password']], 'grant' => [$data['username'], $data['role']], default => [$data['username']]
        };
        try {
            $this->sql->call($procedure, $arguments, 'gym_admin');
            if ($data['action'] === 'grant') {
                $pdo = DB::connection('gym_admin')->getPdo();
                DB::connection('gym_admin')->unprepared('SET DEFAULT ROLE '.$pdo->quote($data['role']).' TO '.$pdo->quote($data['username'])."@'%'");
            }
            $this->sql->audit('Quản trị tài khoản CSDL: '.$data['action'], $data['username'], ['role' => $data['role'] ?? null]);
        } catch (QueryException|\PDOException $exception) {
            throw ValidationException::withMessages(['account' => 'Không thực hiện được. Kiểm tra tài khoản đã tồn tại, role đã được tạo và quyền của kết nối quản trị.']);
        }

        return back()->with('success', 'Đã thực hiện thao tác quản trị CSDL.');
    }
}
