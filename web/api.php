<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array
{
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function addLog(string $agent, string $msg): void
{
    db()->prepare('INSERT INTO log(ts,agent_id,msg) VALUES(?,?,?)')->execute([time(), $agent, mb_substr($msg, 0, 300)]);
}

$action = $_GET['action'] ?? '';

// ---------- Endpoint de los agentes (autenticado por token) ----------
if ($action === 'agent_sync') {
    if (!hash_equals(AGENT_TOKEN, $_SERVER['HTTP_X_TOKEN'] ?? '')) {
        out(['error' => 'token'], 403);
    }
    $b = body();
    $id = $b['agent_id'] ?? '';
    if (!isset(AGENTS[$id])) {
        out(['error' => 'agente desconocido'], 400);
    }
    $pdo = db();
    $pdo->prepare('INSERT INTO agents(agent_id,last_seen,status) VALUES(?,?,?)
        ON DUPLICATE KEY UPDATE last_seen=VALUES(last_seen), status=VALUES(status)')
        ->execute([$id, time(), json_encode($b['status'] ?? [], JSON_UNESCAPED_UNICODE)]);
    foreach (array_slice($b['log'] ?? [], 0, 50) as $m) {
        addLog($id, (string)$m);
    }
    if (random_int(1, 50) === 1) {
        $pdo->exec('DELETE FROM log WHERE id < (SELECT m FROM (SELECT MAX(id)-300 AS m FROM log) t)');
    }
    $q = $pdo->prepare('SELECT auto FROM agents WHERE agent_id=?');
    $q->execute([$id]);
    $auto = (int)$q->fetchColumn();

    $q = $pdo->prepare('SELECT id,cmd FROM commands WHERE agent_id=? AND done=0 ORDER BY id');
    $q->execute([$id]);
    $cmds = $q->fetchAll();
    if ($cmds) {
        $pdo->prepare('UPDATE commands SET done=1 WHERE agent_id=? AND done=0')->execute([$id]);
    }
    out([
        'server_time' => time(),
        'auto' => $auto,
        'commands' => $cmds,
        'events' => $pdo->query('SELECT id,name,scene,input_name,start_at,end_at,prestart_sec FROM events ORDER BY start_at')->fetchAll(),
    ]);
}

// ---------- Endpoints del panel (sesión) ----------
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

if ($action === 'login') {
    $b = body();
    if (hash_equals(PANEL_PASSWORD, (string)($b['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        out(['ok' => true]);
    }
    sleep(1);
    out(['error' => 'Clave incorrecta'], 401);
}

if (empty($_SESSION['ok'])) {
    out(['error' => 'auth'], 401);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF'] ?? '')) {
    out(['error' => 'csrf'], 403);
}

$pdo = db();

switch ($action) {
    case 'logout':
        session_destroy();
        out(['ok' => true]);

    case 'state':
        $agents = [];
        foreach (AGENTS as $id => $label) {
            $q = $pdo->prepare('SELECT last_seen,status,auto FROM agents WHERE agent_id=?');
            $q->execute([$id]);
            $r = $q->fetch() ?: ['last_seen' => 0, 'status' => '{}', 'auto' => 1];
            $agents[] = [
                'id' => $id,
                'label' => $label,
                'online' => time() - (int)$r['last_seen'] <= 20,
                'seconds_ago' => $r['last_seen'] ? time() - (int)$r['last_seen'] : null,
                'auto' => (int)$r['auto'],
                'status' => json_decode($r['status'] ?? '{}', true) ?: new stdClass(),
            ];
        }
        $events = $pdo->query('SELECT * FROM events ORDER BY start_at')->fetchAll();
        foreach ($events as &$e) {
            $e['start_local'] = date('Y-m-d\TH:i', $e['start_at']);
            $e['end_local'] = date('Y-m-d\TH:i', $e['end_at']);
        }
        $log = $pdo->query('SELECT ts,agent_id,msg FROM log ORDER BY id DESC LIMIT 40')->fetchAll();
        foreach ($log as &$l) {
            $l['time'] = date('d/m H:i:s', $l['ts']);
        }
        out([
            'csrf' => $_SESSION['csrf'],
            'now' => date('d/m/Y H:i:s'),
            'agents' => $agents,
            'events' => $events,
            'log' => $log,
        ]);

    case 'save_event':
        $b = body();
        $tz = new DateTimeZone('America/Mexico_City');
        $s = DateTime::createFromFormat('Y-m-d\TH:i', (string)($b['start'] ?? ''), $tz);
        $e = DateTime::createFromFormat('Y-m-d\TH:i', (string)($b['end'] ?? ''), $tz);
        if (!$s || !$e || $e <= $s) {
            out(['error' => 'Fechas inválidas'], 400);
        }
        $pdo->prepare('UPDATE events SET scene=?, input_name=?, start_at=?, end_at=?, prestart_sec=? WHERE id=?')
            ->execute([
                trim((string)$b['scene']),
                trim((string)$b['input_name']),
                $s->getTimestamp(),
                $e->getTimestamp(),
                max(0, (int)$b['prestart_min']) * 60,
                (int)$b['id'],
            ]);
        addLog('panel', 'Horario actualizado: evento ' . (int)$b['id']);
        out(['ok' => true]);

    case 'command':
        $b = body();
        $id = $b['agent_id'] ?? '';
        $cmd = $b['cmd'] ?? '';
        if (!isset(AGENTS[$id]) || !in_array($cmd, ['start', 'stop', 'resync'], true)) {
            out(['error' => 'parámetros'], 400);
        }
        $pdo->prepare('INSERT INTO commands(agent_id,cmd,created) VALUES(?,?,?)')->execute([$id, $cmd, time()]);
        if ($cmd === 'start') {
            $pdo->prepare('UPDATE agents SET auto=1 WHERE agent_id=?')->execute([$id]);
        } elseif ($cmd === 'stop') {
            $pdo->prepare('UPDATE agents SET auto=0 WHERE agent_id=?')->execute([$id]);
        }
        addLog('panel', "Orden '$cmd' para $id");
        out(['ok' => true]);

    case 'set_auto':
        $b = body();
        if (!isset(AGENTS[$b['agent_id'] ?? ''])) {
            out(['error' => 'parámetros'], 400);
        }
        $pdo->prepare('UPDATE agents SET auto=? WHERE agent_id=?')->execute([empty($b['auto']) ? 0 : 1, $b['agent_id']]);
        addLog('panel', 'Automático ' . (empty($b['auto']) ? 'APAGADO' : 'encendido') . ' en ' . $b['agent_id']);
        out(['ok' => true]);
}

out(['error' => 'acción desconocida'], 404);
