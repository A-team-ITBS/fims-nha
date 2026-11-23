<?php
try {
    $pdo = new PDO('pgsql:host=host.docker.internal;port=5432;dbname=fims', 'postgres', '123456');
    echo "--- status_id 28 name ---\n";
    $stmt = $pdo->query("SELECT id, name, status_for FROM fims.settings_status WHERE id = 28");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { echo json_encode($r) . "\n"; }

    echo "--- JEV 3 lines ---\n";
    $stmt2 = $pdo->query("SELECT jevl_id, line_number, account_code, debit, credit, description FROM fims.journal_entry_voucher_lines WHERE jev_id = 3 ORDER BY line_number");
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) { echo json_encode($r) . "\n"; }

    echo "--- general_ledger rows for jev_id 3 ---\n";
    $stmt3 = $pdo->query("SELECT gl_id, jev_id, account_code, debit, credit, posted_by, posted_at FROM fims.general_ledger WHERE jev_id = 3 ORDER BY gl_id");
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) { echo json_encode($r) . "\n"; }
} catch (Exception $e) { echo "ERR: " . $e->getMessage() . "\n"; }
