<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Statement of Cash Flows, read-only, built from fims.general_ledger — same one-query-over-the-
 * ledger approach as IncomeStatementService.php / BalanceSheetService.php. Laid out to match NHA's
 * printed "Statements of Cash Flow" exactly (Operating / Investing / Financing Activities, Note
 * column, Current Year / Previous Year), using the Direct Method: every line is a named cash
 * inflow or outflow, not a reconciliation from surplus.
 *
 * How each line is derived
 *   Unlike the Income Statement/Balance Sheet (which roll up by chart-of-accounts hierarchy),
 *   this statement's lines are specific NHA program/agency labels that don't correspond to chart
 *   groups, so LINE_ITEMS below hand-maps each printed line to the chart_of_accounts code(s) that
 *   actually carry it. Each line is one of:
 *     - 'net'   : credit-normal or debit-normal net movement for the year (one column value)
 *     - 'debit' / 'credit' : gross movement on ONE side only, for lines that split a single
 *       account's debits and credits across two different statement rows (e.g. Trust Liabilities:
 *       credits received show under Operating inflows, debits paid out show under outflows — net
 *       would hide both).
 *   A handful of printed lines (Bayanihan Act BTr refund, Expropriated Account closure, and 5 of
 *   the 6 "Disbursements made for:" project lines) have no dedicated chart-of-accounts code in the
 *   current chart — same kind of data gap already documented for CRDJ/CKDJ/ADADJ. They print as
 *   their own row at 0 rather than being silently dropped, so the layout matches the printed form;
 *   see NO_ACCOUNT_LINES.
 *   "Various Resettlement Projects" and "Other housing programs/projects" DO have a source: NHA
 *   keeps program-dedicated current/savings accounts for them (1-01-02-030-02, -03, -04), so those
 *   two lines use the net movement of those specific cash accounts instead of 0.
 *
 * Cash and Cash Equivalents, Beginning/Ending
 *   Beginning = cumulative balance (all postings before the fiscal year) of every 1-01 account.
 *   Ending is NOT re-queried — it is Beginning + Net Increase/(Decrease), the statement's own
 *   arithmetic, so the three activity sections and the cash roll-forward always tie out.
 */
class CashFlowService
{
    private string $tableGl  = 'fims.general_ledger';
    private string $tableCoa = 'fims.chart_of_accounts';

    private const SECTIONS = [
        'operating' => 'CASH FLOWS FROM OPERATING ACTIVITIES',
        'investing' => 'CASH FLOWS FROM INVESTING ACTIVITIES',
        'financing' => 'CASH FLOWS FROM FINANCING ACTIVITIES',
    ];

