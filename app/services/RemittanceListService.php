<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Remittance List (Deductions) — page app/pages/remittance-list-page.php (Reports menu), route app/routes/remittance_list.php.
 *
 * A list of what approved disbursement vouchers WITHHELD, one row per deduction line / ATC line, so Accounting can
 * see what is owed to BIR and others. It reads the tables the voucher form already fills:
 *   - fims.disbursement_voucher_deductions  (Retention, Advance Recoupment, Penalty Tax, VAT = Withholding VAT, Other)
 *   - fims.disbursement_voucher_atc_lines   (ATC withholding tax / EWT)
 *
 * A voucher is listed from FOR_TREASURY on: that is when Accounting's approval posts its journal entry, so the amounts have become
 * real payables (Due to BIR …). It stays through RELEASED. A cancelled / rejected / not-yet-approved voucher is not listed.
 *   remit_status  WITHHELD      the voucher is approved but its payment has not been released yet
 *                 READY         the voucher is RELEASED — the amount can now be remitted
 *                 IN_DV         a Remittance DV includes it and is still in process
 *                 REMITTED      that Remittance DV was RELEASED
 *                 NOT_REMITTED  Retention (held for the contractor) and Advance Recoupment (an advance recovered): not paid to an agency
 *
 * Remittance DV — one per MONTH and BIR tax (EWT · Withholding VAT · Penalty Tax), the way Accounting pays BIR ("Remittance of Expanded
 * Withholding Taxes withheld for the month of …"): every RELEASED voucher dated in that month whose withholding is not in a Remittance DV yet
 * goes into it. The page's "Pay to BIR by month" panel offers a "Remit — create DV" button per month and tax; it opens the Voucher page's
 * new-DV form filled by dvData() — claimant BIR, the month's total, the breakdown by rate (1% / 2% / 5% / 10% / 15% …) in the particulars,
 * the entry Dr the liability account (Due to BIR / VAT Payable) and Cr Cash in Bank — and shows the vendor-by-vendor schedule under it.
 * Saving the DV links the vouchers (fims.dv_deduction_remittances, one row per voucher + tax); DisbursementVoucherService calls
 * validateLink() / syncLink() then, and releaseDv() when the DV is cancelled or deleted. Same pattern as the payroll remittance DV
 * (DeductionRemittanceService). A voucher released after the DV was made goes into the next one for that month.
 *
 * Payroll deductions (PAYROLL_GSIS …) are left out: they belong to the payroll Deductions & Remittances page
 * (DeductionRemittanceService), which builds its own list from the imported payroll — so the Payroll column of the paper form is not here.
 */
class RemittanceListService
{
    private string $tDv       = 'fims.disbursement_vouchers';
    private string $tDed      = 'fims.disbursement_voucher_deductions';
    private string $tType     = 'fims.deduction_types';
    private string $tAtcLine  = 'fims.disbursement_voucher_atc_lines';
    private string $tAtc      = 'fims.atc_codes';
    private string $tStatus   = 'fims.settings_status';
    private string $tCoa      = 'fims.chart_of_accounts';
    private string $tLink     = 'fims.dv_deduction_remittances';

    private const LISTED_STATUSES = ['FOR_TREASURY', 'RELEASED'];
    // filter values: row type (EWT = an ATC line) / agency / remit status
    private const TYPES    = ['EWT', 'VAT', 'PENALTY_TAX', 'RETENTION', 'RECOUPMENT', 'OTHER'];
    private const AGENCIES = ['BIR' => 'BIR', 'OTHER' => 'Other payables', 'RETAINED' => 'Held for the contractor', 'INTERNAL' => 'Internal (advance recovery)'];
    private const STATUSES = ['WITHHELD', 'READY', 'IN_DV', 'REMITTED', 'NOT_REMITTED'];

    // What a Remittance DV can pay (the BIR types; Retention / Recoupment are not paid to an agency, Other has no known payee).
    public const REMIT_TYPES = ['EWT' => 'ATC Withholding Tax (EWT)', 'VAT' => 'Withholding VAT', 'PENALTY_TAX' => 'Penalty Tax'];
    // "Remittance of … withheld for the month of …" — the DV's particulars heading per type
    private const REMIT_HEADING = ['EWT' => 'Expanded Withholding Taxes', 'VAT' => 'Withholding VAT', 'PENALTY_TAX' => 'Penalty Tax'];
    private const RATE_ROWS     = [1, 2, 5, 10, 15]; // the rate rows the paper EWT remittance lists even when empty
    private const BIR_PAYEE     = 'Bureau of Internal Revenue (BIR)';
    private const CASH_ACCOUNT  = '1-01-02-020'; // Cash in Bank-Local Currency, Current Account — what the Remittance DV credits (no payment entry follows: nothing routes through Accounts Payable)
    private const DUE_ACCOUNT   = ['EWT' => '2-02-01-010', 'VAT' => '2-02-01-120', 'PENALTY_TAX' => '2-02-01-010']; // used when a line carries no account
    private const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    /**
     * Every deduction / ATC line of a listed voucher, with the columns the page shows. type_code is the deduction type's code, or
     * 'EWT' for an ATC line; agency and remit_status are derived from it (see the class comment).
     */
    private function itemsSql(): string
    {
        $listed = implode(', ', array_map(fn ($s) => $this->str($s), self::LISTED_STATUSES));

        $deductions = "SELECT dv.dv_id, dv.dv_no, dv.dv_date, dv.fiscal_yr, dv.claimant_name, st.name AS dv_status,
                              dv.gross_amount AS dv_gross, dv.net_amount AS dv_net,
                              d.line_number AS line_no, 'DEDUCTION' AS source, dt.code AS type_code,
                              CASE WHEN dt.code = 'VAT' THEN 'Withholding VAT' ELSE dt.name END AS type_name,
                              NULL::varchar AS atc_code, d.rate AS rate, d.gross_amount AS base, d.deduction_amount AS amount,
                              COALESCE(dt.account_code, d.account_code) AS account_code
                         FROM {$this->tDed} d
                         JOIN {$this->tType} dt ON dt.deduction_type_id = d.deduction_type_id
                         JOIN {$this->tDv} dv ON dv.dv_id = d.dv_id
                         JOIN {$this->tStatus} st ON st.id = dv.status_id
                        WHERE st.name IN ({$listed}) AND d.deduction_amount > 0 AND dt.code NOT LIKE 'PAYROLL\\_%'";

        $atc = "SELECT dv.dv_id, dv.dv_no, dv.dv_date, dv.fiscal_yr, dv.claimant_name, st.name AS dv_status,
                       dv.gross_amount AS dv_gross, dv.net_amount AS dv_net,
                       1000 + l.line_number AS line_no, 'ATC' AS source, 'EWT' AS type_code, 'ATC Withholding Tax' AS type_name,
                       l.atc_code AS atc_code, l.rate AS rate, l.tax_base AS base, l.withholding_tax AS amount,
                       ac.account_code AS account_code
                  FROM {$this->tAtcLine} l
                  JOIN {$this->tAtc} ac ON ac.atc_code = l.atc_code
                  JOIN {$this->tDv} dv ON dv.dv_id = l.dv_id
                  JOIN {$this->tStatus} st ON st.id = dv.status_id
                 WHERE st.name IN ({$listed}) AND l.withholding_tax > 0";

        // the Remittance DV (if any) paying each (voucher, type) — see fims.dv_deduction_remittances
        $links = $this->linkTableReady()
            ? "SELECT l.source_dv_id, l.type_key, l.remit_dv_id, rdv.dv_no AS remit_dv_no, rst.name AS remit_dv_status
                 FROM {$this->tLink} l
                 JOIN {$this->tDv} rdv ON rdv.dv_id = l.remit_dv_id
                 JOIN {$this->tStatus} rst ON rst.id = rdv.status_id"
            : 'SELECT NULL::integer AS source_dv_id, NULL::varchar AS type_key, NULL::integer AS remit_dv_id, NULL::varchar AS remit_dv_no, NULL::varchar AS remit_dv_status WHERE FALSE'; // table script not run yet

        return "SELECT y.*, coa.account_title,
                       CASE WHEN y.type_code IN ('EWT', 'VAT', 'PENALTY_TAX') THEN 'BIR'
                            WHEN y.type_code = 'RETENTION'  THEN 'RETAINED'
                            WHEN y.type_code = 'RECOUPMENT' THEN 'INTERNAL'
                            ELSE 'OTHER' END AS agency,
                       CASE WHEN y.type_code IN ('RETENTION', 'RECOUPMENT') THEN 'NOT_REMITTED'
                            WHEN rl.remit_dv_status = 'RELEASED' THEN 'REMITTED'
                            WHEN rl.remit_dv_id IS NOT NULL THEN 'IN_DV'
                            WHEN y.dv_status = 'RELEASED' THEN 'READY'
                            ELSE 'WITHHELD' END AS remit_status,
                       rl.remit_dv_id, rl.remit_dv_no, rl.remit_dv_status
                  FROM (SELECT x.*,
                               CASE WHEN x.type_code NOT IN ('EWT', 'VAT', 'PENALTY_TAX', 'RETENTION', 'RECOUPMENT') THEN 'OTHER' ELSE x.type_code END AS type_key
                          FROM ({$deductions} UNION ALL {$atc}) x) y
                  LEFT JOIN {$this->tCoa} coa ON coa.account_code = y.account_code
                  LEFT JOIN ({$links}) rl ON rl.source_dv_id = y.dv_id AND rl.type_key = y.type_key";
    }

    /** WHERE over itemsSql()'s columns for the page's filters (all optional, all whitelisted / quoted). */
    private function whereSql(array $f): string
    {
        $where = ['1 = 1'];
        if (ctype_digit((string) ($f['fiscal_yr'] ?? ''))) {
            $where[] = 'i.fiscal_yr = ' . (int) $f['fiscal_yr'];
        }
        $type = strtoupper(trim((string) ($f['type'] ?? '')));
        if (in_array($type, self::TYPES, true)) {
            $where[] = 'i.type_key = ' . $this->str($type);
        }
        $agency = strtoupper(trim((string) ($f['agency'] ?? '')));
        if (isset(self::AGENCIES[$agency])) {
            $where[] = 'i.agency = ' . $this->str($agency);
        }
        $status = strtoupper(trim((string) ($f['status'] ?? '')));
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'i.remit_status = ' . $this->str($status);
        }
        $search = trim((string) ($f['search'] ?? ''));
        if ($search !== '') {
            $kw      = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
            $where[] = "(i.dv_no ILIKE {$kw} OR i.claimant_name ILIKE {$kw} OR i.atc_code ILIKE {$kw} OR i.type_name ILIKE {$kw} OR i.account_code ILIKE {$kw})";
        }

        return implode(' AND ', $where);
    }

    /** Totals over every line matching the filters: [summary, by_type]. Shared by the flat and the per-voucher list. */
    private function totals(string $items, string $whereSql): array
    {
        $summary = App\ExecuteRow(
            "SELECT COUNT(*) AS lines, COUNT(DISTINCT i.dv_id) AS vouchers,
                    COALESCE(SUM(i.amount), 0) AS total,
                    COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'READY'), 0)        AS ready,
                    COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'WITHHELD'), 0)     AS withheld,
                    COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'IN_DV'), 0)        AS in_dv,
                    COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'REMITTED'), 0)     AS remitted,
                    COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'NOT_REMITTED'), 0) AS not_remitted
               FROM ({$items}) i WHERE {$whereSql}",
            'DB'
        ) ?: [];
        $byType = App\ExecuteRows(
            "SELECT i.type_key, MIN(i.type_name) AS type_name, COUNT(*) AS lines, COALESCE(SUM(i.amount), 0) AS amount
               FROM ({$items}) i WHERE {$whereSql} GROUP BY i.type_key ORDER BY amount DESC",
            'DB'
        ) ?: [];

        return [$summary, $byType];
    }

    /**
     * Status of a voucher as a whole from its summed lines (see fetchByDv()): READY while anything is left to remit on a RELEASED voucher,
     * WITHHELD before release, IN_DV / REMITTED once everything remittable is in a Remittance DV, NOT_REMITTED when nothing is remittable.
     */
    private function voucherStatus(array $v): string
    {
        $ready    = (float) $v['ready'];
        $withheld = (float) $v['withheld'];
        $inDv     = (float) $v['in_dv'];
        $remitted = (float) $v['remitted'];
        if ($ready + $withheld + $inDv + $remitted <= 0) {
            return 'NOT_REMITTED';
        }

        return $ready > 0 ? 'READY' : ($withheld > 0 ? 'WITHHELD' : ($inDv > 0 ? 'IN_DV' : 'REMITTED'));
    }

    /**
     * The same lines grouped per voucher: one entry per voucher having at least one matching line (newest first, paginated BY VOUCHER),
     * each with the voucher's gross / net, the total and the ready / awaiting / in-DV / remitted / not-remitted parts of its matching lines,
     * a status for the voucher as a whole, and its matching lines under `items`. Totals (summary / by_type) are over every matching line.
     */
    public function fetchByDv(int $page = 1, int $limit = 15, array $filters = []): array
    {
        try {
            $items    = $this->itemsSql();
            $whereSql = $this->whereSql($filters);
            [$summary, $byType] = $this->totals($items, $whereSql);

            $limit = max(1, min(100, $limit));
            $page  = max(1, $page);
            $total = (int) ($summary['vouchers'] ?? 0);

            $vouchers = App\ExecuteRows(
                "SELECT i.dv_id, MIN(i.dv_no) AS dv_no, MIN(i.dv_date) AS dv_date, MIN(i.fiscal_yr) AS fiscal_yr, MIN(i.claimant_name) AS claimant_name,
                        MIN(i.dv_status) AS dv_status, MIN(i.dv_gross) AS dv_gross, MIN(i.dv_net) AS dv_net,
                        COUNT(*) AS lines, COALESCE(SUM(i.amount), 0) AS total,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'READY'), 0)        AS ready,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'WITHHELD'), 0)     AS withheld,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'IN_DV'), 0)        AS in_dv,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'REMITTED'), 0)     AS remitted,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'NOT_REMITTED'), 0) AS not_remitted
                   FROM ({$items}) i WHERE {$whereSql}
                  GROUP BY i.dv_id ORDER BY MIN(i.dv_date) DESC, MIN(i.dv_no) DESC, i.dv_id DESC
                  LIMIT {$limit} OFFSET " . (($page - 1) * $limit),
                'DB'
            ) ?: [];

            // the matching lines of just the vouchers on this page, in one query
            $lines = [];
            if ($vouchers) {
                $ids = implode(', ', array_map(fn ($v) => (int) $v['dv_id'], $vouchers));
                foreach (App\ExecuteRows("SELECT i.* FROM ({$items}) i WHERE {$whereSql} AND i.dv_id IN ({$ids}) ORDER BY i.dv_id, i.line_no", 'DB') ?: [] as $l) {
                    $lines[(int) $l['dv_id']][] = $l;
                }
            }
            foreach ($vouchers as &$v) {
                $v['status'] = $this->voucherStatus($v);
                $v['items']  = $lines[(int) $v['dv_id']] ?? [];
            }
            unset($v);

            return [
                'success'    => true,
                'data'       => $vouchers,
                'summary'    => $summary,
                'by_type'    => $byType,
                'agencies'   => self::AGENCIES,
                'pagination' => ['total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $limit))],
            ];
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchByDv ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the remittance list.'];
        }
    }

    public function fetchAlls(?int $page = 1, int $limit = 15, array $filters = []): array
    {
        try {
            $items    = $this->itemsSql();
            $whereSql = $this->whereSql($filters);
            $select   = "SELECT i.* FROM ({$items}) i WHERE {$whereSql} ORDER BY i.dv_date DESC, i.dv_no DESC, i.line_no";

            [$summary, $byType] = $this->totals($items, $whereSql);

            $out = ['success' => true, 'summary' => $summary, 'by_type' => $byType, 'agencies' => self::AGENCIES];
            if ($page === null) {
                $out['data'] = App\ExecuteRows($select, 'DB') ?: [];

                return $out;
            }

            $limit  = max(1, min(200, $limit));
            $page   = max(1, $page);
            $total  = (int) ($summary['lines'] ?? 0);
            $out['data']       = App\ExecuteRows($select . ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit), 'DB') ?: [];
            $out['pagination'] = ['total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $limit))];

            return $out;
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the remittance list.'];
        }
    }

    // ═══ Pay to BIR by month — the page's panel ═════════════════════════════════════════════════

    /**
     * One entry per (month, BIR tax) that has withholding: how many vouchers, the total and its parts — `ready` (released, in no Remittance DV
     * yet: what the "Remit — create DV" button would pay), `withheld` (voucher not released yet), `in_dv`, `remitted` — and the Remittance DVs
     * already made for it (`dvs`). The month is the voucher's DV date. Only the fiscal-year filter applies.
     */
    public function fetchMonths(array $filters = []): array
    {
        try {
            $keys  = implode(', ', array_map(fn ($k) => $this->str($k), array_keys(self::REMIT_TYPES)));
            $fy    = ctype_digit((string) ($filters['fiscal_yr'] ?? '')) ? ' AND i.fiscal_yr = ' . (int) $filters['fiscal_yr'] : '';
            $items = $this->itemsSql();

            $rows = App\ExecuteRows(
                "SELECT to_char(i.dv_date, 'YYYY-MM') AS period, i.type_key, COUNT(DISTINCT i.dv_id) AS vouchers, COALESCE(SUM(i.amount), 0) AS total,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'READY'), 0)    AS ready,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'WITHHELD'), 0) AS withheld,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'IN_DV'), 0)    AS in_dv,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'REMITTED'), 0) AS remitted,
                        COUNT(DISTINCT i.dv_id) FILTER (WHERE i.remit_status = 'READY')       AS ready_vouchers
                   FROM ({$items}) i WHERE i.type_key IN ({$keys}){$fy}
                  GROUP BY 1, 2 ORDER BY 1 DESC, 2",
                'DB'
            ) ?: [];

            $dvs = [];
            foreach (App\ExecuteRows(
                "SELECT DISTINCT to_char(i.dv_date, 'YYYY-MM') AS period, i.type_key, i.remit_dv_id, i.remit_dv_no, i.remit_dv_status
                   FROM ({$items}) i WHERE i.type_key IN ({$keys}) AND i.remit_dv_id IS NOT NULL{$fy}
                  ORDER BY i.remit_dv_no",
                'DB'
            ) ?: [] as $d) {
                $dvs[$d['period'] . '|' . $d['type_key']][] = ['dv_id' => (int) $d['remit_dv_id'], 'dv_no' => $d['remit_dv_no'], 'status' => $d['remit_dv_status']];
            }

            foreach ($rows as &$r) {
                $r['period_label'] = $this->periodLabel($r['period']);
                $r['type_name']    = self::REMIT_TYPES[$r['type_key']];
                $r['can_remit']    = (float) $r['ready'] > 0;
                $r['dvs']          = $dvs[$r['period'] . '|' . $r['type_key']] ?? [];
            }
            unset($r);

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchMonths ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the monthly remittances.'];
        }
    }

    // ═══ Remittance DV (DV module: Voucher?remit_period=YYYY-MM&remit_type={EWT|VAT|PENALTY_TAX}, DisbursementVoucherService hooks) ═════

    /**
     * False until "01 - table/54 - fims_dv_deduction_remittances.sql" has been run. Every voucher create / update / cancel / delete calls into
     * this class, so on a database without the table they must do nothing rather than fail inside the transaction.
     */
    private function linkTableReady(): bool
    {
        static $ready = null;

        return $ready ??= (int) App\ExecuteScalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'fims' AND table_name = 'dv_deduction_remittances'", 'DB') > 0;
    }

    /** [first day, first day of the next month] of 'YYYY-MM', or null when it is not a month. */
    private function periodBounds(string $period): ?array
    {
        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $m)) {
            return null;
        }
        $next = (int) $m[2] === 12 ? ((int) $m[1] + 1) . '-01' : $m[1] . '-' . str_pad((string) ((int) $m[2] + 1), 2, '0', STR_PAD_LEFT);

        return [$period . '-01', $next . '-01'];
    }

    private function periodLabel(string $period): string
    {
        return preg_match('/^(\d{4})-(\d{2})$/', $period, $m) ? (self::MONTHS[(int) $m[2]] ?? $m[2]) . ' ' . $m[1] : $period;
    }

    /**
     * AND-condition over itemsSql()'s alias i limiting the lines to one vendor (the voucher's claimant — the name the page lists); '' = no limit.
     * '—' is how a voucher without a claimant name is shown.
     */
    private function vendorCond(?string $vendor): string
    {
        $vendor = trim((string) $vendor);
        if ($vendor === '') {
            return '';
        }
        if ($vendor === '—') {
            return " AND btrim(COALESCE(i.claimant_name, '')) = ''";
        }

        return ' AND lower(btrim(i.claimant_name)) = lower(' . $this->str($vendor) . ')';
    }

    /** The vendors a month's open lines were withheld from — [{vendor, amount, vouchers}], biggest first — for the form's vendor select. */
    private function monthVendors(array $lines): array
    {
        $by = [];
        foreach ($lines as $l) {
            $name = trim((string) $l['claimant_name']) !== '' ? trim((string) $l['claimant_name']) : '—';
            $key  = mb_strtolower($name);
            $by[$key] ??= ['vendor' => $name, 'amount' => 0.0, 'dvs' => []];
            $by[$key]['amount'] += round((float) $l['amount'], 2);
            $by[$key]['dvs'][(int) $l['dv_id']] = true;
        }
        $out = array_map(fn ($v) => ['vendor' => $v['vendor'], 'amount' => round($v['amount'], 2), 'vouchers' => count($v['dvs'])], array_values($by));
        usort($out, fn ($a, $b) => $b['amount'] <=> $a['amount'] ?: strcasecmp($a['vendor'], $b['vendor']));

        return $out;
    }

    /**
     * The lines of one BIR tax of one month, oldest voucher first. $mode 'open' = vouchers RELEASED and in no Remittance DV yet (what a new DV
     * would pay); 'linked' = the lines Remittance DV $remitDvId already pays.
     */
    private function monthLines(string $period, string $type, string $mode, int $remitDvId = 0, ?string $vendor = null): array
    {
        $bounds = $this->periodBounds($period);
        if (!$bounds) {
            return [];
        }
        $cond = $mode === 'linked'
            ? "i.remit_dv_id = {$remitDvId}"
            : "i.dv_status = 'RELEASED' AND i.remit_dv_id IS NULL" . $this->vendorCond($vendor);

        return App\ExecuteRows(
            "SELECT i.* FROM ({$this->itemsSql()}) i
              WHERE i.type_key = " . $this->str($type) . " AND i.dv_date >= " . $this->str($bounds[0]) . " AND i.dv_date < " . $this->str($bounds[1]) . " AND {$cond}
              ORDER BY i.dv_date, i.dv_no, i.line_no",
            'DB'
        ) ?: [];
    }

    /**
     * What a set of lines adds up to: the total, the breakdown by rate (the paper form's "1% Withholding Tax … 15%" rows — for EWT the rates
     * in RATE_ROWS are always listed — each with the vendors it was withheld from and their amounts), the accounts to debit and the
     * voucher-by-voucher schedule (one entry per line).
     *
     * @return array{total: float, vouchers: int, breakdown: array, accounts: array, schedule: array}
     */
    private function summarize(array $lines, string $type): array
    {
        $total = 0.0;
        $byRate = [];
        $vendorsByRate = [];
        $accounts = [];
        $schedule = [];
        $vouchers = [];
        if ($type === 'EWT') {
            foreach (self::RATE_ROWS as $r) {
                $byRate[(string) $r] = 0.0;
            }
        }
        foreach ($lines as $l) {
            $amt = round((float) $l['amount'], 2);
            $total += $amt;
            $rate = $l['rate'] !== null && $l['rate'] !== '' ? (string) (float) $l['rate'] : '';
            $byRate[$rate] = ($byRate[$rate] ?? 0.0) + $amt;
            $vendor = trim((string) $l['claimant_name']) !== '' ? trim((string) $l['claimant_name']) : '—';
            $vendorsByRate[$rate][$vendor] = ($vendorsByRate[$rate][$vendor] ?? 0.0) + $amt; // who the rate's amount was withheld from
            $acct = trim((string) $l['account_code']) !== '' ? $l['account_code'] : self::DUE_ACCOUNT[$type];
            $accounts[$acct] = ($accounts[$acct] ?? 0.0) + $amt;
            $vouchers[(int) $l['dv_id']] = true;
            $schedule[] = [
                'dv_id' => (int) $l['dv_id'], 'dv_no' => $l['dv_no'], 'dv_date' => substr((string) $l['dv_date'], 0, 10), 'claimant_name' => $l['claimant_name'],
                'atc_code' => $l['atc_code'], 'rate' => $l['rate'] !== null && $l['rate'] !== '' ? (float) $l['rate'] : null,
                'base' => round((float) $l['base'], 2), 'amount' => $amt,
            ];
        }
        uksort($byRate, fn ($a, $b) => (float) $a <=> (float) $b);
        $breakdown = [];
        foreach ($byRate as $rate => $amt) {
            $vendors = [];
            $list = $vendorsByRate[$rate] ?? [];
            uksort($list, fn ($a, $b) => $list[$b] <=> $list[$a] ?: strcasecmp($a, $b)); // biggest first
            foreach ($list as $name => $vAmt) {
                $vendors[] = ['vendor' => (string) $name, 'amount' => round($vAmt, 2)];
            }
            $breakdown[] = ['rate' => $rate === '' ? null : (float) $rate, 'amount' => round($amt, 2), 'vendors' => $vendors];
        }

        return ['total' => round($total, 2), 'vouchers' => count($vouchers), 'breakdown' => $breakdown, 'accounts' => $accounts, 'schedule' => $schedule];
    }

    /** "2% Withholding Tax" (EWT) / "5% Withholding VAT" / "1% Penalty Tax" — a breakdown row's label. */
    private function rateLabel(?float $rate, string $type): string
    {
        $pct = $rate === null ? '' : rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '% ';

        return $pct . ($type === 'EWT' ? 'Withholding Tax' : self::REMIT_TYPES[$type]);
    }

    /**
     * Null when the Remittance DV $remitDvId (0 = a new one) may pay $type of month $period; otherwise the error to show. $gross (the DV form's
     * Amount), when given, must equal what the DV pays — for a new DV the month's open (RELEASED, not yet remitted) withholding; for an existing
     * one what it is already linked to.
     */
    public function validateLink(string $period, string $type, int $remitDvId = 0, ?float $gross = null, ?string $vendor = null): ?string
    {
        $type = strtoupper(trim($type));
        if (!isset(self::REMIT_TYPES[$type])) {
            return 'That deduction cannot be remitted through a Remittance DV.';
        }
        if (!$this->linkTableReady()) {
            App\LogError('RemittanceListService: fims.dv_deduction_remittances is missing — run "01 - table/54 - fims_dv_deduction_remittances.sql".');

            return 'Unable to create a Remittance DV right now. Please contact your system administrator.';
        }
        if (!$this->periodBounds($period)) {
            return 'A Remittance DV needs the month it pays (YYYY-MM).';
        }
        $label = self::REMIT_TYPES[$type];
        $lines = $remitDvId > 0 ? $this->monthLines($period, $type, 'linked', $remitDvId) : [];
        if (!$lines) {
            if (trim((string) $vendor) === '' && $this->monthLines($period, $type, 'open')) {
                return 'Select the vendor this Remittance DV pays the withheld tax of — one vendor per DV.';
            }
            $lines = $this->monthLines($period, $type, 'open', 0, $vendor);
        }
        if (!$lines) {
            return "There is no {$label}" . (trim((string) $vendor) !== '' ? ' of ' . trim((string) $vendor) : '') . ' left to remit for ' . $this->periodLabel($period) . ' — its vouchers must be RELEASED and not already in a Remittance DV.';
        }
        $amount = $this->summarize($lines, $type)['total'];
        if ($gross !== null && abs($gross - $amount) >= 0.005) {
            return "Amount must equal the {$label} withheld from the selected vendor for " . $this->periodLabel($period) . ' (₱' . number_format($amount, 2) . ').';
        }

        return null;
    }

    /**
     * What the new-DV form fills for a month's Remittance DV: claimant = BIR, the particulars ("Remittance of Expanded Withholding Taxes withheld
     * for the month of …" with the breakdown by rate), the amount, no deductions, and the entry — Dr the liability account(s) the withholding was
     * credited to (Due to BIR / Value Added Tax Payable), Cr Cash in Bank. No RFA: the vouchers already obligated it. `breakdown` and `schedule`
     * are what the form lists under the claimant (the rate rows and the voucher-by-voucher detail).
     */
    public function dvData(string $period, string $type, ?string $vendor = null): array
    {
        try {
            $type = strtoupper(trim($type));
            // the vendors the month's open withholding came from — the form lets Accounting pick one; the DV pays that vendor's lines only
            $vendors = [];
            if (isset(self::REMIT_TYPES[$type]) && $this->linkTableReady() && $this->periodBounds($period)) {
                $vendors = $this->monthVendors($this->monthLines($period, $type, 'open'));
            }
            $vendor = trim((string) $vendor);
            if ($vendor === '' && $vendors) {
                $vendor = $vendors[0]['vendor']; // opens on the vendor with the most withheld; the DV pays one vendor's vouchers
            }
            foreach ($vendors as $v) {
                if (mb_strtolower($v['vendor']) === mb_strtolower($vendor)) {
                    $vendor = $v['vendor']; // the name as the page lists it
                }
            }
            if ($err = $this->validateLink($period, $type, 0, null, $vendor)) {
                return ['success' => false, 'message' => $err];
            }
            $data = $this->remittanceView($period, $type, $this->monthLines($period, $type, 'open', 0, $vendor), $vendor);
            $data['vendors'] = $vendors;

            return ['success' => true, 'data' => $data];
        } catch (\Throwable $e) {
            App\LogError('RemittanceListService::dvData ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to read the vouchers for the Remittance DV.'];
        }
    }

    /** Account code => title for the accounting entry (blank titles when the chart can't be read — the entry still shows its codes). */
    private function accountTitles(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
        if (!$codes) {
            return [];
        }
        try {
            $in  = implode(',', array_map(fn ($c) => $this->str($c), $codes));
            $out = [];
            foreach (App\ExecuteRows("SELECT account_code, account_title FROM {$this->tCoa} WHERE account_code IN ({$in})", 'DB') ?: [] as $r) {
                $out[(string) $r['account_code']] = (string) $r['account_title'];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** The form-fill / view data of a month's Remittance DV for the given lines (see dvData()). */
    private function remittanceView(string $period, string $type, array $lines, ?string $vendor = null): array
    {
        $label = self::REMIT_TYPES[$type];
        $month = $this->periodLabel($period);
        $s     = $this->summarize($lines, $type);
        $vendor = trim((string) $vendor) !== '' ? trim((string) $vendor) : null;

        $breakdown = array_map(fn ($b) => $b + ['label' => $this->rateLabel($b['rate'], $type)], $s['breakdown']);
        $text = ["Remittance of " . self::REMIT_HEADING[$type] . ' withheld' . ($vendor !== null ? " from {$vendor}" : '') . " for the month of {$month}"];
        foreach ($breakdown as $b) {
            if ($b['amount'] > 0) {
                $text[] = $b['label'] . ' — ₱' . number_format($b['amount'], 2);
            }
        }
        $text[] = 'Total — ₱' . number_format($s['total'], 2) . " ({$s['vouchers']} voucher" . ($s['vouchers'] === 1 ? '' : 's') . ')';

        $entry   = [];
        $main    = null;
        $mainAmt = -1.0;
        foreach ($s['accounts'] as $acct => $amt) {
            $amt     = round($amt, 2);
            $entry[] = ['account_code' => (string) $acct, 'debit' => $amt, 'credit' => 0.0, 'description' => "{$label} withheld — {$month}"];
            if ($amt > $mainAmt) {
                [$main, $mainAmt] = [(string) $acct, $amt];
            }
        }
        $entry[] = ['account_code' => self::CASH_ACCOUNT, 'debit' => 0.0, 'credit' => $s['total'], 'description' => "Remittance to BIR — {$label}, {$month}"];

        $titles = $this->accountTitles(array_column($entry, 'account_code'));
        foreach ($entry as &$e) {
            $e['account_title'] = $titles[$e['account_code']] ?? '';
        }
        unset($e);

        return [
            'remit_period'  => $period,
            'remit_type'    => $type,
            'remit_vendor'  => $vendor,
            'period_label'  => $month,
            'type_name'     => $label,
            'agency_name'   => 'BIR',
            'claimant_name' => self::BIR_PAYEE,
            'particulars'   => implode("\n", $text),
            'account_code'  => $main,
            'gross_amount'  => $s['total'],
            'vouchers'      => $s['vouchers'],
            'lines'         => $entry,
            'breakdown'     => $breakdown,
            'schedule'      => $s['schedule'],
        ];
    }

    /**
     * Inside the DV's create()/update() transaction: Remittance DV $dvId pays $type of month $period (null = it pays none).
     * A new DV links every open voucher of that month and tax (one row each — fims.dv_deduction_remittances); a DV that is already linked keeps
     * exactly what it has, so editing it never picks up a voucher released since.
     */
    public function syncLink(int $dvId, ?string $period, ?string $type, int $userId = 0, ?string $vendor = null): void
    {
        if (!$this->linkTableReady()) {
            return;
        }
        $type = $type !== null ? strtoupper(trim($type)) : null;
        if (!$period || !$type) {
            App\Execute("DELETE FROM {$this->tLink} WHERE remit_dv_id = {$dvId}", 'DB');

            return;
        }
        if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tLink} WHERE remit_dv_id = {$dvId}", 'DB') > 0) {
            return;
        }
        $bounds = $this->periodBounds($period);
        if (!$bounds || !isset(self::REMIT_TYPES[$type])) {
            return;
        }
        App\Execute(
            "INSERT INTO {$this->tLink} (source_dv_id, type_key, remit_dv_id, amount, created_by)
             SELECT i.dv_id, " . $this->str($type) . ", {$dvId}, ROUND(SUM(i.amount)::numeric, 2), " . ($userId > 0 ? $userId : 'NULL') . "
               FROM ({$this->itemsSql()}) i
              WHERE i.type_key = " . $this->str($type) . " AND i.dv_date >= " . $this->str($bounds[0]) . " AND i.dv_date < " . $this->str($bounds[1]) . "
                AND i.dv_status = 'RELEASED' AND i.remit_dv_id IS NULL" . $this->vendorCond($vendor) . "
              GROUP BY i.dv_id",
            'DB'
        );
    }

    /** Remittance DV cancelled / deleted: what it paid is "Ready to remit" again (a new Remittance DV can be made). */
    public function releaseDv(int $dvId): void
    {
        if (!$this->linkTableReady()) {
            return;
        }
        App\Execute("DELETE FROM {$this->tLink} WHERE remit_dv_id = {$dvId}", 'DB');
    }

    /** DisbursementVoucherService::fetchById(): what this Remittance DV pays (month, tax, breakdown, voucher schedule), or null for any other DV. */
    public function forDv(int $dvId): ?array
    {
        if (!$this->linkTableReady()) {
            return null;
        }
        try {
            $row = App\ExecuteRow(
                "SELECT l.type_key, to_char(MIN(src.dv_date), 'YYYY-MM') AS period
                   FROM {$this->tLink} l JOIN {$this->tDv} src ON src.dv_id = l.source_dv_id
                  WHERE l.remit_dv_id = {$dvId} GROUP BY l.type_key LIMIT 1",
                'DB'
            );
            if (!$row) {
                return null;
            }

            $lines   = $this->monthLines($row['period'], $row['type_key'], 'linked', $dvId);
            $vendors = $this->monthVendors($lines);

            return $this->remittanceView($row['period'], $row['type_key'], $lines, count($vendors) === 1 ? $vendors[0]['vendor'] : null);
        } catch (\Throwable $e) {
            // never block viewing a DV over its remittance link
            App\LogError('RemittanceListService::forDv ' . $e->getMessage());

            return null;
        }
    }
}
