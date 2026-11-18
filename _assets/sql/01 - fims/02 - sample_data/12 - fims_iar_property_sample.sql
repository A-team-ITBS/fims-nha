-- ============================================================================
-- SAMPLE DATA for testing the IAR module -> Property Records (items per property account, depreciation, quarterly
-- pro-forma JEV). Six RFA POs (SAMPLE-PO-PPE-01 .. 06), each with one IAR and delivery receipts whose account rows
-- are property accounts (1-06-xx-xxx), spread over Jan - Jul 2026 and several responsibility centers, so every quarter
-- and several categories have depreciation:
--
--   PO-01  1-06-05-030 ICT equipment        600,000 (2 items of 300,000)           DR 2026-01-15
--   PO-02  1-06-05-020 Office equipment     250,000                                DR 2026-02-10
--          1-06-07-010 Furniture & fixtures 120,000                                DR 2026-04-05
--   PO-03  1-06-06-010 Motor vehicles     1,850,000                                DR 2026-03-20
--   PO-04  1-06-05-070 Communication equip  380,000 + 5-02-03-990 supplies 60,000  DR 2026-05-12 (the supplies item is NOT a property account: not listed)
--   PO-05  1-06-05-110 Medical equipment    300,000 + 1-06-05-130 Sports 240,000   DR 2026-06-30
--   PO-06  1-06-05-080 Construction equip 2,400,000 + 1-06-99-990 Other PPE 95,000 DR 2026-07-08
--
-- Idempotent (safe to run again). Works with the project-block layout of budget_allocation (fims.budget_allocation_project holds the
-- lines) and with an older database that still has budget_allocation.line_transaction. The RCs are the first six of
-- fims.responsibility_code. Remove the samples with:
--   DELETE FROM fims.inspection_acceptance_report WHERE po_number LIKE 'SAMPLE-PO-PPE-%';
--   (and the RFAs: UPDATE fims.budget_allocation SET deleted_at = CURRENT_TIMESTAMP WHERE allocation_number LIKE 'SAMPLE-RFA-PPE-%';)
-- ============================================================================

DO $$
DECLARE
    s           RECORD;
    new_id      INTEGER;
    has_blocks  BOOLEAN := EXISTS (SELECT 1 FROM information_schema.tables  WHERE table_schema = 'fims' AND table_name = 'budget_allocation_project');
    has_legacy  BOOLEAN := EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'fims' AND table_name = 'budget_allocation' AND column_name = 'line_transaction');
    lines_json  JSONB;
BEGIN
    FOR s IN
        SELECT * FROM (VALUES
            (1, 'SAMPLE-PO-PPE-01', 'SAMPLE-RFA-PPE-01', 'IAR-SMP-PPE-01', 'SI-SMP-0001', DATE '2026-01-20', 600000.00, 'ICT equipment (laptops)'),
            (2, 'SAMPLE-PO-PPE-02', 'SAMPLE-RFA-PPE-02', 'IAR-SMP-PPE-02', 'SI-SMP-0002', DATE '2026-04-08', 370000.00, 'Office equipment and furniture'),
            (3, 'SAMPLE-PO-PPE-03', 'SAMPLE-RFA-PPE-03', 'IAR-SMP-PPE-03', 'SI-SMP-0003', DATE '2026-03-25', 1850000.00, 'Pick-up truck'),
            (4, 'SAMPLE-PO-PPE-04', 'SAMPLE-RFA-PPE-04', 'IAR-SMP-PPE-04', 'SI-SMP-0004', DATE '2026-05-15', 440000.00, 'Radio communication set and supplies'),
            (5, 'SAMPLE-PO-PPE-05', 'SAMPLE-RFA-PPE-05', 'IAR-SMP-PPE-05', 'SI-SMP-0005', DATE '2026-07-02', 540000.00, 'Medical and sports equipment'),
            (6, 'SAMPLE-PO-PPE-06', 'SAMPLE-RFA-PPE-06', 'IAR-SMP-PPE-06', 'SI-SMP-0006', DATE '2026-07-10', 2495000.00, 'Backhoe and other PPE')
        ) AS t(n, po, rfa, iar, si, si_date, amount, descr)
    LOOP
        -- the RFA (allocation_type RFA) that carries the PO number, the requesting RC and the requested amounts
        IF NOT EXISTS (SELECT 1 FROM fims.budget_allocation WHERE allocation_number = s.rfa) THEN
            INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, po_number, po_amount, description, created_at)
            VALUES ('RFA', s.rfa, 2026, (SELECT rc_id FROM fims.responsibility_code ORDER BY rc_id LIMIT 1 OFFSET s.n - 1), s.po, s.amount, s.descr, TIMESTAMP '2026-01-05 08:00')
            RETURNING allocation_id INTO new_id;

            lines_json := jsonb_build_array(jsonb_build_object('requested_amount', s.amount, 'allocated_amount', s.amount));
            IF has_blocks THEN
                INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, line_transaction) VALUES (new_id, 0, lines_json);
            ELSIF has_legacy THEN
                UPDATE fims.budget_allocation SET line_transaction = lines_json WHERE allocation_id = new_id;
            END IF;
        END IF;
    END LOOP;
END $$;