    // key => [label, section, mode, normal ('debit'|'credit'), codes (exact) | prefixes, excludeTitleLike?]
    // mode 'net': signed so a positive value is always a cash INFLOW and a negative value an OUTFLOW,
    // regardless of the account's own debit/credit-normal side.
    private const LINE_ITEMS = [
        // ── Operating: inflows ──────────────────────────────────────────────
        'subsidy_ng'              => ['label' => 'Subsidy from National Government',                         'section' => 'operating', 'mode' => 'net', 'normal' => 'credit', 'codes' => ['4-03-01-010']],
        'trust_fund_borrowed'     => ['label' => 'Cash borrowed from (reverted to) trust funds',              'section' => 'operating', 'mode' => 'net', 'normal' => 'credit', 'codes' => ['2-04-01-010']],
        'collections_awardees'    => ['label' => 'Collections from awardees/beneficiaries',                   'section' => 'operating', 'mode' => 'net', 'normal' => 'credit', 'codes' => ['1-03-01-160', '1-03-01-270']],
        'other_collections'       => ['label' => 'Other collections',                                         'section' => 'operating', 'mode' => 'net', 'normal' => 'credit', 'prefixes' => ['4-02-01']],
        'interest_earned'         => ['label' => 'Interest earned on savings/time deposit',                   'section' => 'operating', 'mode' => 'net', 'normal' => 'credit', 'codes' => ['4-02-02-210']],
        'trust_fund_received'     => ['label' => 'Trust Fund received from other agencies',                   'section' => 'operating', 'mode' => 'credit', 'normal' => 'credit', 'codes' => ['2-04-01-020']],
        'cash_donations'          => ['label' => 'Cash Donations Received',                                   'section' => 'operating', 'mode' => 'net', 'normal' => 'credit', 'codes' => ['4-04-02-010']],
        'btr_refund_ra11469'      => ['label' => 'Refund from BTr re: Remittance in compliance to RA No. 11469 (Bayanihan to Heal as One Act)', 'section' => 'operating', 'mode' => 'none'],
        'expropriated_closure'    => ['label' => 'Proceeds from Closure of Expropriated Account',             'section' => 'operating', 'mode' => 'none'],

        // ── Operating: outflows ─────────────────────────────────────────────
        'cash_paid_suppliers'     => ['label' => 'Cash paid to suppliers and employees',                      'section' => 'operating', 'mode' => 'net', 'normal' => 'debit', 'prefixes' => ['5-01', '5-02'], 'excludePrefixes' => ['5-02-14']],
        'disb_calamity'           => ['label' => 'Various calamity projects',                                 'section' => 'operating', 'mode' => 'none', 'indent' => true],
        'disb_resettlement'       => ['label' => 'Various resettlement projects',                             'section' => 'operating', 'mode' => 'net', 'normal' => 'debit', 'codes' => ['1-01-02-030-02'], 'indent' => true],
        'disb_marawi'             => ['label' => 'Rehabilitation of most affected areas in Marawi City',      'section' => 'operating', 'mode' => 'none', 'indent' => true],
        'disb_afp_pnp'            => ['label' => 'AFP/PNP Housing',                                           'section' => 'operating', 'mode' => 'none', 'indent' => true],
        'disb_relocation'         => ['label' => 'Northrail/Southrail/North Triangle Relocation Projects',    'section' => 'operating', 'mode' => 'none', 'indent' => true],
        'disb_other_housing'      => ['label' => 'Other housing programs/projects',                           'section' => 'operating', 'mode' => 'net', 'normal' => 'debit', 'codes' => ['1-01-02-030-03', '1-01-02-030-04'], 'indent' => true],
        'remittances_trust'       => ['label' => 'Remittances to other agencies under Trust Agreement',       'section' => 'operating', 'mode' => 'debit', 'normal' => 'debit', 'codes' => ['2-04-01-020']],
        'financial_expenses'      => ['label' => 'Financial expenses',                                        'section' => 'operating', 'mode' => 'net', 'normal' => 'debit', 'prefixes' => ['5-03']],

        // ── Investing ────────────────────────────────────────────────────────
        'purchase_ppe'            => ['label' => 'Purchase of property and equipment',                        'section' => 'investing', 'mode' => 'net', 'normal' => 'debit', 'prefixes' => ['1-06'], 'excludeTitleLike' => '%Accumulated%'],
        'sale_unserviceable'      => ['label' => 'Collections on sale of unserviceable property',              'section' => 'investing', 'mode' => 'net', 'normal' => 'credit', 'codes' => ['4-05-01-170']],

        // ── Financing ────────────────────────────────────────────────────────
        'dividends_paid'          => ['label' => 'Dividends paid to the National Government',                 'section' => 'financing', 'mode' => 'net', 'normal' => 'debit', 'codes' => ['2-99-99-090']],
    ];

