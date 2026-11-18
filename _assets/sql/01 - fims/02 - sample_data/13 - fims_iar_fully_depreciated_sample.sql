-- ============================================================================
-- SAMPLE DATA for testing the Property Records "Remarks *****" column: one RFA PO (SAMPLE-PO-PPE-07) with an IAR whose delivery receipt is
-- dated 2020-03-15, so its items are older than their useful life (60 months for equipment) and show "Fully Depreciated" (italic):
--   1-06-05-020 Office equipment 150,000   (2020-03-15)  → fully depreciated after Mar 2025 (accumulated = 90% = 135,000.00, net book value 15,000.00)
--   1-06-05-030 ICT equipment    180,000   (2020-03-15)  → accumulated 162,000.00, net book value 18,000.00
-- Depreciation starts the month after the delivery date; both items count in the "Fully Depreciated" card.
-- Idempotent. Works with the project-block layout of budget_allocation (fims.budget_allocation_project) and with an older database that still has
-- budget_allocation.line_transaction. Remove with:
--   DELETE FROM fims.inspection_acceptance_report WHERE po_number = 'SAMPLE-PO-PPE-07';
--   UPDATE fims.budget_allocation SET deleted_at = CURRENT_TIMESTAMP WHERE allocation_number = 'SAMPLE-RFA-PPE-07';
-- ============================================================================

DO $$
DECLARE
    new_id      INTEGER;
    has_blocks  BOOLEAN := EXISTS (SELECT 1 FROM information_schema.tables  WHERE table_schema = 'fims' AND table_name = 'budget_allocation_project');
    has_legacy  BOOLEAN := EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'fims' AND table_name = 'budget_allocation' AND column_name = 'line_transaction');
    lines_json  JSONB   := jsonb_build_array(jsonb_build_object('requested_amount', 330000.00, 'allocated_amount', 330000.00));
BEGIN
    IF NOT EXISTS (SELECT 1 FROM fims.budget_allocation WHERE allocation_number = 'SAMPLE-RFA-PPE-07') THEN
        INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, po_number, po_amount, description, created_at)
        VALUES ('RFA', 'SAMPLE-RFA-PPE-07', 2026, (SELECT rc_id FROM fims.responsibility_code ORDER BY rc_id LIMIT 1), 'SAMPLE-PO-PPE-07', 330000.00,
                'Older equipment (fully depreciated)', TIMESTAMP '2026-01-05 08:00')
        RETURNING allocation_id INTO new_id;
        IF has_blocks THEN
            INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, line_transaction) VALUES (new_id, 0, lines_json);
        ELSIF has_legacy THEN
            UPDATE fims.budget_allocation SET line_transaction = lines_json WHERE allocation_id = new_id;
        END IF;
    END IF;
END $$;

INSERT INTO fims.inspection_acceptance_report (iar_number, po_number, si_number, si_date, line_transaction) VALUES
('IAR-SMP-PPE-07', 'SAMPLE-PO-PPE-07', 'SI-SMP-0007', '2020-03-20', $j$[
  {"dr_number":"DR-SMP-0701","delivery_date":"2020-03-15","total_amount":"330000.00",
   "lines":[{"account_code":"1-06-05-020","account_title":"Office Equipment","amount":"150000.00"},
            {"account_code":"1-06-05-030","account_title":"Information and Communications Technology Equipment","amount":"180000.00"}],
   "items":[{"description":"Network printer and scanner set (delivered 2020 - fully depreciated)","serial_no":"SMP-OLD-0001","property_no":"2020-03-05-020-0001","amount":"150000.00","account_code":"1-06-05-020"},
            {"description":"Server computer (delivered 2020 - fully depreciated)","serial_no":"SMP-OLD-0002","property_no":"2020-03-05-030-0001","amount":"180000.00","account_code":"1-06-05-030"}]}
]$j$::jsonb)
ON CONFLICT DO NOTHING;
