<?php
try {
    $pdo = new PDO('pgsql:host=host.docker.internal;port=5432;dbname=fims', 'postgres', '123456');

    echo "--- fund_transfers referencing 26-11-0001 ---\n";
    $stmt = $pdo->query("SELECT fund_transfer_id, ftla_number, status, dv_numbers, transfer_amount, to_bank_account_id, jev_id FROM fims.fund_transfers WHERE dv_numbers LIKE '%26-11-0001%'");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { echo json_encode($r) . "\n"; }

    echo "--- DV 26-11-0001 status ---\n";
    $stmt2 = $pdo->query("SELECT dv.dv_id, dv.dv_no, st.name AS status FROM fims.disbursement_vouchers dv LEFT JOIN fims.settings_status st ON st.id = dv.status_id WHERE dv.dv_no = '26-11-0001'");
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) { echo json_encode($r) . "\n"; }

    echo "--- JEVs for that DV or PAYMENT type recently ---\n";
    $stmt3 = $pdo->query("SELECT jev_id, jev_no, dv_id, jev_type, source_module, doc_ref_no, total_debit, total_credit, status_id FROM fims.journal_entry_vouchers WHERE jev_type='PAYMENT' ORDER BY jev_id DESC LIMIT 5");
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) { echo json_encode($r) . "\n"; }
} catch (Exception $e) { echo "ERR: " . $e->getMessage() . "\n"; }