    /**
     * @param int|null $fiscalYr The fiscal year this statement covers (default: current year)
     * @param int|null $bookId   Optional fims.books filter; null = all books
     */
    public function fetchCashFlow(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $fy   = $fiscalYr ?: (int) date('Y');
            $prev = $fy - 1;
            $yr   = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $book = $bookId !== null ? ' AND g.book_id = ' . (int) $bookId : '';

            $rows = App\ExecuteRows(
                "SELECT g.account_code,
                        c.account_title,
                        SUM(CASE WHEN {$yr} = {$fy}   THEN g.debit  ELSE 0 END) AS debit_cur,
                        SUM(CASE WHEN {$yr} = {$fy}   THEN g.credit ELSE 0 END) AS credit_cur,
                        SUM(CASE WHEN {$yr} = {$prev} THEN g.debit  ELSE 0 END) AS debit_prev,
                        SUM(CASE WHEN {$yr} = {$prev} THEN g.credit ELSE 0 END) AS credit_prev
                 FROM {$this->tableGl} g
                 JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                 WHERE ({$yr} = {$fy} OR {$yr} = {$prev}){$book}
                 GROUP BY g.account_code, c.account_title",
                'DB'
            ) ?: [];

            $beginCur  = $this->cashBalanceBefore($fy, $bookId);
            $beginPrev = $this->cashBalanceBefore($prev, $bookId);

            return ['success' => true, 'data' => $this->build($rows, $fy, $prev, $bookId, $beginCur, $beginPrev)];
        } catch (\Exception $e) {
            App\LogError('CashFlowService::fetchCashFlow ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Statement of Cash Flows.'];
        }
    }

    /** Cumulative balance of every Cash and Cash Equivalents (1-01) account as of the start of $fy. */
    private function cashBalanceBefore(int $fy, ?int $bookId): float
    {
        $yr   = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
        $book = $bookId !== null ? ' AND g.book_id = ' . (int) $bookId : '';

        $row = App\ExecuteRow(
            "SELECT COALESCE(SUM(g.debit - g.credit), 0) AS bal
             FROM {$this->tableGl} g
             WHERE g.account_code LIKE '1-01%' AND {$yr} < {$fy}{$book}",
            'DB'
        );

        return (float) ($row['bal'] ?? 0);
    }

    /** @return float Signed so positive is always an inflow and negative an outflow. */
    private function lineValue(array $meta, array $byAccount, string $side): float
    {
        if ($meta['mode'] === 'none') {
            return 0.0;
        }

        $codes = $meta['codes'] ?? [];
        if (!empty($meta['prefixes'])) {
            foreach (array_keys($byAccount) as $code) {
                foreach ($meta['prefixes'] as $prefix) {
                    if (strpos($code, $prefix) === 0) {
                        if (!empty($meta['excludePrefixes']) && $this->matchesAny($code, $meta['excludePrefixes'])) {
                            continue 2;
                        }
                        if (!empty($meta['excludeTitleLike']) && isset($byAccount[$code]['title'])
                            && stripos($byAccount[$code]['title'], trim($meta['excludeTitleLike'], '%')) !== false) {
                            continue 2;
                        }
                        $codes[] = $code;
                        break;
                    }
                }
            }
        }

        $debit = $credit = 0.0;
        foreach (array_unique($codes) as $code) {
            $debit  += $byAccount[$code][$side . '_debit'] ?? 0.0;
            $credit += $byAccount[$code][$side . '_credit'] ?? 0.0;
        }

        switch ($meta['mode']) {
            case 'debit':
                return -$debit; // gross debits only, always an outflow
            case 'credit':
                return $credit; // gross credits only, always an inflow
            default: // 'net'
                $movement = $meta['normal'] === 'debit' ? ($debit - $credit) : ($credit - $debit);

                return $meta['normal'] === 'debit' && in_array($meta['section'], ['operating', 'investing', 'financing'], true) && $this->isOutflowLine($meta)
                    ? -$movement
                    : $movement;
        }
    }

    private function matchesAny(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (strpos($code, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Debit-normal lines under Operating/Investing/Financing are cash OUTFLOWS (an increase in an
     * expense or asset account consumes cash) except the two program cash accounts, which print a
     * raw balance movement, not an outflow.
     */
    private function isOutflowLine(array $meta): bool
    {
        return !in_array($meta['label'], ['Various resettlement projects', 'Other housing programs/projects'], true);
    }

    private function build(array $rows, int $fy, int $prev, ?int $bookId, float $beginCur, float $beginPrev): array
    {
        $byAccount = [];
        foreach ($rows as $r) {
            $byAccount[$r['account_code']] = [
                'title'        => $r['account_title'],
                'cur_debit'    => (float) $r['debit_cur'],
                'cur_credit'   => (float) $r['credit_cur'],
                'prev_debit'   => (float) $r['debit_prev'],
                'prev_credit'  => (float) $r['credit_prev'],
            ];
        }

        $sections = [];
        foreach (self::SECTIONS as $key => $label) {
            $sections[$key] = ['key' => $key, 'label' => $label, 'lines' => [], 'subtotal_cur' => 0.0, 'subtotal_prev' => 0.0];
        }

        foreach (self::LINE_ITEMS as $key => $meta) {
            $cur  = round($this->lineValue($meta, $byAccount, 'cur'), 2);
            $prv  = round($this->lineValue($meta, $byAccount, 'prev'), 2);
            $sec  = $meta['section'];
            $sections[$sec]['lines'][] = [
                'key' => $key, 'label' => $meta['label'], 'indent' => !empty($meta['indent']),
                'cur' => $cur, 'prev' => $prv,
            ];
            $sections[$sec]['subtotal_cur']  += $cur;
            $sections[$sec]['subtotal_prev'] += $prv;
        }

        foreach ($sections as &$s) {
            $s['subtotal_cur']  = round($s['subtotal_cur'], 2);
            $s['subtotal_prev'] = round($s['subtotal_prev'], 2);
        }
        unset($s);

        $netCur  = round($sections['operating']['subtotal_cur'] + $sections['investing']['subtotal_cur'] + $sections['financing']['subtotal_cur'], 2);
        $netPrev = round($sections['operating']['subtotal_prev'] + $sections['investing']['subtotal_prev'] + $sections['financing']['subtotal_prev'], 2);

        return [
            'fiscal_yr' => $fy,
            'prev_year' => $prev,
            'book_id'   => $bookId,
            'sections'  => array_values($sections),
            'totals'    => [
                'net_increase_cur'  => $netCur,
                'net_increase_prev' => $netPrev,
                'beginning_cur'     => round($beginCur, 2),
                'beginning_prev'    => round($beginPrev, 2),
                'ending_cur'        => round($beginCur + $netCur, 2),
                'ending_prev'       => round($beginPrev + $netPrev, 2),
            ],
        ];
    }
}