-- the IARs: one per PO, with its delivery receipts (account rows + items; an item for every account row of 50,000 or more)
INSERT INTO fims.inspection_acceptance_report (iar_number, po_number, si_number, si_date, line_transaction) VALUES
('IAR-SMP-PPE-01', 'SAMPLE-PO-PPE-01', 'SI-SMP-0001', '2026-01-20', $j$[
  {"dr_number":"DR-SMP-0101","delivery_date":"2026-01-15","total_amount":"600000.00",
   "lines":[{"account_code":"1-06-05-030","account_title":"Information and Communications Technology Equipment","amount":"600000.00"}],
   "items":[{"description":"Laptop computer, 15.6in, i7, 16GB RAM (Unit 1)","serial_no":"SMP-LT-0001","property_no":"2026-01-05-030-0001","amount":"300000.00","account_code":"1-06-05-030"},
            {"description":"Laptop computer, 15.6in, i7, 16GB RAM (Unit 2)","serial_no":"SMP-LT-0002","property_no":"2026-01-05-030-0002","amount":"300000.00","account_code":"1-06-05-030"}]}
]$j$::jsonb),
('IAR-SMP-PPE-02', 'SAMPLE-PO-PPE-02', 'SI-SMP-0002', '2026-04-08', $j$[
  {"dr_number":"DR-SMP-0201","delivery_date":"2026-02-10","total_amount":"250000.00",
   "lines":[{"account_code":"1-06-05-020","account_title":"Office Equipment","amount":"250000.00"}],
   "items":[{"description":"Digital photocopier, A3, multifunction","serial_no":"SMP-PC-0001","property_no":"2026-02-05-020-0001","amount":"250000.00","account_code":"1-06-05-020"}]},
  {"dr_number":"DR-SMP-0202","delivery_date":"2026-04-05","total_amount":"120000.00",
   "lines":[{"account_code":"1-06-07-010","account_title":"Furniture and Fixtures","amount":"120000.00"}],
   "items":[{"description":"Executive office furniture set (table, chair, cabinet)","serial_no":"SMP-FF-0001","property_no":"2026-04-07-010-0001","amount":"120000.00","account_code":"1-06-07-010"}]}
]$j$::jsonb),
('IAR-SMP-PPE-03', 'SAMPLE-PO-PPE-03', 'SI-SMP-0003', '2026-03-25', $j$[
  {"dr_number":"DR-SMP-0301","delivery_date":"2026-03-20","total_amount":"1850000.00",
   "lines":[{"account_code":"1-06-06-010","account_title":"Motor Vehicles","amount":"1850000.00"}],
   "items":[{"description":"Pick-up truck, 4x4, 2.8L diesel, double cab","serial_no":"SMP-MV-0001","property_no":"2026-03-06-010-0001","amount":"1850000.00","account_code":"1-06-06-010"}]}
]$j$::jsonb),
('IAR-SMP-PPE-04', 'SAMPLE-PO-PPE-04', 'SI-SMP-0004', '2026-05-15', $j$[
  {"dr_number":"DR-SMP-0401","delivery_date":"2026-05-12","total_amount":"440000.00",
   "lines":[{"account_code":"1-06-05-070","account_title":"Communication Equipment","amount":"380000.00"},
            {"account_code":"5-02-03-990","account_title":"Other Supplies and Materials Expenses","amount":"60000.00"}],
   "items":[{"description":"Base radio and 10 handheld radios, VHF","serial_no":"SMP-CE-0001","property_no":"2026-05-05-070-0001","amount":"380000.00","account_code":"1-06-05-070"},
            {"description":"Assorted consumable supplies (not a property account)","amount":"60000.00","account_code":"5-02-03-990"}]}
]$j$::jsonb),
('IAR-SMP-PPE-05', 'SAMPLE-PO-PPE-05', 'SI-SMP-0005', '2026-07-02', $j$[
  {"dr_number":"DR-SMP-0501","delivery_date":"2026-06-30","total_amount":"540000.00",
   "lines":[{"account_code":"1-06-05-110","account_title":"Medical Equipment","amount":"300000.00"},
            {"account_code":"1-06-05-130","account_title":"Sports Equipment","amount":"240000.00"}],
   "items":[{"description":"Ultrasound machine, portable","serial_no":"SMP-ME-0001","property_no":"2026-06-05-110-0001","amount":"300000.00","account_code":"1-06-05-110"},
            {"description":"Multi-purpose gym equipment set","serial_no":"SMP-SE-0001","property_no":"2026-06-05-130-0001","amount":"240000.00","account_code":"1-06-05-130"}]}
]$j$::jsonb),
('IAR-SMP-PPE-06', 'SAMPLE-PO-PPE-06', 'SI-SMP-0006', '2026-07-10', $j$[
  {"dr_number":"DR-SMP-0601","delivery_date":"2026-07-08","total_amount":"2495000.00",
   "lines":[{"account_code":"1-06-05-080","account_title":"Construction and Heavy Equipment","amount":"2400000.00"},
            {"account_code":"1-06-99-990","account_title":"Other Property, Plant and Equipment","amount":"95000.00"}],
   "items":[{"description":"Backhoe loader, 1.0 cu.m bucket","serial_no":"SMP-HE-0001","property_no":"2026-07-05-080-0001","amount":"1400000.00","account_code":"1-06-05-080"},
            {"description":"Attachment set and hydraulic breaker","serial_no":"SMP-HE-0002","property_no":"2026-07-05-080-0002","amount":"1000000.00","account_code":"1-06-05-080"},
            {"description":"Water pump and generator set","serial_no":"SMP-OP-0001","property_no":"2026-07-99-990-0001","amount":"95000.00","account_code":"1-06-99-990"}]}
]$j$::jsonb)
ON CONFLICT DO NOTHING;
