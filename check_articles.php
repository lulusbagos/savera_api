<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'articles' ORDER BY ordinal_position");
    if (empty($cols)) {
        echo "TABLE NOT FOUND or no columns\n";
    } else {
        foreach ($cols as $c) echo $c->column_name . "\n";
    }
    // Also check sample data
    $rows = DB::select("SELECT id, type, company_id, status FROM articles LIMIT 5");
    echo "--- DATA SAMPLE ---\n";
    foreach ($rows as $r) echo "id={$r->id} type={$r->type} company_id={$r->company_id} status={$r->status}\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
