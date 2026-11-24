-- ============================================================================
-- DV Deduction Remittances — which Remittance DV pays what approved Disbursement Vouchers withheld.
--
-- Remittance List (Deductions) page: BIR is paid once a MONTH per tax —
--   EWT (the ATC withholding tax) · Withholding VAT · Penalty Tax
-- through a Remittance DV ("Remittance of Expanded Withholding Taxes withheld for the month of …": claimant = BIR, no RFA,
-- Dr the liability account — Due to BIR / Value Added Tax Payable —, Cr Cash in Bank). The page's "Remit — create DV" button opens the
-- new-DV form for a month, filled in; saving that DV writes one row here per RELEASED voucher of that month it pays
-- (RemittanceListService::syncLink()). So ONE Remittance DV has MANY rows, one per source voucher.
--
-- One row per (source voucher, tax): a voucher's withholding is remitted once. The rows go when the Remittance DV is CANCELLED or deleted
-- (RemittanceListService::releaseDv()), which makes those vouchers "Ready to remit" again. A REJECTED Remittance DV keeps its rows —
-- Accounting edits and resubmits it. A voucher released after the DV was made is picked up by the next Remittance DV of that month.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.dv_deduction_remittances (
    remittance_id  SERIAL PRIMARY KEY,
    source_dv_id   INTEGER      NOT NULL REFERENCES fims.disbursement_vouchers (dv_id) ON DELETE CASCADE,
    type_key       VARCHAR(20)  NOT NULL CHECK (type_key IN ('EWT', 'VAT', 'PENALTY_TAX')),
    remit_dv_id    INTEGER      NOT NULL REFERENCES fims.disbursement_vouchers (dv_id) ON DELETE CASCADE,
    amount         NUMERIC(18,2) NOT NULL,
    created_by     INTEGER,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_dv_deduction_remittance UNIQUE (source_dv_id, type_key),
    CONSTRAINT ck_dv_deduction_remittance_self CHECK (source_dv_id <> remit_dv_id)
);

CREATE INDEX IF NOT EXISTS idx_dv_deduction_remittances_remit_dv ON fims.dv_deduction_remittances (remit_dv_id);

COMMENT ON TABLE  fims.dv_deduction_remittances IS 'Links the vouchers'' withheld EWT / Withholding VAT / Penalty Tax to the monthly Remittance DV that pays it to BIR (Remittance List page); many rows per Remittance DV';
COMMENT ON COLUMN fims.dv_deduction_remittances.source_dv_id IS 'The approved (RELEASED) voucher that withheld the amount';
COMMENT ON COLUMN fims.dv_deduction_remittances.type_key     IS 'EWT = all ATC lines of the voucher | VAT = its Withholding VAT line | PENALTY_TAX';
COMMENT ON COLUMN fims.dv_deduction_remittances.remit_dv_id  IS 'The monthly Remittance DV (claimant BIR, no RFA) paying it';
COMMENT ON COLUMN fims.dv_deduction_remittances.amount       IS 'This voucher''s share of the Remittance DV — what it withheld when the DV was saved';
