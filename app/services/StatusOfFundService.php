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
 *   disbursed       what the DISBURSEMENT VOUCHERS linked to the SAROs have paid — a SARO points at the one voucher it funds
 *                   (fims.fund_tracking.dv_id, "03 - alter_table/61"), and only a RELEASED voucher counts, at its gross amount.
 *                   Trust Funds are not linked to vouchers, so their disbursed is null (unknown), not 0.
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

    private static ?bool $hasDvLink = null;

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
            'programs' => [],
            'trust_funds' => [],
        ];
    }

    /** The three fund sources with their sums for a fiscal year (null = every year), figures derived, `programs` / `trust_funds` still keyed. */
    private function compute(?int $fiscalYear): array
    {
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

        foreach ($src as $k => &$s) {
            $trust = $k === 'TRUST';
            $s['available'] = round($trust ? $s['receipts'] : $s['nca_released'], 2);
            $s['disbursed'] = $trust || !$this->hasDvLink() ? null : round($s['disbursed'], 2);
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
                $p['disbursed'] = $this->hasDvLink() ? round($p['disbursed'], 2) : null;
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
            $src = $this->compute($fiscalYear);
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
            $src = $this->compute($fiscalYear);

            return ['success' => true, 'data' => $src[$key] + ['fiscal_year' => $fiscalYear]];
        } catch (\Throwable $e) {
            App\LogError('StatusOfFundService::fetchSource '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the fund source.'];
        }
    }
}
