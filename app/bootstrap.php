<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
date_default_timezone_set('America/Bogota');
require_once ROOT . '/vendor/autoload.php';
require_once __DIR__ . '/Migrations.php';
$config = require ROOT . '/config/example.php';
if (is_file(ROOT . '/config/local.php')) $config = array_replace_recursive($config, require ROOT . '/config/local.php');
if (getenv('APP_DATABASE')) $config['database'] = getenv('APP_DATABASE');
$GLOBALS['config'] = $config;
foreach (['storage', 'storage/uploads', 'storage/cache'] as $dir) if (!is_dir(ROOT . '/' . $dir)) mkdir(ROOT . '/' . $dir, 0700, true);
function uploadDir(): string { return dirname($GLOBALS['config']['database']).'/uploads'; }
if(!is_dir(uploadDir()))mkdir(uploadDir(),0700,true);

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . $GLOBALS['config']['database'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
        if (!defined('SKIP_DATABASE_MIGRATIONS') || !SKIP_DATABASE_MIGRATIONS) migrateDatabase($pdo);
    }
    return $pdo;
}
function rows(string $sql, array $params = []): array { $s=db()->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function row(string $sql, array $params = []): ?array { return rows($sql,$params)[0] ?? null; }
function run(string $sql, array $params = []): int { $s=db()->prepare($sql); $s->execute($params); return $s->rowCount(); }
function insert(string $table, array $data): int {
    $columns = implode(',',array_keys($data));
    run('INSERT INTO '.$table.' ('.$columns.') VALUES ('.implode(',',array_fill(0,count($data),'?')).')', array_values($data));
    return (int)db()->lastInsertId();
}
function atomic(callable $callback): mixed {
    db()->exec('BEGIN IMMEDIATE');
    try { $result=$callback(); db()->exec('COMMIT'); return $result; }
    catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
}
function setting(string $key, string $default = ''): string { return row('SELECT value FROM settings WHERE key=?',[$key])['value'] ?? $default; }
function setSetting(string $key, string $value): void { run('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$key,$value]); }
function now(): string { return date('Y-m-d H:i:s'); }
function e(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function hours(int $minutes): string { return intdiv($minutes,60).' h '.str_pad((string)($minutes%60),2,'0',STR_PAD_LEFT).' min'; }
function ensure(bool $ok, string $message): void { if (!$ok) throw new DomainException($message); }
function roles(array $u): array { return json_decode($u['roles'],true,512,JSON_THROW_ON_ERROR); }
function hasRole(array $u,string $role): bool { return in_array($role,roles($u),true); }
function event(int $actor, string $action, string $detail, ?int $request=null): void { insert('events',['actor_id'=>$actor,'request_id'=>$request,'action'=>$action,'detail'=>$detail,'created_at'=>now()]); }
function notify(int $userId,string $subject,string $body): void {
    $u=row('SELECT * FROM users WHERE id=?',[$userId]);
    if ($u) insert('outbox',['recipient'=>$u['email'],'subject'=>$subject,'body'=>$body,"created_at"=>now()]);
}
function user(): array {
    $u=row('SELECT u.*,d.name department FROM users u JOIN departments d ON d.id=u.department_id WHERE u.id=? AND active=1',[(int)($_SESSION['uid']??0)]);
    if (!$u) { header('Location: /?page=login'); exit; }
    return $u;
}
function redirect(string $url): never { header('Location: '.$url); exit; }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function authorizeAdmin(array $u): void { ensure(hasRole($u,'admin'),'Esta acción requiere un administrador de Talento Humano.'); }
function validDate(string $s): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s); return $d && $d->format('Y-m-d')===$s; }
function statusLabel(string $s): string { return ['pending'=>'En aprobación','approved'=>'Aprobada','returned'=>'Devuelta para corregir','rejected'=>'Rechazada','cancel_pending'=>'Cancelación en revisión','cancelled'=>'Cancelada','waiting'=>'Pendiente','skipped'=>'Sin efecto','released'=>'Liberado','reserved'=>'Reservado','consumed'=>'Descontado','sent'=>'Enviado','sending'=>'Enviando','failed'=>'Error de envío'][$s]??$s; }
function roleLabel(string $role): string { return ['manager'=>'Jefe inmediato','subsecretary'=>'Subsecretario','mayor'=>'Alcalde','admin'=>'Administrador de Talento Humano','payroll'=>'Nómina'][$role]??$role; }
function familyLabel(string $s): string { return ['permiso'=>'Permiso','compensatorio'=>'Compensatorio','remunerada'=>'Licencia remunerada','no_remunerada'=>'Licencia no remunerada'][$s]??$s; }
require_once __DIR__.'/Calendar.php';
require_once __DIR__.'/Workflow.php';
require_once __DIR__.'/Files.php';
require_once __DIR__.'/Designations.php';
