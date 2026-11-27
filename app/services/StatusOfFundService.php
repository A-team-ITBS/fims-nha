<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Status of Funds — read-only, backs app/pages/status-of-fund-page.php (sidebar Registry → Status of Funds).
 *
 * There is no fund-source master table in FIMS, so a "fund source" here is one of three groups of fims.fund_tracking records
 * (the same records the SARO / NCA Tracking, Trust Funds and Supplemental Funds pages keep):
 *
 *   NG     NG Subsidy          SARO + NCA records whose Nature of Fund is not SUPPLEMENTAL
 *   TRUST  Trust Funds         TRUST FUND records and the OR (Official Receipt) records under them
 *   SUPP   Supplemental Funds  SARO + NCA records whose Nature of Fund is SUPPLEMENTAL
 *
 * An NCA has no Nature of Fund of its own on the form, so it follows its SARO (parent_id); an NCA with no SARO falls back to its own.
 * A record of any other tracking type is not part of any fund source.
 *
 * The figures of one fund source (every one is the sum of the records in the fiscal year asked for, by each record's own fiscal_year):
 *   saro_released   the SAROs' amounts (NG / SUPP)
 *   nca_released    the NCAs' amounts (NG / SUPP)
 *   receipts        the ORs' amounts (TRUST) — what has actually come in under the trust funds
 *   available       "Funds available": the NCAs released (NG / SUPP) or the receipts (TRUST)
 *   disbursed       what the RELEASED disbursement vouchers have paid — each voucher counted ONCE, at its gross amount, by the first link it has:
 *                     1. a SARO: a SARO points at the one voucher it funds (fims.fund_tracking.dv_id, "03 - alter_table/61") — NG / SUPP, under that SARO's
 *                        program;
 *                     2. an RFA / LAA: the voucher is connected to it (fims.budget_allocation.dv_id) and the RFA / LAA drew from budget lines — the voucher's
 *                        gross is spread over those lines in proportion to what each drew (the way the RFA / LAA Monitoring page does), and each line says where
 *                        its share goes: a line a supplemental request created (budget_line_items.supplemental_revision_id) → SUPP; else its Type of Fund —
 *                        NG SUPPORT … → NG, TRUST FUND → TRUST; any other (CORPORATE RECEIPT, or none) is not a fund source on this page. The share is under the
 *                        budget line's own program and the fiscal year of its budget request.
 *                   A voucher with neither link is counted nowhere — `vouchers` (see reconciliation()) says how much, so the page can tell the user why.
 *                   Trust Funds have no SARO, so theirs is null (unknown) until an RFA / LAA of the Trust Fund type of fund has a released voucher.
 *   balance         available - disbursed
 *   utilization     disbursed / available, in percent (null when there is nothing available or nothing known)
 *
 * NG / SUPP also come split per Program (the SARO's sub_program → fims.sub_program → fims.program; an NCA goes under its SARO's
 * program); a SARO with no sub-program is under "No program". TRUST also comes per trust fund, with the ORs under it.
 */
class StatusOfFundService
{
    public const SOURCES = ['NG' => 'NG Subsidy', 'TRUST' => 'Trust Funds', 'SUPP' => 'Supplemental Funds'];

    private string $tableFt = 'fims.fund_tracking';
    private string $tableType = 'fims.fund_tracking_type';
    private string $tableNature = 'fims.nature_of_fund';
    private string $tableSubProgram = 'fims.sub_program';
    private string $tableProgram = 'fims.program';
    private string $tableDv = 'fims.disbursement_vouchers';
    private string $tableStatus = 'fims.settings_status';
    private string $tableEarmark = 'fims.budget_line_item_earmark';
    private string $tableAllocation = 'fims.budget_allocation';
    private string $tableLines = 'fims.budget_line_items';
    private string $tableBudgetReq = 'fims.budget_req';
    private string $tableBudgetReqProject = 'fims.budget_req_project';
    private string $tableBudgetReqProgram = 'fims.budget_req_program';
    private string $tableTypeOfFund = 'fims.type_of_fund';

    private static ?bool $hasDvLink = null;
    private static ?bool $hasSuppTag = null;

    /** Whether fund_tracking.dv_id exists ("03 - alter_table/61"); without it nothing is known about disbursements (they come back null). */
    private function hasDvLink(): bool
    {
        if (self::$hasDvLink === null) {
            self::$hasDvLink = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'fims' AND table_name = 'fund_tracking' AND column_name = 'dv_id'",
                'DB'
            ) > 0;
        }

        return self::$hasDvLink;
    }

    /** Whether budget_line_items.supplemental_revision_id exists ("03 - alter_table/63"): the tag of a line a supplemental request created. */
    private function hasSuppTag(): bool
    {
        if (self::$hasSuppTag === null) {
            self::$hasSuppTag = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'supplemental_revision_id'",
                'DB'
            ) > 0;
        }

        return self::$hasSuppTag;
    }

    /** "AND NOT EXISTS (a live SARO points at this voucher)" — a voucher with a SARO is counted there, not again through its RFA / LAA. '' without fund_tracking.dv_id. */
    private function notSaroLinkedSql(): string
    {
        return $this->hasDvLink() ? "AND NOT EXISTS (SELECT 1 FROM {$this->tableFt} fl WHERE fl.dv_id = dv.dv_id AND fl.deleted_at IS NULL)" : '';
    }

    private function releasedJoinSql(): string
    {
        return "JOIN {$this->tableStatus} rst ON rst.id = dv.status_id AND UPPER(rst.name) = 'RELEASED'";
    }

    /**
     * The RELEASED vouchers that reach a fund source through an RFA / LAA and not a SARO, as one row per (fiscal year, program, fund source): `amount` is the
     * share of the voucher's gross_amount on that group's budget lines (every share adds back up to the voucher's gross). source_key NG / TRUST / SUPP, or OTHER
     * (a Type of Fund that is not a fund source on this page).
     */
    private function loadRfaDisbursed(): array
    {
        $supp = $this->hasSuppTag() ? 'li.supplemental_revision_id IS NOT NULL' : 'FALSE';

        return App\ExecuteRows(
            "SELECT br.fiscal_yr, brpg.program_code, pg.program_name,
                    CASE WHEN {$supp} THEN 'SUPP'
                         WHEN UPPER(COALESCE(tof.name, '')) LIKE 'NG SUPPORT%' OR UPPER(COALESCE(tof.name, '')) LIKE 'NG SUBSIDY%' THEN 'NG'
                         WHEN UPPER(COALESCE(tof.name, '')) LIKE 'TRUST FUND%' THEN 'TRUST'
                         ELSE 'OTHER' END AS source_key,
                    SUM(COALESCE(dv.gross_amount, 0) * e.amount / NULLIF(tot.total, 0)) AS amount
             FROM {$this->tableEarmark} e
             JOIN {$this->tableAllocation} a ON a.allocation_id = e.allocation_id AND a.deleted_at IS NULL AND UPPER(a.allocation_type) IN ('RFA', 'LAA')
             JOIN {$this->tableDv} dv ON dv.dv_id = a.dv_id
             {$this->releasedJoinSql()}
             JOIN (SELECT a2.dv_id, SUM(e2.amount) AS total
                     FROM {$this->tableEarmark} e2
                     JOIN {$this->tableAllocation} a2 ON a2.allocation_id = e2.allocation_id AND a2.deleted_at IS NULL AND UPPER(a2.allocation_type) IN ('RFA', 'LAA')
                    WHERE e2.status = 'converted' AND a2.dv_id IS NOT NULL
                    GROUP BY a2.dv_id) tot ON tot.dv_id = a.dv_id
             JOIN {$this->tableLines} li ON li.budget_line_item_id = e.budget_line_item_id
             JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
             LEFT JOIN {$this->tableBudgetReqProject} brpj ON brpj.budget_req_project_id = li.budget_req_project_id
             LEFT JOIN {$this->tableBudgetReqProgram} brpg ON brpg.budget_req_program_id = brpj.budget_req_program_id
             LEFT JOIN {$this->tableProgram} pg ON pg.program_code = brpg.program_code
             LEFT JOIN {$this->tableTypeOfFund} tof ON tof.type_of_fund_id = li.type_of_fund_id
             WHERE e.status = 'converted'
               {$this->notSaroLinkedSql()}
             GROUP BY br.fiscal_yr, brpg.program_code, pg.program_name, 4",
            'DB'
        ) ?: [];
    }

    /**
     * Where the RELEASED vouchers stand, whatever the fiscal year: how many and how much there are in all, how much of it is counted in a fund source (by a SARO
     * or by an RFA / LAA), and what is not — split into the vouchers with no link at all (no SARO, no RFA / LAA: the user can fix those) and what went through a
     * Type of Fund that is not a fund source here. The page shows it when something is not counted, so a voucher that "changed nothing" explains itself.
     *
     * @param list<array> $rfaRows loadRfaDisbursed()
     */
    private function reconciliation(array $rfaRows): array
    {
        $total = App\ExecuteRow("SELECT COUNT(*) AS c, COALESCE(SUM(dv.gross_amount), 0) AS amt FROM {$this->tableDv} dv {$this->releasedJoinSql()}", 'DB') ?: [];
        $viaSaro = $this->hasDvLink()
            ? (App\ExecuteRow(
                "SELECT COUNT(*) AS c, COALESCE(SUM(dv.gross_amount), 0) AS amt FROM {$this->tableDv} dv {$this->releasedJoinSql()}
                  WHERE EXISTS (SELECT 1 FROM {$this->tableFt} fl WHERE fl.dv_id = dv.dv_id AND fl.deleted_at IS NULL)",
                'DB'
            ) ?: [])
            : [];
        $unlinked = App\ExecuteRow(
            "SELECT COUNT(*) AS c, COALESCE(SUM(dv.gross_amount), 0) AS amt FROM {$this->tableDv} dv {$this->releasedJoinSql()}
              WHERE NOT EXISTS (SELECT 1 FROM {$this->tableAllocation} a WHERE a.dv_id = dv.dv_id AND a.deleted_at IS NULL AND UPPER(a.allocation_type) IN ('RFA', 'LAA'))
                {$this->notSaroLinkedSql()}",
            'DB'
        ) ?: [];

        $viaRfa = 0.0;
        $other = 0.0;
        foreach ($rfaRows as $r) {
            if ($r['source_key'] === 'OTHER') {
                $other += (float) $r['amount'];
            } else {
                $viaRfa += (float) $r['amount'];
            }
        }
        $released = (float) ($total['amt'] ?? 0);
        $counted = (float) ($viaSaro['amt'] ?? 0) + $viaRfa;

        return [
            'released_count' => (int) ($total['c'] ?? 0),
            'released' => round($released, 2),
            'counted' => round($counted, 2),
            'not_counted' => round(max(0.0, $released - $counted), 2),
            'unlinked_count' => (int) ($unlinked['c'] ?? 0),
            'unlinked' => round((float) ($unlinked['amt'] ?? 0), 2),
            'other_fund' => round($other, 2),
        ];
    }

    /** Every live fund_tracking record with what the figures need — filtered by fiscal year in PHP, because an NCA looks up its SARO whatever year that is. */
    private function loadRecords(): array
    {
        $dv = $this->hasDvLink();
        $dvCol = $dv
            ? "CASE WHEN UPPER(COALESCE(st.name, '')) = 'RELEASED' THEN COALESCE(dv.gross_amount, 0) ELSE 0 END AS dv_released"
            : '0 AS dv_released';
        $dvJoin = $dv
            ? "LEFT JOIN {$this->tableDv} dv ON dv.dv_id = ft.dv_id
               LEFT JOIN {$this->tableStatus} st ON st.id = dv.status_id"
            : '';

        return App\ExecuteRows(
            "SELECT ft.id, ft.parent_id, ft.fiscal_year, COALESCE(ft.amount, 0) AS amount, ft.saro_id AS reference, ft.description,
                    UPPER(REPLACE(COALESCE(tt.fund_tracking_code, ''), '_', ' ')) AS type_code,
                    UPPER(COALESCE(nf.name, '')) AS nature,
                    sp.program_code, pr.program_name,
                    {$dvCol}
             FROM {$this->tableFt} ft
             LEFT JOIN {$this->tableType} tt ON tt.fund_tracking_type_id = ft.tracking_type_id
             LEFT JOIN {$this->tableNature} nf ON nf.nature_of_fund_id = ft.nature_of_fund_id
             LEFT JOIN {$this->tableSubProgram} sp ON sp.sub_program_code = ft.sub_program
             LEFT JOIN {$this->tableProgram} pr ON pr.program_code = sp.program_code
             {$dvJoin}
             WHERE ft.deleted_at IS NULL
             ORDER BY ft.id",
            'DB'
        ) ?: [];
    }

    private function blank(string $key): array
    {
        return [
            'key' => $key,
            'name' => self::SOURCES[$key],
            'saro_count' => 0, 'saro_released' => 0.0,
            'nca_count' => 0, 'nca_released' => 0.0,
            'trust_count' => 0, 'trust_total' => 0.0,
            'or_count' => 0, 'receipts' => 0.0,
            'disbursed' => 0.0,
            'disbursed_rfa' => 0.0,   // the part of `disbursed` that came through an RFA / LAA rather than a SARO
            'programs' => [],
            'trust_funds' => [],
        ];
    }

    /** The three fund sources with their sums for a fiscal year (null = every year), figures derived, `programs` / `trust_funds` still keyed. */
    private function compute(?int $fiscalYear, ?array $rfaRows = null): array
    {
        $rfaRows ??= $this->loadRfaDisbursed();
        $rows = $this->loadRecords();
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        $src = [];
        foreach (array_keys(self::SOURCES) as $k) {
            $src[$k] = $this->blank($k);
        }

        foreach ($rows as $r) {
            if ($fiscalYear !== null && (int) $r['fiscal_year'] !== $fiscalYear) {
                continue;
            }
            $type = (string) $r['type_code'];
            $amount = (float) $r['amount'];
            $parent = $r['parent_id'] !== null ? ($byId[(int) $r['parent_id']] ?? null) : null;

            if ($type === 'SARO' || $type === 'NCA') {
                // an NCA is of its SARO's nature and program
                $nature = ($type === 'NCA' && $parent) ? (string) $parent['nature'] : (string) $r['nature'];
                $progRow = ($type === 'NCA' && $parent) ? $parent : $r;
                $key = $nature === 'SUPPLEMENTAL' ? 'SUPP' : 'NG';
                $s = &$src[$key];

                $code = (string) ($progRow['program_code'] ?? '');
                if (!isset($s['programs'][$code])) {
                    $s['programs'][$code] = [
                        'program_code' => $code !== '' ? $code : null,
                        'program_name' => $code !== '' ? (string) $progRow['program_name'] : 'No program',
                        'saro_released' => 0.0, 'nca_released' => 0.0, 'disbursed' => 0.0,
                    ];
                }
                if ($type === 'SARO') {
                    $s['saro_count']++;
                    $s['saro_released'] += $amount;
                    $s['disbursed'] += (float) $r['dv_released'];
                    $s['programs'][$code]['saro_released'] += $amount;
                    $s['programs'][$code]['disbursed'] += (float) $r['dv_released'];
                } else {
                    $s['nca_count']++;
                    $s['nca_released'] += $amount;
                    $s['programs'][$code]['nca_released'] += $amount;
                }
                unset($s);
            } elseif ($type === 'TRUST' || $type === 'TRUST FUND') {
                $s = &$src['TRUST'];
                $s['trust_count']++;
                $s['trust_total'] += $amount;
                $s['trust_funds'][(int) $r['id']] = [
                    'id' => (int) $r['id'], 'reference' => $r['reference'], 'description' => $r['description'],
                    'fiscal_year' => $r['fiscal_year'] !== null ? (int) $r['fiscal_year'] : null,
                    'amount' => $amount, 'receipts' => 0.0, 'or_count' => 0,
                ];
                unset($s);
            } elseif ($type === 'OR') {
                $s = &$src['TRUST'];
                $s['or_count']++;
                $s['receipts'] += $amount;
                unset($s);
            }
        }

        // the ORs under each trust fund (by parent, within the year asked for)
        foreach ($rows as $r) {
            if ($r['type_code'] !== 'OR' || $r['parent_id'] === null) {
                continue;
            }
            if ($fiscalYear !== null && (int) $r['fiscal_year'] !== $fiscalYear) {
                continue;
            }
            $pid = (int) $r['parent_id'];
            if (isset($src['TRUST']['trust_funds'][$pid])) {
                $src['TRUST']['trust_funds'][$pid]['receipts'] += (float) $r['amount'];
                $src['TRUST']['trust_funds'][$pid]['or_count']++;
            }
        }

        // the RELEASED vouchers that reach a fund source through an RFA / LAA (not through a SARO): by the budget line's fiscal year, program and fund source
        foreach ($rfaRows as $r) {
            $key = (string) $r['source_key'];
            if (!isset($src[$key]) || ($fiscalYear !== null && (int) $r['fiscal_yr'] !== $fiscalYear)) {
                continue;
            }
            $amount = (float) $r['amount'];
            $src[$key]['disbursed'] += $amount;
            $src[$key]['disbursed_rfa'] += $amount;
            if ($key !== 'TRUST') {
                $code = (string) ($r['program_code'] ?? '');
                if (!isset($src[$key]['programs'][$code])) {
                    $src[$key]['programs'][$code] = [
                        'program_code' => $code !== '' ? $code : null,
                        'program_name' => $code !== '' ? (string) $r['program_name'] : 'No program',
                        'saro_released' => 0.0, 'nca_released' => 0.0, 'disbursed' => 0.0,
                    ];
                }
                $src[$key]['programs'][$code]['disbursed'] += $amount;
            }
        }

        foreach ($src as $k => &$s) {
            $trust = $k === 'TRUST';
            $s['available'] = round($trust ? $s['receipts'] : $s['nca_released'], 2);
            // known when a SARO can carry a voucher (NG / SUPP, once fund_tracking.dv_id exists) or when an RFA / LAA voucher was attributed to it; a Trust Fund has no SARO
            $known = (!$trust && $this->hasDvLink()) || $s['disbursed_rfa'] > 0;
            $s['disbursed'] = $known ? round($s['disbursed'], 2) : null;
            $s['disbursed_rfa'] = round($s['disbursed_rfa'], 2);
            $s['balance'] = round($s['available'] - (float) ($s['disbursed'] ?? 0), 2);
            $s['utilization'] = ($s['disbursed'] !== null && $s['available'] > 0) ? round($s['disbursed'] / $s['available'] * 100, 1) : null;
            $s['unreleased'] = $trust ? null : round($s['saro_released'] - $s['nca_released'], 2);
            $s['saro_released'] = round($s['saro_released'], 2);
            $s['nca_released'] = round($s['nca_released'], 2);
            $s['trust_total'] = round($s['trust_total'], 2);
            $s['receipts'] = round($s['receipts'], 2);

            $programs = array_values($s['programs']);
            usort($programs, fn ($a, $b) => ($a['program_code'] === null) <=> ($b['program_code'] === null) ?: strcmp((string) $a['program_name'], (string) $b['program_name']));
            foreach ($programs as &$p) {
                $p['saro_released'] = round($p['saro_released'], 2);
                $p['nca_released'] = round($p['nca_released'], 2);
                $p['disbursed'] = $known ? round($p['disbursed'], 2) : null;
                $p['undisbursed'] = round($p['nca_released'] - (float) ($p['disbursed'] ?? 0), 2);
                $p['utilization'] = ($p['disbursed'] !== null && $p['nca_released'] > 0) ? round($p['disbursed'] / $p['nca_released'] * 100, 1) : null;
            }
            unset($p);
            $s['programs'] = $programs;
            $s['trust_funds'] = array_values($s['trust_funds']);
        }
        unset($s);

        return $src;
    }

    /** The Fiscal Year filter's options: every year a live fund_tracking record carries, newest first. */
    private function fiscalYears(): array
    {
        $rows = App\ExecuteRows("SELECT DISTINCT fiscal_year FROM {$this->tableFt} WHERE deleted_at IS NULL AND fiscal_year IS NOT NULL ORDER BY fiscal_year DESC", 'DB') ?: [];

        return array_map(fn ($r) => (int) $r['fiscal_year'], $rows);
    }

    /** The overview: one summary row per fund source, and their totals. */
    public function fetchOverview(?int $fiscalYear): array
    {
        try {
            $rfa = $this->loadRfaDisbursed();
            $src = $this->compute($fiscalYear, $rfa);
            $rows = [];
            $available = 0.0;
            $disbursed = 0.0;
            foreach ($src as $s) {
                $rows[] = array_diff_key($s, ['programs' => 1, 'trust_funds' => 1]);
                $available += $s['available'];
                $disbursed += (float) ($s['disbursed'] ?? 0);
            }

            return [
                'success' => true,
                'data' => [
                    'fiscal_years' => $this->fiscalYears(),
                    'vouchers' => $this->reconciliation($rfa),
                    'sources' => $rows,
                    'totals' => [
                        'sources' => count($rows),
                        'available' => round($available, 2),
                        'disbursed' => round($disbursed, 2),
                        'balance' => round($available - $disbursed, 2),
                        'utilization' => $available > 0 ? round($disbursed / $available * 100, 1) : null,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            App\LogError('StatusOfFundService::fetchOverview '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the Status of Funds.'];
        }
    }

    /** One fund source in full: its figures, the per-program split (NG / SUPP) and the per-trust-fund split (TRUST). */
    public function fetchSource(string $key, ?int $fiscalYear): array
    {
        $key = strtoupper(trim($key));
        if (!isset(self::SOURCES[$key])) {
            return ['success' => false, 'message' => 'Unknown fund source. Use one of: '.implode(', ', array_keys(self::SOURCES)).'.', 'status' => 404];
        }
        try {
            $rfa = $this->loadRfaDisbursed();
            $src = $this->compute($fiscalYear, $rfa);

            return ['success' => true, 'data' => $src[$key] + ['fiscal_year' => $fiscalYear, 'vouchers' => $this->reconciliation($rfa)]];
        } catch (\Throwable $e) {
            App\LogError('StatusOfFundService::fetchSource '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the fund source.'];
        }
    }
}
