<?php

$host = '192.168.151.21';
$port = '5432';
$db = 'saverawatch';
$user = 'postgres';
$pass = 'Unggul@2026';

try {
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = <<<SQL
select
    e.id as employee_id,
    e.code,
    e.fullname,
    d.mac_address,
    u.email
from employees e
left join devices d on d.id = e.device_id
left join users u on u.id = e.user_id
where e.company_id = 1
  and e.device_id is not null
  and e.user_id is not null
order by e.id desc
limit 200
SQL;

    foreach ($pdo->query($sql) as $row) {
        echo $row['code'] . '|' . $row['employee_id'] . '|' . $row['mac_address'] . '|' . $row['email'] . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
