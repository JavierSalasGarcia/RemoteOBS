<?php
require __DIR__ . '/config.php';
date_default_timezone_set('America/Mexico_City');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        install($pdo);
    }
    return $pdo;
}

function ts(string $local): int
{
    return (new DateTime($local, new DateTimeZone('America/Mexico_City')))->getTimestamp();
}

function install(PDO $pdo): void
{
    if ($pdo->query("SHOW TABLES LIKE 'events'")->fetch()) {
        return;
    }
    $pdo->exec('CREATE TABLE events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL,
        scene VARCHAR(100) NOT NULL,
        input_name VARCHAR(100) NOT NULL,
        start_at INT NOT NULL,
        end_at INT NOT NULL,
        prestart_sec INT NOT NULL DEFAULT 300
    ) CHARACTER SET utf8mb4');
    $pdo->exec('CREATE TABLE agents (
        agent_id VARCHAR(20) PRIMARY KEY,
        last_seen INT NOT NULL DEFAULT 0,
        status TEXT,
        auto TINYINT NOT NULL DEFAULT 1
    ) CHARACTER SET utf8mb4');
    $pdo->exec('CREATE TABLE commands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id VARCHAR(20) NOT NULL,
        cmd VARCHAR(20) NOT NULL,
        created INT NOT NULL,
        done TINYINT NOT NULL DEFAULT 0
    ) CHARACTER SET utf8mb4');
    $pdo->exec('CREATE TABLE log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ts INT NOT NULL,
        agent_id VARCHAR(20) NOT NULL,
        msg VARCHAR(300) NOT NULL
    ) CHARACTER SET utf8mb4');

    $seed = $pdo->prepare('INSERT INTO events(name,scene,input_name,start_at,end_at,prestart_sec) VALUES(?,?,?,?,?,300)');
    foreach ([
        ['Miércoles', 'miercoles', '2026-10-14 10:45', '2026-10-14 17:30'],
        ['Jueves',    'jueves',    '2026-10-15 09:45', '2026-10-15 18:00'],
        ['Viernes',   'viernes',   '2026-10-16 08:45', '2026-10-16 10:30'],
    ] as [$n, $s, $a, $b]) {
        $seed->execute([$n, $s, $s, ts($a), ts($b)]);
    }
}
