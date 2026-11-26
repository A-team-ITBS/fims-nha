<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Statement of Changes in Net Assets/Equity print form — what the Statement of Changes page shows
 * (app/pages/statement-of-changes-page.php): Description · Consolidated (or the picked book) · NHA (Main Office), portrait —
 * the opening balance, the changes during the year and the closing balance.
 *
 * It is the Balance Sheet's layout (same title block, row kinds and payload — section bands are `major`, the lines `account`,
 * "Net Changes" `subtotal`, the closing balance `grand`), so it calls BalanceSheetPrintService for the model and the HTML and
 * changes only the certification wording, the column widths and the file name (the way IncomeStatementPrintService does).
 * Payload and layout: see BalanceSheetPrintService.
 *
 * File name `Statement-of-Changes_<year>` — the same as the page's Excel. PDF / HTML only. Printed through
 * PrintService::print('SC', …).
 */
class StatementOfChangesPrintService
{
    private const CERTIFICATION = 'I HEREBY CERTIFY that the foregoing Statement of Changes in Net Assets/Equity is a true and complete '
        . 'statement of the changes in the net assets/equity as shown in the books of accounts, prepared and presented in conformity with '
        . 'International Public Sector Accounting Standards (IPSAS).';

    public function model(array $p): array
    {
        // a long description column and two amount columns, unless the page sends its own widths
        $p['widths'] = $p['widths'] ?? [56, 22, 22];
        $m = (new BalanceSheetPrintService())->model($p, 'Statement-of-Changes', self::CERTIFICATION);
        $m['file_base'] = 'Statement-of-Changes_' . self::year($p);

        return $m;
    }

    public function html(array $m, string $media = 'screen'): string
    {
        return (new BalanceSheetPrintService())->html($m, $media);
    }

    private static function year(array $p): string
    {
        $fy = PrintService::text($p['fiscal_yr'] ?? '');

        return preg_match('/^\d{4}$/', $fy) ? $fy : 'all-years';
    }
}
