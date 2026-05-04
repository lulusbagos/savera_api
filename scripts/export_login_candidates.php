<?php

$host = '192.168.151.21';
$port = '5432';
$db = 'saverawatch';
$user = 'postgres';
$pass = 'Unggul@2026';
$companyCode = 'UDU';

$candidates = ['admin', '123456', 'password', '12345', '12345678', 'qwerty', 'admin123', '50423807'];

try {
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = <<<SQL
select
    u.id as user_id,
    u.email,
    u.password as pass_hash,
    e.id as employee_id,
    e.code as nik,
    d.mac_address
from users u
join employees e on e.user_id = u.id
join devices d on d.id = e.device_id
join companies c on c.id = e.company_id
where c.code = :company
  and e.device_id is not null
  and e.status = 1
  and d.is_active = true
order by e.id
SQL;

    $st = $pdo->prepare($sql);
    $st->execute(['company' => $companyCode]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = fopen(__DIR__ . '/users.from_db.csv', 'w');
    fputcsv($out, ['login', 'password', 'employee_id', 'mac_address', 'company']);

    $matched = 0;
    foreach ($rows as $r) {
        $login = trim((string) ($r['nik'] ?? ''));
        if ($login === '') {
            continue;
        }

        $found = null;
        foreach ($candidates as $cand) {
            if (password_verify($cand, (string) $r['pass_hash'])) {
                $found = $cand;
                break;
            }
        }
        if ($found === null) {
            continue;
        }

        fputcsv($out, [
            $login,
            $found,
            (int) $r['employee_id'],
            (string) $r['mac_address'],
            $companyCode,
        ]);
        $matched++;
    }
    fclose($out);

    echo "matched={$matched}" . PHP_EOL;
    echo "file=" . __DIR__ . '/users.from_db.csv' . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
