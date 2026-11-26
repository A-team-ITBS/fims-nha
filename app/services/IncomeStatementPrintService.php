<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Income Statement print form — the Statement of Financial Performance the Income Statement page shows
 * (app/pages/income-statement-page.php), in whichever View is on screen (Detailed / Condensed: Particulars · Current Year ·
 * Prior Year · INCREASE (DECREASE), portrait; Consolidated: Particulars · one column per book · TOTAL, landscape).
 *
 * It is the Balance Sheet's layout — same title block, same row kinds (major / group / subgroup / account / subtotal /
 * grand — the grand rows are the running "Surplus (Deficit)" checkpoints), same payload — so it calls
 * BalanceSheetPrintService for the model and the HTML and changes only the certification wording and the file name
 * (the way CafPrintService shares CbaPrintService's layout). Payload and layout: see BalanceSheetPrintService.
 *
 * File name `Income-Statement_<year>_<view>` — the same as the page's Excel. PDF / HTML only. Printed through
 * PrintService::print('IS', …).
 */
class IncomeStatementPrintService
{
    private const CERTIFICATION = 'I HEREBY CERTIFY that the foregoing Statement of Financial Performance is a true and complete statement of the '
        . 'revenue earned and expenses incurred as shown in the books of accounts, prepared and presented in conformity with International '
        . 'Public Sector Accounting Standards (IPSAS).';

    public function model(array $p): array
    {
        return (new BalanceSheetPrintService())->model($p, 'Income-Statement', self::CERTIFICATION);
    }

    public function html(array $m, string $media = 'screen'): string
    {
        return (new BalanceSheetPrintService())->html($m, $media);
    }
}
