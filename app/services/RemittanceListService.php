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
 * Remittance DV — ONE per MONTH for ALL the BIR taxes (EWT · Withholding VAT · Penalty Tax together, every vendor), the way Accounting pays
 * BIR ("Remittance of Withholding Taxes withheld for the month of …"): every RELEASED voucher dated in that month whose withholding is not in a
 * Remittance DV yet goes into it. The page has a month filter, a summary of the month's remittance (fetchSummary(): per tax, and per rate under
 * it) and a single "Create DV" button; it opens the Voucher page's new-DV form filled by dvData() — claimant BIR (every one of these taxes
 * is posted to a BIR payable: Due to BIR / Value Added Tax Payable), the month's total, the breakdown by tax and rate (1% / 2% / 5% / 10% / 15% …)
 * in the particulars, the entry Dr each liability account / Cr Cash in Bank — and shows the vendor-by-vendor schedule under it. There is no
 * vendor or tax to pick: everything of the month that can be remitted is in the one DV.
 * The DV has NO FMD step (nothing to obligate — the vouchers already did): DisbursementVoucherService sends it from Draft straight to Accounting.
 * Saving the DV links the vouchers (fims.dv_deduction_remittances, one row per voucher + tax); DisbursementVoucherService calls
 * validateLink() / syncLink() then, and releaseDv() when the DV is cancelled or deleted. Same pattern as the payroll remittance DV
 * (DeductionRemittanceService). A voucher released after the DV was made goes into a follow-up DV for that month.
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

    // What the monthly Remittance DV pays — all of these together (the BIR taxes; Retention / Recoupment are not paid to an agency, Other has no known payee).
    public const REMIT_TYPES = ['EWT' => 'Withholding Tax (EWT)', 'VAT' => 'Withholding VAT', 'PENALTY_TAX' => 'Penalty Tax'];
    // "Remittance of … withheld for the month of …" — the DV's particulars heading per type
    private const REMIT_HEADING = ['EWT' => 'Expanded Withholding Taxes', 'VAT' => 'Withholding VAT', 'PENALTY_TAX' => 'Penalty Tax'];
    private const RATE_ROWS     = [1, 2, 5, 10, 15]; // the rate rows the paper EWT remittance lists even when empty
    private const BIR_PAYEE     = 'Bureau of Internal Revenue (BIR)';
    private const CASH_ACCOUNT  = '1-01-02-020'; // Cash in Bank-Local Currency, Current Account — what the Remittance DV credits (no payment entry follows: nothing routes through Accounts Payable)
    private const DUE_ACCOUNT   = ['EWT' => '2-02-01-010', 'VAT' => '2-02-01-120', 'PENALTY_TAX' => '2-02-01-010']; // used when a line carries no account
    private const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    // Who may create, edit and submit the monthly Remittance DV: Accounting Department staff (uac.users.rc_id 012) and the superadmin — the same rule as
    // the payroll remittance DV (DeductionRemittanceService::DV_RCS). It is not an Originating DV (no RFA, no vendor, pays every office's withholding at once).
    // Viewing the page and its lists is open to every level that has the page's permission.
    public const DV_RCS   = ['012'];
    public const DENY_MSG = 'Only Accounting Department staff can create, edit or submit a remittance DV.';

    /** True when the signed-in user may create / edit / submit the Remittance DV (Accounting Department, or a superadmin). */
    public function canCreateDv(): bool
    {
        $s = GlobalService::getUserRcScope();

        return $s['is_admin'] || in_array($s['rc_id'] ?? null, self::DV_RCS, true);
    }

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
        if ($bounds = $this->periodBounds(trim((string) ($f['period'] ?? '')))) {   // the month filter: the voucher's DV date, 'YYYY-MM'
            $where[] = 'i.dv_date >= ' . $this->str($bounds[0]) . ' AND i.dv_date < ' . $this->str($bounds[1]);
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

    // ═══ The month's remittance — the page's summary ════════════════════════════════════════════

    /** SQL list of the tax keys the Remittance DV pays: 'EWT', 'VAT', 'PENALTY_TAX'. */
    private function remitKeysSql(): string
    {
        return implode(', ', array_map(fn ($k) => $this->str($k), array_keys(self::REMIT_TYPES)));
    }

    /**
     * The months that have BIR tax withheld (newest first) for the month filter: how much tax was withheld in each (`tax_total`), how much of it is
     * `ready` to remit (released, in no Remittance DV yet) and how many vouchers. The month is the voucher's DV date. Only the fiscal-year filter applies.
     */
    public function fetchPeriods(array $filters = []): array
    {
        try {
            $fy   = ctype_digit((string) ($filters['fiscal_yr'] ?? '')) ? ' AND i.fiscal_yr = ' . (int) $filters['fiscal_yr'] : '';
            $keys = $this->remitKeysSql();
            $rows = App\ExecuteRows(
                "SELECT to_char(i.dv_date, 'YYYY-MM') AS period, COUNT(DISTINCT i.dv_id) AS vouchers,
                        COALESCE(SUM(i.amount), 0) AS tax_total,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'READY'), 0) AS ready
                   FROM ({$this->itemsSql()}) i WHERE i.type_key IN ({$keys}){$fy}
                  GROUP BY 1 ORDER BY 1 DESC",
                'DB'
            ) ?: [];
            foreach ($rows as &$r) {
                $r['period_label'] = $this->periodLabel((string) $r['period']);
                $r['vouchers']     = (int) $r['vouchers'];
                $r['tax_total']    = round((float) $r['tax_total'], 2);
                $r['ready']        = round((float) $r['ready'], 2);
            }
            unset($r);

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchPeriods ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the months.'];
        }
    }

    /**
     * The summary of the remittance for the page's month ('period' = 'YYYY-MM'; none = every month): per BIR tax how much was withheld and where it
     * stands (`ready` = released and in no Remittance DV yet: what "Create DV" pays; `withheld` = voucher not released yet; `in_dv`; `remitted`) and,
     * under each tax, its `rates` — the READY amount per rate (what the DV will pay). Also the totals, the number of vouchers still waiting for
     * release, whether anything of the month is already in a Remittance DV (`has_remitted`) and those DVs (`dvs`). `can_remit` = a month is picked
     * and something is ready. Only the fiscal-year and month filters apply.
     */
    public function fetchSummary(array $filters = []): array
    {
        try {
            $period = trim((string) ($filters['period'] ?? ''));
            $bounds = $this->periodBounds($period);
            $where  = "i.type_key IN ({$this->remitKeysSql()})";
            if (ctype_digit((string) ($filters['fiscal_yr'] ?? ''))) {
                $where .= ' AND i.fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if ($bounds) {
                $where .= ' AND i.dv_date >= ' . $this->str($bounds[0]) . ' AND i.dv_date < ' . $this->str($bounds[1]);
            } else {
                $period = '';
            }
            $items = $this->itemsSql();

            $parts = "COALESCE(SUM(i.amount), 0) AS total,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'READY'), 0)    AS ready,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'WITHHELD'), 0) AS withheld,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'IN_DV'), 0)    AS in_dv,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'REMITTED'), 0) AS remitted";

            $rateRows = App\ExecuteRows(
                "SELECT i.type_key, i.rate, COUNT(DISTINCT i.dv_id) AS vouchers, {$parts}
                   FROM ({$items}) i WHERE {$where} GROUP BY i.type_key, i.rate ORDER BY i.type_key, i.rate NULLS FIRST",
                'DB'
            ) ?: [];
            $all = App\ExecuteRow(
                "SELECT COUNT(DISTINCT i.dv_id) AS vouchers, COUNT(DISTINCT i.dv_id) FILTER (WHERE i.remit_status = 'READY') AS ready_vouchers,
                        COUNT(DISTINCT i.dv_id) FILTER (WHERE i.remit_status = 'WITHHELD') AS withheld_vouchers, {$parts}
                   FROM ({$items}) i WHERE {$where}",
                'DB'
            ) ?: [];

            $taxes = [];
            foreach (self::REMIT_TYPES as $key => $name) {
                $taxes[$key] = ['type_key' => $key, 'type_name' => $name, 'total' => 0.0, 'ready' => 0.0, 'withheld' => 0.0, 'in_dv' => 0.0, 'remitted' => 0.0, 'rates' => []];
            }
            foreach ($rateRows as $r) {
                $t = &$taxes[$r['type_key']];
                foreach (['total', 'ready', 'withheld', 'in_dv', 'remitted'] as $f) {
                    $t[$f] += (float) $r[$f];
                }
                if ((float) $r['ready'] > 0) {   // the rates of what the DV will pay
                    $t['rates'][] = ['rate' => $r['rate'] !== null && $r['rate'] !== '' ? (float) $r['rate'] : null, 'amount' => round((float) $r['ready'], 2)];
                }
                unset($t);
            }
            foreach ($taxes as &$t) {
                foreach (['total', 'ready', 'withheld', 'in_dv', 'remitted'] as $f) {
                    $t[$f] = round($t[$f], 2);
                }
            }
            unset($t);

            $dvs = [];
            foreach (App\ExecuteRows(
                "SELECT i.remit_dv_id, MAX(i.remit_dv_no) AS dv_no, MAX(i.remit_dv_status) AS status, COALESCE(SUM(i.amount), 0) AS amount
                   FROM ({$items}) i WHERE {$where} AND i.remit_dv_id IS NOT NULL GROUP BY i.remit_dv_id ORDER BY MAX(i.remit_dv_no)",
                'DB'
            ) ?: [] as $d) {
                $dvs[] = ['dv_id' => (int) $d['remit_dv_id'], 'dv_no' => $d['dv_no'], 'status' => $d['status'], 'amount' => round((float) $d['amount'], 2)];
            }

            $ready    = round((float) ($all['ready'] ?? 0), 2);
            $inDv     = round((float) ($all['in_dv'] ?? 0), 2);
            $remitted = round((float) ($all['remitted'] ?? 0), 2);

            return ['success' => true, 'data' => [
                'period'            => $period,
                'period_label'      => $period !== '' ? $this->periodLabel($period) : 'All months',
                'vouchers'          => (int) ($all['vouchers'] ?? 0),
                'ready_vouchers'    => (int) ($all['ready_vouchers'] ?? 0),
                'withheld_vouchers' => (int) ($all['withheld_vouchers'] ?? 0),
                'total'             => round((float) ($all['total'] ?? 0), 2),
                'ready'             => $ready,
                'withheld'          => round((float) ($all['withheld'] ?? 0), 2),
                'in_dv'             => $inDv,
                'remitted'          => $remitted,
                'has_remitted'      => ($inDv + $remitted) > 0,
                'taxes'             => array_values($taxes),
                'dvs'               => $dvs,
                'can_remit'         => $period !== '' && $ready > 0,   // something is ready to be paid
                'can_create'        => $this->canCreateDv(),            // and the signed-in user may create the DV (Accounting only)
            ]];
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchSummary ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the remittance summary.'];
        }
    }

    /**
     * The "Summary of Tax Deductions" of ONE month ('period' = 'YYYY-MM', the voucher's DV date) — everything behind the month's figures:
     *   totals     what the month's BIR taxes add up to, split into remitted (already in a Remittance DV, in process or paid) / ready (what the next
     *              Create DV pays) / withheld (voucher not released yet), each with its number of vouchers;
     *   by_tax     per tax (EWT · VAT · Penalty Tax): vouchers, tax base, tax withheld and the split, with its `rates` — the same figures per ATC code
     *              (with its description) and rate, the way BIR's withholding returns / alphalist are organized (a deduction line has no ATC code);
     *   by_vendor  per vendor (payee): TIN on record (`missing_tin` when a creditable ATC line needs one for the 2307 / alphalist and there is none),
     *              vouchers, each tax, the total and the split — the same people the BIR 2307s are issued to;
     *   dvs        the Remittance DVs already made for the month (number, status, vouchers, amount).
     * Same figures as the page's own summary and lists, just broken down.
     */
    public function fetchMonthDetail(string $period): array
    {
        try {
            $period = trim($period);
            $bounds = $this->periodBounds($period);
            if (!$bounds) {
                return ['success' => false, 'message' => 'Choose a month.'];
            }
            $where = "i.type_key IN ({$this->remitKeysSql()}) AND i.dv_date >= " . $this->str($bounds[0]) . ' AND i.dv_date < ' . $this->str($bounds[1]);
            $items = $this->itemsSql();
            $parts = "COALESCE(SUM(i.amount), 0) AS total,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status IN ('IN_DV', 'REMITTED')), 0) AS remitted,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'READY'), 0)    AS ready,
                      COALESCE(SUM(i.amount) FILTER (WHERE i.remit_status = 'WITHHELD'), 0) AS withheld";
            $order = "CASE i.type_key WHEN 'EWT' THEN 1 WHEN 'VAT' THEN 2 ELSE 3 END";

            $all = App\ExecuteRow(
                "SELECT COUNT(DISTINCT i.dv_id) AS vouchers,
                        COUNT(DISTINCT i.dv_id) FILTER (WHERE i.remit_status IN ('IN_DV', 'REMITTED')) AS remitted_vouchers,
                        COUNT(DISTINCT i.dv_id) FILTER (WHERE i.remit_status = 'READY')    AS ready_vouchers,
                        COUNT(DISTINCT i.dv_id) FILTER (WHERE i.remit_status = 'WITHHELD') AS withheld_vouchers, {$parts}
                   FROM ({$items}) i WHERE {$where}",
                'DB'
            ) ?: [];
            $typeRows = App\ExecuteRows(
                "SELECT i.type_key, COUNT(DISTINCT i.dv_id) AS vouchers, COALESCE(SUM(i.base), 0) AS base, {$parts}
                   FROM ({$items}) i WHERE {$where} GROUP BY i.type_key ORDER BY {$order}",
                'DB'
            ) ?: [];
            // each tax by ATC code (the way BIR's withholding returns and the quarterly alphalist are organized) — and by rate; a line without an ATC
            // code (the VAT and Penalty Tax deductions) has no code, so it is listed by rate alone
            $rateRows = App\ExecuteRows(
                "SELECT i.type_key, i.atc_code, MAX(ac.description) AS atc_description, i.rate, COUNT(DISTINCT i.dv_id) AS vouchers, COALESCE(SUM(i.base), 0) AS base, {$parts}
                   FROM ({$items}) i LEFT JOIN {$this->tAtc} ac ON ac.atc_code = i.atc_code
                  WHERE {$where} GROUP BY i.type_key, i.atc_code, i.rate ORDER BY {$order}, i.atc_code NULLS LAST, i.rate NULLS FIRST",
                'DB'
            ) ?: [];
            // each vendor (payee) with the TIN on record (the vendor's own, else the one typed on the voucher — the same rule as the BIR 2307) and whether
            // it needs one: a payee with a creditable ATC line gets a 2307 and goes on the alphalist, both of which require the payee's TIN
            $vendorRows = App\ExecuteRows(
                "SELECT COALESCE(NULLIF(btrim(MIN(i.claimant_name)), ''), '—') AS vendor, COUNT(DISTINCT i.dv_id) AS vouchers,
                        MAX(COALESCE(NULLIF(btrim(vx.tin), ''), NULLIF(btrim(dvx.employee_no_tin), ''))) AS tin,
                        COALESCE(BOOL_OR(i.atc_code IS NOT NULL AND i.atc_code NOT ILIKE 'WV%'), FALSE) AS needs_tin,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.type_key = 'EWT'), 0)         AS ewt,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.type_key = 'VAT'), 0)         AS vat,
                        COALESCE(SUM(i.amount) FILTER (WHERE i.type_key = 'PENALTY_TAX'), 0) AS penalty_tax, {$parts}
                   FROM ({$items}) i
                   JOIN {$this->tDv} dvx ON dvx.dv_id = i.dv_id
                   LEFT JOIN fims.vendors vx ON vx.vendor_id = dvx.vendor_id
                  WHERE {$where}
                  GROUP BY lower(btrim(COALESCE(i.claimant_name, ''))) ORDER BY SUM(i.amount) DESC, 1",
                'DB'
            ) ?: [];
            $dvRows = App\ExecuteRows(
                "SELECT i.remit_dv_id, MAX(i.remit_dv_no) AS dv_no, MAX(i.remit_dv_status) AS status, COUNT(DISTINCT i.dv_id) AS vouchers, COALESCE(SUM(i.amount), 0) AS amount
                   FROM ({$items}) i WHERE {$where} AND i.remit_dv_id IS NOT NULL GROUP BY i.remit_dv_id ORDER BY MAX(i.remit_dv_no)",
                'DB'
            ) ?: [];

            $r2   = fn ($v) => round((float) $v, 2);
            $fig  = fn (array $r) => ['vouchers' => (int) $r['vouchers'], 'total' => $r2($r['total']), 'remitted' => $r2($r['remitted']), 'ready' => $r2($r['ready']), 'withheld' => $r2($r['withheld'])];
            $byTax = [];
            foreach ($typeRows as $r) {
                $byTax[$r['type_key']] = ['type_key' => $r['type_key'], 'type_name' => self::REMIT_TYPES[$r['type_key']], 'base' => $r2($r['base']), 'rates' => []] + $fig($r);
            }
            foreach ($rateRows as $r) {
                $byTax[$r['type_key']]['rates'][] = [
                    'atc_code' => $r['atc_code'] ?: null, 'atc_description' => $r['atc_description'] ?: null,
                    'rate'     => $r['rate'] !== null && $r['rate'] !== '' ? (float) $r['rate'] : null, 'base' => $r2($r['base']),
                ] + $fig($r);
            }

            return ['success' => true, 'data' => [
                'period'       => $period,
                'period_label' => $this->periodLabel($period),
                'totals'       => $fig($all) + ['remitted_vouchers' => (int) ($all['remitted_vouchers'] ?? 0), 'ready_vouchers' => (int) ($all['ready_vouchers'] ?? 0), 'withheld_vouchers' => (int) ($all['withheld_vouchers'] ?? 0)],
                'by_tax'       => array_values($byTax),
                'by_vendor'    => array_map(function ($r) use ($r2, $fig) {
                    $tin = trim((string) $r['tin']);

                    return ['vendor' => $r['vendor'], 'tin' => $tin !== '' ? $tin : null, 'missing_tin' => $tin === '' && in_array($r['needs_tin'], [true, 't', '1', 1], true),
                            'taxes' => ['EWT' => $r2($r['ewt']), 'VAT' => $r2($r['vat']), 'PENALTY_TAX' => $r2($r['penalty_tax'])]] + $fig($r);
                }, $vendorRows),
                'dvs'          => array_map(fn ($r) => ['dv_id' => (int) $r['remit_dv_id'], 'dv_no' => $r['dv_no'], 'status' => $r['status'], 'vouchers' => (int) $r['vouchers'], 'amount' => $r2($r['amount'])], $dvRows),
            ]];
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchMonthDetail ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the summary of tax deductions.'];
        }
    }

    /**
     * The tax lines (EWT · Withholding VAT · Penalty Tax, every vendor) of the page's month for one of its two tabs, grouped by tax then oldest voucher first:
     * scope 'OPEN' = what the next Create DV will pay (voucher RELEASED, in no Remittance DV yet); 'REMITTED' = what is already in a Remittance DV
     * (still in process or paid) — each line carries `remit_dv_id` / `remit_dv_no` / `remit_dv_status`. Only the fiscal-year and month filters apply.
     */
    public function fetchTaxLines(array $filters = []): array
    {
        try {
            $where = $this->whereSql(['fiscal_yr' => $filters['fiscal_yr'] ?? '', 'period' => $filters['period'] ?? ''])
                . " AND i.type_key IN ({$this->remitKeysSql()})"
                . (strtoupper(trim((string) ($filters['scope'] ?? ''))) === 'REMITTED' ? " AND i.remit_status IN ('IN_DV', 'REMITTED')" : " AND i.remit_status = 'READY'");
            $rows = App\ExecuteRows(
                "SELECT i.* FROM ({$this->itemsSql()}) i WHERE {$where}
                  ORDER BY CASE i.type_key WHEN 'EWT' THEN 1 WHEN 'VAT' THEN 2 ELSE 3 END, i.remit_dv_no NULLS LAST, i.dv_date, i.dv_no, i.line_no",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('RemittanceListService::fetchTaxLines ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the taxes.'];
        }
    }

    // ═══ Remittance DV (DV module: Voucher?remit_period=YYYY-MM, DisbursementVoucherService hooks) ═════

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
     * The BIR-tax lines (EWT · Withholding VAT · Penalty Tax, every vendor) of one month, oldest voucher first. $mode 'open' = vouchers RELEASED
     * and in no Remittance DV yet (what a new DV would pay); 'linked' = the lines Remittance DV $remitDvId already pays.
     */
    private function monthLines(string $period, string $mode, int $remitDvId = 0): array
    {
        $bounds = $this->periodBounds($period);
        if (!$bounds) {
            return [];
        }
        $cond = $mode === 'linked'
            ? "i.remit_dv_id = {$remitDvId}"
            : "i.dv_status = 'RELEASED' AND i.remit_dv_id IS NULL";

        return App\ExecuteRows(
            "SELECT i.* FROM ({$this->itemsSql()}) i
              WHERE i.type_key IN ({$this->remitKeysSql()}) AND i.dv_date >= " . $this->str($bounds[0]) . ' AND i.dv_date < ' . $this->str($bounds[1]) . " AND {$cond}
              ORDER BY i.dv_date, i.dv_no, i.line_no",
            'DB'
        ) ?: [];
    }

    /** "Withholding Tax" (EWT) / "Withholding VAT" / "Penalty Tax" — a line's tax as the DV words it. */
    private function taxName(string $type): string
    {
        return $type === 'EWT' ? 'Withholding Tax' : (self::REMIT_TYPES[$type] ?? $type);
    }

    /** "2% Withholding Tax" (EWT) / "5% Withholding VAT" / "Penalty Tax" — a breakdown row's label. */
    private function rateLabel(?float $rate, string $type): string
    {
        $pct = $rate === null ? '' : rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '% ';

        return $pct . $this->taxName($type);
    }

    /** ['A', 'B', 'C'] → "A, B and C" */
    private function joinList(array $items): string
    {
        $items = array_values($items);
        $last  = array_pop($items);

        return $items ? implode(', ', $items) . ' and ' . $last : (string) $last;
    }

    /**
     * What a set of lines adds up to: the total, the breakdown by tax and rate (the paper form's "1% Withholding Tax … 15%" rows — for EWT the
     * rates in RATE_ROWS are always listed — each with the vendors it was withheld from and their amounts), the per-tax totals, the accounts to
     * debit and the voucher-by-voucher schedule (one entry per line).
     *
     * @return array{total: float, vouchers: int, breakdown: array, taxes: array, accounts: array, schedule: array}
     */
    private function summarize(array $lines): array
    {
        $total    = 0.0;
        $byRate   = array_fill_keys(array_keys(self::REMIT_TYPES), []);   // type => rate => ['amount' => float, 'vendors' => [name => float]]
        $accounts = [];
        $schedule = [];
        $vouchers = [];
        foreach (self::RATE_ROWS as $r) {
            $byRate['EWT'][(string) $r] = ['amount' => 0.0, 'vendors' => []];
        }
        foreach ($lines as $l) {
            $type   = (string) $l['type_key'];
            $amt    = round((float) $l['amount'], 2);
            $rate   = $l['rate'] !== null && $l['rate'] !== '' ? (string) (float) $l['rate'] : '';
            $vendor = trim((string) $l['claimant_name']) !== '' ? trim((string) $l['claimant_name']) : '—';
            $total += $amt;
            $byRate[$type][$rate] ??= ['amount' => 0.0, 'vendors' => []];
            $byRate[$type][$rate]['amount'] += $amt;
            $byRate[$type][$rate]['vendors'][$vendor] = ($byRate[$type][$rate]['vendors'][$vendor] ?? 0.0) + $amt;   // who the rate's amount was withheld from
            $acct = trim((string) $l['account_code']) !== '' ? $l['account_code'] : self::DUE_ACCOUNT[$type];
            $accounts[$acct] = ($accounts[$acct] ?? 0.0) + $amt;
            $vouchers[(int) $l['dv_id']] = true;
            $schedule[] = [
                'dv_id' => (int) $l['dv_id'], 'dv_no' => $l['dv_no'], 'dv_date' => substr((string) $l['dv_date'], 0, 10), 'claimant_name' => $l['claimant_name'],
                'type_key' => $type, 'type_name' => $this->taxName($type),
                'atc_code' => $l['atc_code'], 'rate' => $l['rate'] !== null && $l['rate'] !== '' ? (float) $l['rate'] : null,
                'base' => round((float) $l['base'], 2), 'amount' => $amt,
            ];
        }

        $breakdown = [];
        $taxes     = [];
        foreach ($byRate as $type => $rates) {
            uksort($rates, fn ($a, $b) => (float) $a <=> (float) $b);
            $sum = 0.0;
            foreach ($rates as $rate => $row) {
                $list = $row['vendors'];
                uksort($list, fn ($a, $b) => $list[$b] <=> $list[$a] ?: strcasecmp($a, $b)); // biggest first
                $vendors = [];
                foreach ($list as $name => $vAmt) {
                    $vendors[] = ['vendor' => (string) $name, 'amount' => round($vAmt, 2)];
                }
                $rateVal     = $rate === '' ? null : (float) $rate;
                $breakdown[] = ['type_key' => $type, 'type_name' => $this->taxName($type), 'rate' => $rateVal, 'amount' => round($row['amount'], 2),
                                'vendors' => $vendors, 'label' => $this->rateLabel($rateVal, $type)];
                $sum += $row['amount'];
            }
            if ($sum > 0) {
                $taxes[] = ['type_key' => $type, 'type_name' => self::REMIT_TYPES[$type], 'amount' => round($sum, 2)];
            }
        }

        return ['total' => round($total, 2), 'vouchers' => count($vouchers), 'breakdown' => $breakdown, 'taxes' => $taxes, 'accounts' => $accounts, 'schedule' => $schedule];
    }

    /**
     * Null when the Remittance DV $remitDvId (0 = a new one) may pay month $period; otherwise the error to show. $gross (the DV form's Amount),
     * when given, must equal what the DV pays — for a new DV the month's open (RELEASED, not yet remitted) withholding of every tax and vendor;
     * for an existing one what it is already linked to.
     */
    public function validateLink(string $period, int $remitDvId = 0, ?float $gross = null): ?string
    {
        if (!$this->linkTableReady()) {
            App\LogError('RemittanceListService: fims.dv_deduction_remittances is missing — run "01 - table/54 - fims_dv_deduction_remittances.sql".');

            return 'Unable to create a Remittance DV right now. Please contact your system administrator.';
        }
        if (!$this->periodBounds($period)) {
            return 'A Remittance DV needs the month it pays (YYYY-MM).';
        }
        $lines = $remitDvId > 0 ? $this->monthLines($period, 'linked', $remitDvId) : [];
        if (!$lines) {
            $lines = $this->monthLines($period, 'open');
        }
        if (!$lines) {
            return 'There is no withheld tax left to remit for ' . $this->periodLabel($period) . ' — its vouchers must be RELEASED and not already in a Remittance DV.';
        }
        $amount = $this->summarize($lines)['total'];
        if ($gross !== null && abs($gross - $amount) >= 0.005) {
            return 'Amount must equal the taxes withheld for ' . $this->periodLabel($period) . ' (₱' . number_format($amount, 2) . ').';
        }

        return null;
    }

    /**
     * What the new-DV form fills for a month's Remittance DV: claimant = BIR, the particulars ("Remittance of Withholding Taxes withheld for the
     * month of …" with the breakdown by tax and rate), the amount, no deductions, and the entry — Dr the liability account(s) the withholding was
     * credited to (Due to BIR / Value Added Tax Payable), Cr Cash in Bank. No RFA: the vouchers already obligated it. `breakdown` and `schedule`
     * are what the form lists under the claimant (the rate rows and the voucher-by-voucher detail).
     */
    public function dvData(string $period): array
    {
        try {
            if (!$this->canCreateDv()) {
                return ['success' => false, 'message' => self::DENY_MSG, 'httpCode' => 403];
            }
            if ($err = $this->validateLink($period)) {
                return ['success' => false, 'message' => $err];
            }

            return ['success' => true, 'data' => $this->remittanceView($period, $this->monthLines($period, 'open'))];
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
    private function remittanceView(string $period, array $lines): array
    {
        $month = $this->periodLabel($period);
        $s     = $this->summarize($lines);

        $heading = $this->joinList(array_map(fn ($t) => self::REMIT_HEADING[$t['type_key']], $s['taxes']));
        $text    = ['Remittance of ' . ($heading !== '' ? $heading : 'Withholding Taxes') . " withheld for the month of {$month}"];
        foreach ($s['breakdown'] as $b) {
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
            $entry[] = ['account_code' => (string) $acct, 'debit' => $amt, 'credit' => 0.0, 'description' => "Taxes withheld — {$month}"];
            if ($amt > $mainAmt) {
                [$main, $mainAmt] = [(string) $acct, $amt];
            }
        }
        $entry[] = ['account_code' => self::CASH_ACCOUNT, 'debit' => 0.0, 'credit' => $s['total'], 'description' => "Remittance to BIR — taxes withheld, {$month}"];

        $titles = $this->accountTitles(array_column($entry, 'account_code'));
        foreach ($entry as &$e) {
            $e['account_title'] = $titles[$e['account_code']] ?? '';
        }
        unset($e);

        return [
            'remit_period'  => $period,
            'period_label'  => $month,
            'type_name'     => 'Withheld taxes',
            'agency_name'   => 'BIR',
            'claimant_name' => self::BIR_PAYEE,
            'particulars'   => implode("\n", $text),
            'account_code'  => $main,
            'gross_amount'  => $s['total'],
            'vouchers'      => $s['vouchers'],
            'lines'         => $entry,
            'breakdown'     => $s['breakdown'],
            'taxes'         => $s['taxes'],
            'schedule'      => $s['schedule'],
        ];
    }

    /**
     * Inside the DV's create()/update() transaction: Remittance DV $dvId pays month $period (null = it pays none).
     * A new DV links every open voucher of that month for every tax (one row per voucher and tax — fims.dv_deduction_remittances); a DV that is
     * already linked keeps exactly what it has, so editing it never picks up a voucher released since.
     */
    public function syncLink(int $dvId, ?string $period, int $userId = 0): void
    {
        if (!$this->linkTableReady()) {
            return;
        }
        if (!$period) {
            App\Execute("DELETE FROM {$this->tLink} WHERE remit_dv_id = {$dvId}", 'DB');

            return;
        }
        if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tLink} WHERE remit_dv_id = {$dvId}", 'DB') > 0) {
            return;
        }
        $bounds = $this->periodBounds($period);
        if (!$bounds) {
            return;
        }
        App\Execute(
            "INSERT INTO {$this->tLink} (source_dv_id, type_key, remit_dv_id, amount, created_by)
             SELECT i.dv_id, i.type_key, {$dvId}, ROUND(SUM(i.amount)::numeric, 2), " . ($userId > 0 ? $userId : 'NULL') . "
               FROM ({$this->itemsSql()}) i
              WHERE i.type_key IN ({$this->remitKeysSql()}) AND i.dv_date >= " . $this->str($bounds[0]) . ' AND i.dv_date < ' . $this->str($bounds[1]) . "
                AND i.dv_status = 'RELEASED' AND i.remit_dv_id IS NULL
              GROUP BY i.dv_id, i.type_key",
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

    /** True when DV $dvId is a Remittance DV from this page — DisbursementVoucherService skips the FMD step for it (it has nothing to obligate). */
    public function isRemittanceDv(int $dvId): bool
    {
        return $dvId > 0 && $this->linkTableReady()
            && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tLink} WHERE remit_dv_id = {$dvId}", 'DB') > 0;
    }

    /** DisbursementVoucherService::fetchById(): what this Remittance DV pays (month, breakdown, voucher schedule), or null for any other DV. */
    public function forDv(int $dvId): ?array
    {
        if (!$this->linkTableReady()) {
            return null;
        }
        try {
            $period = App\ExecuteScalar(
                "SELECT to_char(MIN(src.dv_date), 'YYYY-MM')
                   FROM {$this->tLink} l JOIN {$this->tDv} src ON src.dv_id = l.source_dv_id
                  WHERE l.remit_dv_id = {$dvId}",
                'DB'
            );
            if (!$period) {
                return null;
            }

            return $this->remittanceView((string) $period, $this->monthLines((string) $period, 'linked', $dvId));
        } catch (\Throwable $e) {
            // never block viewing a DV over its remittance link
            App\LogError('RemittanceListService::forDv ' . $e->getMessage());

            return null;
        }
    }
}
