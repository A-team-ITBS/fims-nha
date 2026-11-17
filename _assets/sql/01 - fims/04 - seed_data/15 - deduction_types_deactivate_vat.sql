-- VAT shouldn't be offered as a Deductions pill on the DV form — it's a withholding tax, already
-- covered by the ATC (Withholding Tax) grid on the same card (see "08 - deduction_types_other_
-- recoupment_penalty_vat.sql"), so letting Originating also pick it as a non-tax "deduction" risks
-- the same amount being withheld twice. Deactivate rather than delete: any DV already saved with
-- a VAT deduction line (fims.disbursement_voucher_deductions.deduction_type_id) keeps its FK and
-- still displays correctly; DisbursementVoucherService::fetchDeductionTypes() only lists
-- is_active = TRUE rows, so it simply stops appearing as a pickable option going forward.
UPDATE fims.deduction_types SET is_active = FALSE WHERE code = 'VAT';
