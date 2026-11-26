<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Statement of Cash Flows print form — what the Cash Flow page shows (app/pages/cash-flow-page.php):
 * Particulars · Note · Current Year · Previous Year, portrait — the Operating, Investing and Financing Activities, then the
 * Cash and Cash Equivalents roll-forward (Net Increase + Beginning = Ending).
 *
 * It is the Balance Sheet's layout (same title block, row kinds and payload — section bands are `major`, the lines `account`,
 * the "Disbursements made for:" lines `detail`, section totals `subtotal`, the roll-forward totals `total` — a grand row in normal case, as the page prints them), so it calls
 * BalanceSheetPrintService for the model and the HTML and changes only the certification wording, the column layout (the Note
 * column is narrow, centred and blank — the page never fills it, as the printed form leaves it for the note numbers) and the file
 * name. Payload and layout: see BalanceSheetPrintService.
 *
 * File name `Statement-of-Cash-Flows_<year>` — the same as the page's Excel. PDF / HTML only. Printed through
 * PrintService::print('CF', …).
 */
class CashFlowPrintService
{
    private const CERTIFICATION = 'I HEREBY CERTIFY that the foregoing Statement of Cash Flows is a true and complete statement of the '
        . 'cash receipts and disbursements as shown in the books of accounts, prepared and presented in conformity with International '
        . 'Public Sector Accounting Standards (IPSAS).';

    public function model(array $p): array
    {
        $p['widths'] = $p['widths'] ?? [54, 8, 19, 19];
        $p['aligns'] = $p['aligns'] ?? ['left', 'center', 'right', 'right'];
        $m = (new BalanceSheetPrintService())->model($p, 'Statement-of-Cash-Flows', self::CERTIFICATION);
        $fy = PrintService::text($p['fiscal_yr'] ?? '');
        $m['file_base'] = 'Statement-of-Cash-Flows_' . (preg_match('/^\d{4}$/', $fy) ? $fy : 'all-years');

        return $m;
    }

    public function html(array $m, string $media = 'screen'): string
    {
        return (new BalanceSheetPrintService())->html($m, $media);
    }
}
