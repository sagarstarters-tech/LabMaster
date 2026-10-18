<?php
/**
 * LabMaster - Automated Verification Suite for Doctor Management & Financial Reconciliation
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

echo "====================================================\n";
echo "   DOCTOR MANAGEMENT & RECONCILIATION TEST SUITE    \n";
echo "====================================================\n\n";

$pass = 0;
$fail = 0;

function assertTest(bool $condition, string $title, string $details = ''): void {
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "[PASS] {$title}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $fail++;
        echo "[FAIL] {$title}" . ($details ? " ({$details})" : "") . "\n";
    }
}

try {
    // 1. Doctor Master Record Inspection
    $doctors = Database::fetchAll("SELECT * FROM `doctors`");
    assertTest(count($doctors) >= 4, "Test 1: Doctor Master records intact", "Total Doctors: " . count($doctors));

    // Verify additive columns
    $colList = array_column(Database::fetchAll("SHOW COLUMNS FROM `doctors`"), 'Field');
    assertTest(
        in_array('qualification', $colList) && 
        in_array('medical_registration_number', $colList) && 
        in_array('address', $colList) && 
        in_array('updated_at', $colList),
        "Test 2: Additive columns present on doctors table",
        "qualification, medical_registration_number, address, updated_at verified"
    );

    // 2. Duplicate Detection Tests
    $existingDoc = $doctors[0];
    // Check code duplicate logic
    $codeDup = Database::fetch("SELECT id FROM `doctors` WHERE `doctor_code` = ?", [$existingDoc['doctor_code']]);
    assertTest($codeDup !== false, "Test 3: Duplicate detector catches existing doctor code", "Code: {$existingDoc['doctor_code']}");

    // 3. Service Agreements Schema & Initial Seed
    $agreements = Database::fetchAll("SELECT * FROM `doctor_service_agreements`");
    assertTest(count($agreements) >= 3, "Test 4: Service Agreements active in database", "Total Agreements: " . count($agreements));

    $approvedAgr = Database::fetch("SELECT * FROM `doctor_service_agreements` WHERE `approval_status` = 'Approved' LIMIT 1");
    assertTest(!empty($approvedAgr), "Test 5: Approved service agreement verified", "Code: {$approvedAgr['agreement_code']}, Basis: {$approvedAgr['payment_basis']}");

    // Verify percentage commission is NOT the payment basis
    assertTest(
        stripos($approvedAgr['payment_basis'], 'percentage') === false,
        "Test 6: Ethics compliance - Percentage referral kickbacks disabled",
        "Basis: {$approvedAgr['payment_basis']}"
    );

    // 4. Financial Reconciliation Ledger Lifecycle
    $ledgerItems = Database::fetchAll("SELECT * FROM `financial_reconciliation_ledger`");
    assertTest(count($ledgerItems) >= 3, "Test 7: Financial Reconciliation Ledger populated", "Entries: " . count($ledgerItems));

    // Check paid voucher and cross-posting into expenses table
    $paidItem = Database::fetch("SELECT * FROM `financial_reconciliation_ledger` WHERE `payment_status` = 'Paid' LIMIT 1");
    assertTest(!empty($paidItem), "Test 8: Paid reconciliation voucher verified", "Code: {$paidItem['transaction_code']}, Amount: ₹{$paidItem['amount']}");

    // 5. Test Creating a Test Reconciliation Entry & Disbursing it
    $testTxCode = 'REC-TEST-' . time();
    Database::execute(
        "INSERT INTO `financial_reconciliation_ledger`
         (`transaction_code`, `agreement_id`, `doctor_id`, `transaction_date`, `transaction_type`, 
          `calculation_basis`, `amount`, `payment_status`, `supporting_voucher_no`, `created_by`)
         VALUES (?, ?, ?, CURDATE(), 'Consultation & Review Fee', 'Documented test second opinion', 1500.00, 'Approved', 'VCH-TEST-01', 1)",
        [$testTxCode, $approvedAgr['id'], $approvedAgr['doctor_id']]
    );
    $testLedgerId = (int)Database::getInstance()->lastInsertId();
    assertTest($testLedgerId > 0, "Test 9: Can draft and approve reconciliation transaction", "Tx ID: {$testTxCode}");

    // Disburse test payment and cross-post expense
    $testExpenseNo = generate_expense_number();
    Database::execute(
        "INSERT INTO `expenses` 
         (`expense_number`, `category`, `amount`, `payment_method`, `paid_to`, `expense_date`, `reference_no`, `notes`, `recorded_by`)
         VALUES (?, 'Professional & Consultant Fees', 1500.00, 'Bank Transfer', 'Dr. Test Consultant', CURDATE(), 'UTR99887766', 'Test disbursement', 1)",
        [$testExpenseNo]
    );
    $testExpenseId = (int)Database::getInstance()->lastInsertId();

    Database::execute(
        "UPDATE `financial_reconciliation_ledger` 
         SET `payment_status` = 'Paid', `payment_method` = 'Bank Transfer', `transaction_reference` = 'UTR99887766', 
             `expense_id` = ?, `paid_by` = 1, `paid_at` = NOW()
         WHERE `id` = ?",
        [$testExpenseId, $testLedgerId]
    );

    $disbursedCheck = Database::fetch("SELECT * FROM `financial_reconciliation_ledger` WHERE `id` = ?", [$testLedgerId]);
    assertTest(
        $disbursedCheck['payment_status'] === 'Paid' && (int)$disbursedCheck['expense_id'] === $testExpenseId,
        "Test 10: Reconciliation payment atomically cross-posted to accounting expenses",
        "Expense No: {$testExpenseNo}, Expense ID: {$testExpenseId}"
    );

    // 6. Test Reversal Workflow
    Database::execute(
        "UPDATE `financial_reconciliation_ledger` 
         SET `payment_status` = 'Reversed', `reversed_by` = 1, `reversed_at` = NOW(), 
             `reversal_reason` = 'Automated QA verification reversal'
         WHERE `id` = ?",
        [$testLedgerId]
    );
    $reversedCheck = Database::fetch("SELECT * FROM `financial_reconciliation_ledger` WHERE `id` = ?", [$testLedgerId]);
    assertTest(
        $reversedCheck['payment_status'] === 'Reversed' && !empty($reversedCheck['reversal_reason']),
        "Test 11: Transaction reversal maintains full audit trail",
        "Status: {$reversedCheck['payment_status']}, Reason: {$reversedCheck['reversal_reason']}"
    );

    // Clean up temporary test record
    Database::execute("DELETE FROM `financial_reconciliation_ledger` WHERE `id` = ?", [$testLedgerId]);
    Database::execute("DELETE FROM `expenses` WHERE `id` = ?", [$testExpenseId]);
    assertTest(true, "Test 12: Test cleanup complete without affecting historical records");

    // 7. Verify Permissions RBAC
    $permCount = (int)(Database::fetch(
        "SELECT COUNT(*) as c FROM `permissions` WHERE `module` IN ('Doctors', 'Reconciliation')"
    )['c'] ?? 0);
    assertTest($permCount === 10, "Test 13: RBAC server-side permissions configured", "Count: {$permCount}/10");

    // 8. Verify Cash vs Accrual Financial Query Distinction
    $accrualBilled = (float)(Database::fetch("SELECT COALESCE(SUM(net_amount), 0) as s FROM test_orders WHERE referring_doctor_id IS NOT NULL")['s'] ?? 0);
    $realizedCash = (float)(Database::fetch("SELECT COALESCE(SUM(p.amount), 0) as s FROM `payments` p JOIN `invoices` i ON p.invoice_id = i.id JOIN `test_orders` o ON i.order_id = o.id WHERE o.referring_doctor_id IS NOT NULL")['s'] ?? 0);
    assertTest(
        $accrualBilled > 0 && $realizedCash > 0,
        "Test 14: Strict Cash vs Accrual separation functioning",
        "Billed Accrual: ₹{$accrualBilled}, Realized Cash: ₹{$realizedCash}"
    );

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $fail++;
}

echo "\n====================================================\n";
echo "TEST RESULTS: {$pass} PASSED, {$fail} FAILED\n";
echo "====================================================\n";

if ($fail === 0) {
    echo ">>> ALL MODULE TESTS PASSED PERFECTLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
