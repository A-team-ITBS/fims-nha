<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs app/pages/fidelity-bond-page.php. Implements the workflow described
 * in files/07_Fidelity_Bond_Management_Screenshot_Guide.docx:
 *   A. Dashboard & Bond Listing
 *   B. Bond Application — region renewal (existing officers) + new
 *      accountable officer, both going straight to Applied (Office Order
 *      issuance is tracked by a different module, not this one)
 *   C. CL Upload & Register — encode/post a BTr Confirmation Letter, with
 *      duplicate-risk-number validation, + CL Register for COA
 *   D. Bond History — closed/replaced listings (just bond_listings filtered
 *      to CLOSED, not a separate table — see "42 - fims_fidelity_bond.sql"'s
 *      header comment for why)
 *
 * A bond_listings row never leaves that table across its whole life
 * (EXPIRING -> EXPIRED -> APPLIED -> ACTIVE -> CLOSED), so "current listing,
 * Bond History, or any posted CL" (the guide's own phrasing for where a risk
 * number must be unique) collapses to one `risk_no` uniqueness check
 * against this single table. PENDING_BOND remains a valid bond_listings
 * status (old data/other callers may still use it) but nothing in this
 * service writes it anymore.
 */
class FidelityBondService
{
    private string $tableOfficers   = 'fims.accountable_officers';
    private string $tableListings   = 'fims.bond_listings';
    private string $tableCl         = 'fims.confirmation_letters';
    private string $tableClOfficers = 'fims.confirmation_letter_officers';
    private string $tableRc         = 'fims.responsibility_code';

    private const OFFICER_TYPES = ['SDO', 'COLLECTING_OFFICER', 'DISBURSING_OFFICER', 'CASHIER', 'OTHER'];
    private const LISTING_STATUSES = ['EXPIRING', 'EXPIRED', 'APPLIED', 'PENDING_BOND', 'ACTIVE', 'CLOSED'];

    // Tiles on the Dashboard are urgency buckets over bond_listings.status +
    // expiration_date, not raw status values — "Bond Application" means
    // "expiring or already expired", not literally status = 'EXPIRING'.
    private const EXPIRING_SOON_DAYS = 30;

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    // fims.accountable_officers (shared with the GL Accountable Officer
    // page — see "42 - fims_accountable_officers.sql") stores name parts,
    // not one employee_name column. Every query reconstructs "Last, First
    // M. Suffix" from them, aliased back to employee_name so the rest of
    // this service/the frontend never has to know the difference.
    private function fullNameSql(string $alias = 'ao'): string
    {
        return "({$alias}.last_name || ', ' || {$alias}.first_name"
            . " || COALESCE(' ' || NULLIF({$alias}.middle_name, ''), '')"
            . " || COALESCE(' ' || NULLIF({$alias}.suffix, ''), ''))";
    }

    // Re-ranks same-name candidates (from extractClFromText's officer-match
    // lookup) by how well each one's position title and station/region
    // text matches what was actually read off the scan for this officer,
    // on top of the plain name similarity already in match_score. Collapses
    // to the single clear winner when the gap to the runner-up is wide
    // enough to trust; otherwise returns every candidate, just re-sorted,
    // so a human still has to pick — two officers can share a name, a
    // position, AND a region, and this can't out-guess that.
    private function disambiguateOfficerMatches(array $matches, ?string $positionTitle, ?string $stationHint): array
    {
        foreach ($matches as &$row) {
            $score = (float) $row['match_score'];
            if ($positionTitle !== null && !empty($row['position_title'])) {
                $score += 0.5 * $this->textSimilarity($positionTitle, $row['position_title']);
            }
            if ($stationHint !== null && !empty($row['station_name'])) {
                $score += 0.5 * $this->textSimilarity($stationHint, $row['station_name']);
            }
            $row['combined_score'] = $score;
        }
        unset($row);

        usort($matches, fn ($a, $b) => $b['combined_score'] <=> $a['combined_score']);

        $gap = $matches[0]['combined_score'] - $matches[1]['combined_score'];
        if ($gap >= 0.35) {
            return [$matches[0]];
        }

        return $matches;
    }

    // 0..1 containment/overlap score — forgiving of OCR noise and word
    // order (e.g. "Special Disbursing Officer" vs "Disbursing Officer
    // (Special)"), unlike an exact-string or pure-substring comparison.
    private function textSimilarity(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return 1.0;
        }
        $wordsA = array_filter(preg_split('/[\s,\-()]+/', $a), fn ($w) => mb_strlen($w) >= 3);
        $wordsB = array_filter(preg_split('/[\s,\-()]+/', $b), fn ($w) => mb_strlen($w) >= 3);
        if (!$wordsA || !$wordsB) {
            return 0.0;
        }
        $shared = count(array_intersect($wordsA, $wordsB));

        return $shared / max(count($wordsA), count($wordsB));
    }

    private function ok(array $data = []): string
    {
        return json_encode(['success' => true, 'data' => $data]);
    }

    private function fail(string $message): string
    {
        return json_encode(['success' => false, 'message' => $message]);
    }

    // ── Lookups ──────────────────────────────────────────────────────────

    public function getRegions()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rc_id, rc_code, rc_name FROM {$this->tableRc} WHERE status = 'Active' ORDER BY rc_name",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // ── A. Dashboard & Bond Listing ─────────────────────────────────────

    public function getDashboard($params)
    {
        $rcId = trim((string) ($params['region'] ?? ''));
        $statusFilter = trim((string) ($params['status_filter'] ?? ''));
        $search = trim((string) ($params['search'] ?? ''));

        $where = ['1=1'];
        if ($rcId !== '') {
            $where[] = 'bl.rc_id = ' . $this->str($rcId);
        }
        if ($search !== '') {
            $where[] = $this->fullNameSql() . ' ILIKE ' . $this->str('%' . $search . '%');
        }

        // One tile per lifecycle status (CLOSED/Bond History has its own
        // tab, not a Dashboard tile) — counted across the same filters
        // (region/search) except the tile's own status, so clicking a tile
        // narrows the table to exactly what it counted.
        $baseWhereSql = implode(' AND ', $where);
        $kpiRow = App\ExecuteRowAssociative(
            "SELECT
                COUNT(*) FILTER (WHERE bl.status = 'EXPIRED') AS expired,
                COUNT(*) FILTER (WHERE bl.status = 'EXPIRING') AS expiring,
                COUNT(*) FILTER (WHERE bl.status = 'PENDING_BOND') AS pending_bond,
                COUNT(*) FILTER (WHERE bl.status = 'APPLIED') AS applied,
                COUNT(*) FILTER (WHERE bl.status = 'ACTIVE') AS bonded
             FROM {$this->tableListings} bl
             JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = bl.officer_id
             WHERE {$baseWhereSql}",
            'DB'
        ) ?: ['expired' => 0, 'expiring' => 0, 'pending_bond' => 0, 'applied' => 0, 'bonded' => 0];

        if ($statusFilter !== '') {
            $where[] = 'bl.status = ' . $this->str($statusFilter);
        } else {
            // "Show all statuses" default — Bond History (CLOSED) only
            // shows up here when explicitly filtered to it; the main
            // listing defaults to everything still open.
            $where[] = "bl.status != 'CLOSED'";
        }
        $whereSql = implode(' AND ', $where);

        $fullName = $this->fullNameSql();
        $rows = App\ExecuteRowsAssociative(
            "SELECT bl.listing_id, bl.officer_id, {$fullName} AS employee_name, ao.position_title, ao.officer_type,
                    bl.rc_id, rc.rc_name, bl.status, bl.bond_no, bl.risk_no, bl.approved_bond_amount,
                    bl.effectivity_date, bl.expiration_date,
                    (bl.expiration_date - CURRENT_DATE) AS days_left,
                    bl.note, bl.last_cl_id, cl.cl_number AS last_cl_number
             FROM {$this->tableListings} bl
             JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = bl.officer_id
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = bl.rc_id
             LEFT JOIN {$this->tableCl} cl ON cl.cl_id = bl.last_cl_id
             WHERE {$whereSql}
             ORDER BY bl.expiration_date ASC NULLS LAST",
            'DB'
        );

        return $this->ok(['kpis' => $kpiRow, 'listings' => $rows ?: []]);
    }

    // CL window opened by clicking a "Last CL issued" number on the
    // Dashboard — coverage, who/when posted, total approved amount, the
    // scanned file, and every officer on that same letter.
    public function getClSummary($clId)
    {
        $clId = (int) $clId;
        $cl = App\ExecuteRowAssociative(
            "SELECT cl.*, rc.rc_name
             FROM {$this->tableCl} cl
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cl.rc_id
             WHERE cl.cl_id = {$clId}",
            'DB'
        );
        if (!$cl) {
            return $this->fail('Confirmation Letter not found.');
        }
        // scanned_file_url holds the object key from POST /upload/file, not
        // a real URL — resolve a short-lived presigned link for display.
        $cl['scanned_file_view_url'] = (new FileUploadServices())->viewUrlFor($cl['scanned_file_url'] ?? null);
        $officers = App\ExecuteRowsAssociative(
            "SELECT clo.*, {$this->fullNameSql()} AS employee_name, ao.position_title
             FROM {$this->tableClOfficers} clo
             JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = clo.officer_id
             WHERE clo.cl_id = {$clId}
             ORDER BY ao.last_name, ao.first_name",
            'DB'
        ) ?: [];
        $cl['total_approved_amount'] = array_sum(array_column($officers, 'approved_bond_amount'));
        $cl['officers'] = $officers;

        return $this->ok($cl);
    }

    // ── B. Bond Application ─────────────────────────────────────────────

    // Section A: officers in this region whose listing is EXPIRING/EXPIRED —
    // selecting one and submitting the application tags it Applied.
    public function getOfficersForRegion($rcId)
    {
        $rcId = trim((string) $rcId);
        // Empty/"ALL" rcId means "All Regions" — show every officer due for
        // renewal across regions instead of requiring one to be picked.
        $where = "bl.status IN ('EXPIRING', 'EXPIRED')";
        if ($rcId !== '' && strtoupper($rcId) !== 'ALL') {
            $where .= ' AND bl.rc_id = ' . $this->str($rcId);
        }
        $rows = App\ExecuteRowsAssociative(
            "SELECT bl.listing_id, ao.accountable_officer_id AS officer_id, {$this->fullNameSql()} AS employee_name, ao.position_title, ao.officer_type,
                    bl.status, bl.bond_no, bl.expiration_date,
                    bl.rc_id, rc.rc_name
             FROM {$this->tableListings} bl
             JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = bl.officer_id
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = bl.rc_id
             WHERE {$where}
             ORDER BY bl.expiration_date ASC",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // Submits a renewal application for one-or-more selected listings —
    // every listing tags straight to APPLIED.
    public function submitApplication($data)
    {
        $listingIds = array_filter(array_map('intval', (array) ($data['listing_ids'] ?? [])));
        if (!$listingIds) {
            return $this->fail('Select at least one officer to renew.');
        }
        $dateFiled = trim((string) ($data['date_filed_btr'] ?? ''));
        if ($dateFiled === '') {
            return $this->fail('Date filed with BTr is required.');
        }
        $premiumRef = trim((string) ($data['premium_payment_reference'] ?? ''));
        $applicationFile = trim((string) ($data['application_file_url'] ?? ''));
        $proofFile = trim((string) ($data['proof_of_payment_file_url'] ?? ''));
        $ooFile = trim((string) ($data['oo_file_url'] ?? ''));

        $idsSql = implode(',', $listingIds);
        $rows = App\ExecuteRowsAssociative(
            "SELECT bl.listing_id, bl.officer_id
             FROM {$this->tableListings} bl
             WHERE bl.listing_id IN ({$idsSql}) AND bl.status IN ('EXPIRING', 'EXPIRED')",
            'DB'
        ) ?: [];
        if (!$rows) {
            return $this->fail('None of the selected listings are eligible for renewal.');
        }

        $appliedCount = 0;
        App\Execute('BEGIN', 'DB');
        try {
            foreach ($rows as $row) {
                $listingId = (int) $row['listing_id'];
                App\Execute(
                    "UPDATE {$this->tableListings}
                     SET status = 'APPLIED', date_filed_btr = " . $this->str($dateFiled) . ",
                         premium_payment_reference = " . $this->strOrNull($premiumRef) . ",
                         application_file_url = " . $this->strOrNull($applicationFile) . ",
                         proof_of_payment_file_url = " . $this->strOrNull($proofFile) . ",
                         updated_at = CURRENT_TIMESTAMP
                     WHERE listing_id = {$listingId}",
                    'DB'
                );
                $appliedCount++;
            }
            if ($ooFile !== '') {
                // One upload covers the whole bulk submission — attached to
                // every renewing officer's own record, same column Section
                // B's new-officer Office Order upload writes to.
                $officerIds = array_unique(array_map(fn ($r) => (int) $r['officer_id'], $rows));
                App\Execute(
                    "UPDATE {$this->tableOfficers} SET oo_file_url = " . $this->str($ooFile) . ", updated_at = CURRENT_TIMESTAMP
                     WHERE accountable_officer_id IN (" . implode(',', $officerIds) . ")",
                    'DB'
                );
            }
            App\Execute('COMMIT', 'DB');
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return $this->fail('Could not submit application: ' . $e->getMessage());
        }

        return $this->ok(['applied_count' => $appliedCount]);
    }

    // New accountable officer — goes straight to Applied, same as a renewal
    // (Office Order issuance isn't tracked by this module).
    public function addOfficer($data)
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $suffix = trim((string) ($data['suffix'] ?? ''));
        $position = trim((string) ($data['position_title'] ?? ''));
        $officerType = strtoupper(trim((string) ($data['officer_type'] ?? '')));
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        $accountability = (float) ($data['accountability_amount'] ?? 0);
        $ooFileUrl = trim((string) ($data['oo_file_url'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            return $this->fail('First and last name are required.');
        }
        if (!in_array($officerType, self::OFFICER_TYPES, true)) {
            return $this->fail('Select a valid officer type.');
        }
        if ($rcId === '' || !App\ExecuteRow("SELECT rc_id FROM {$this->tableRc} WHERE rc_id = " . $this->str($rcId), 'DB')) {
            return $this->fail('Select a valid region/office.');
        }
        $userId = (int) App\CurrentUserID();

        App\Execute('BEGIN', 'DB');
        try {
            $officerRow = App\ExecuteRow(
                "INSERT INTO {$this->tableOfficers} (first_name, middle_name, last_name, suffix, position_title, officer_type, rc_id, accountability_amount, oo_file_url, created_by)
                 VALUES (" . $this->str($firstName) . ', ' . $this->strOrNull($middleName) . ', ' . $this->str($lastName) . ', '
                    . $this->strOrNull($suffix) . ', ' . $this->strOrNull($position) . ', ' . $this->str($officerType) . ', '
                    . $this->str($rcId) . ", {$accountability}, " . $this->strOrNull($ooFileUrl) . ", {$userId})
                 RETURNING accountable_officer_id AS officer_id",
                'DB'
            );
            $officerId = (int) $officerRow['officer_id'];

            $listingRow = App\ExecuteRow(
                "INSERT INTO {$this->tableListings} (officer_id, rc_id, status, created_by)
                 VALUES ({$officerId}, " . $this->str($rcId) . ", 'APPLIED', {$userId})
                 RETURNING listing_id",
                'DB'
            );
            $listingId = (int) $listingRow['listing_id'];
            App\Execute('COMMIT', 'DB');
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return $this->fail('Could not add officer: ' . $e->getMessage());
        }

        return $this->ok(['officer_id' => $officerId, 'listing_id' => $listingId]);
    }

    // ── D. CL Upload & Register ─────────────────────────────────────────

    // Best-effort autofill from OCR text read off an uploaded scan of a BTr
    // Confirmation Letter. OCR itself runs client-side (Tesseract.js, WASM,
    // loaded from CDN — see app/pages/fidelity-bond-page.php's fbClRunOcr())
    // so this works on any host: the server never shells out to a Tesseract/
    // poppler binary, so there's nothing to install here regardless of OS
    // or whether this is running in this repo's Docker image. This method
    // only pattern-matches the known paper template (Transmittal No. / Date
    // header, then one table row per officer: Name, (n) Position, Station,
    // Risk No., Approved Amount, Bonding / Cancellation dates) against
    // whatever text the browser recognized — plain text-pattern matching
    // against a fixed template, not real document understanding — scan
    // quality, skew, or a differently-formatted letter will degrade or
    // break it. Every autofilled field stays fully editable in the form;
    // nothing here writes to the database on its own.
    public function extractClFromText(?string $text)
    {
        if ($text === null || trim($text) === '') {
            return $this->fail('Could not read any text from the scan.');
        }

        return $this->ok($this->parseClTemplate($text));
    }

    // Pattern-matches the BTr Confirmation Letter template against raw OCR
    // text. Returns every field it could find (null if not found) plus the
    // raw text itself, so a bad match is still inspectable/correctable by
    // hand instead of just silently failing.
    private function parseClTemplate(string $text): array
    {
        $clNumber = null;
        // The CL Number is whatever's labeled the Transmittal No./Number —
        // "No\b" alone (no word boundary) would also match inside
        // "Number" and swallow "mber" into the captured value, so the
        // label itself is matched as one full word via alternation. The
        // gap before the value allows underscores too, not just
        // whitespace/colon/hyphen — OCR reads the paper form's blank
        // writing line as literal "_" characters (e.g. "No.__13D4-...").
        if (preg_match('/Transmittal\s*(?:No\.?|Number)[\s_:\-]*([A-Za-z0-9][A-Za-z0-9\-]{3,30})/i', $text, $m)) {
            $clNumber = $m[1];
        } elseif (preg_match('/\b([0-9]{2,4}[A-Z]{1,2}\d?-\d{4}-\d{1,2}-\d{3,8})\b/', $text, $m)) {
            // "Transmittal No." misread or lost entirely (common near the
            // letterhead seal it sits beside) — this shape is distinct
            // enough from the Risk No. (4 hyphen-separated groups, with a
            // month-length 1-2 digit group, vs. Risk No.'s 3 groups) to
            // find on its own, no label needed.
            $clNumber = $m[1];
        }

        preg_match_all('/\b(\d{1,2}\/\d{1,2}\/\d{2,4})\b/', $text, $dateMatches);
        $dates = $dateMatches[1] ?? [];
        $clDate = $dates[0] ?? null;

        // "Bonding"/"Cancellation" label the two dates in the Effective
        // Date column; fall back to "2nd and 3rd date found" if the OCR
        // dropped those two words (common — they're small column headers).
        // One shared pair for the whole letter — Section 1's Effectivity
        // field applies identically to every officer once posted (see
        // postCl()), there's no per-officer date in this app's data model
        // even though the paper form repeats it on every row.
        $effectivityDate = null;
        $expirationDate = null;
        if (preg_match('/Bonding\D{0,15}(\d{1,2}\/\d{1,2}\/\d{2,4})/i', $text, $m)) {
            $effectivityDate = $m[1];
        } elseif (isset($dates[1])) {
            $effectivityDate = $dates[1];
        }
        if (preg_match('/Cancellation\D{0,15}(\d{1,2}\/\d{1,2}\/\d{2,4})/i', $text, $m)) {
            $expirationDate = $m[1];
        } elseif (isset($dates[2])) {
            $expirationDate = $dates[2];
        }

        // One table row per officer — Risk No. is the anchor (the one
        // token with an unmistakable shape); name/station sit before it
        // on the same OCR line, approved amount on that same line,
        // position on the line below (e.g. "(1) Special Disbursing
        // Officer (SDO)"). A single letter can — and often does — name
        // more than one officer, so every Risk No. found gets its own
        // entry instead of just the first.
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $officers = [];
        $firstStationHint = null;
        foreach ($lines as $i => $line) {
            if (!preg_match('/\b([0-9]{2,4}[A-Z]\d-\d{4}-\d{3,6}[A-Z]?)\b/', $line, $rm)) {
                continue;
            }
            $riskNo = $rm[1];
            $before = trim(substr($line, 0, strpos($line, $riskNo)));

            $officerName = null;
            $stationHint = null;
            // Anchored on "LASTNAME[ LASTNAME], FIRSTNAME[ MIDDLE][ INITIAL]"
            // specifically (word counts bounded) rather than any run of
            // capital letters, so this stops at the comma instead of
            // running on into the Station column that follows it.
            if (preg_match('/([A-Z][A-Z\']{1,20}(?:\s+[A-Z][A-Z\']{1,20}){0,2}),\s*([A-Z][A-Z\'.]{1,20}(?:\s+[A-Z][A-Z\'.]{1,20}){0,3})/', $before, $m, PREG_OFFSET_CAPTURE)) {
                $officerName = trim(preg_replace('/\s+/', ' ', $m[1][0] . ', ' . $m[2][0]));
                // Whatever's left in this line after the name — the
                // Station column, wrapped onto the same OCR line as the
                // name since there's no real column separator once the
                // table's grid lines are gone.
                $stationHint = trim(substr($before, $m[2][1] + strlen($m[2][0])));
            }

            $positionTitle = null;
            $nextLine = $lines[$i + 1] ?? '';
            // Stops before "Bond coverage: ..."/a stray digit — the
            // position title itself never contains either.
            if (preg_match('/\(\d+\)\s*([A-Za-z][A-Za-z\s()]{3,60}?)(?:\s+Bond\b|\s*\d|$)/', $nextLine, $pm)) {
                $positionTitle = trim($pm[1]);
            }

            $approvedAmount = null;
            if (preg_match('/\b(\d{1,3}(?:,\d{3})+\.\d{2})\b/', $line, $am)) {
                $approvedAmount = (float) str_replace(',', '', $am[1]);
            }

            $officerMatches = [];
            $officerIneligibleReason = null;
            if ($officerName !== null) {
                $lastName = trim(explode(',', $officerName)[0] ?? '');
                if ($lastName !== '') {
                    // Nearest match against the whole OCR'd name, not just
                    // an exact last-name substring — OCR commonly drops a
                    // middle initial or misreads a letter.
                    $full = $this->fullNameSql();
                    $s = $this->str($officerName);
                    $officerMatches = App\ExecuteRowsAssociative(
                        "SELECT ao.accountable_officer_id AS officer_id, {$full} AS employee_name, ao.position_title, rc.rc_name AS station_name,
                                public.similarity({$full}, {$s}) AS match_score
                         FROM {$this->tableOfficers} ao
                         LEFT JOIN {$this->tableRc} rc ON rc.rc_id = ao.rc_id
                         WHERE (ao.last_name ILIKE " . $this->str('%' . $lastName . '%') . " OR public.similarity({$full}, {$s}) > 0.2)
                         -- Same eligibility rule as postNewCl() itself: an officer can
                         -- only go on a new letter while their current listing is
                         -- APPLIED — already ACTIVE/EXPIRING/etc. means they're
                         -- already bonded, so matching them here would offer an
                         -- officer the scan can't actually be posted for.
                         AND (SELECT status FROM {$this->tableListings}
                              WHERE officer_id = ao.accountable_officer_id AND status != 'CLOSED'
                              ORDER BY listing_id DESC LIMIT 1) = 'APPLIED'
                         ORDER BY match_score DESC, ao.last_name, ao.first_name
                         LIMIT 5",
                        'DB'
                    ) ?: [];
                    // Two officers can share a name — the name match alone
                    // doesn't pick between them, so it's rescored against
                    // the position title and station text read off this
                    // same row. When that clearly separates one candidate
                    // from the rest, only that one is returned (so the
                    // frontend's "exactly one match" auto-add still fires);
                    // otherwise every candidate stays, now ranked, for the
                    // officer to be picked by hand.
                    if (count($officerMatches) > 1) {
                        $officerMatches = $this->disambiguateOfficerMatches($officerMatches, $positionTitle, $stationHint);
                    }

                    // No *eligible* match doesn't mean no match at all — the
                    // name can still belong to an officer who's already
                    // ACTIVE/EXPIRING/etc. under a prior letter. Surfaced
                    // separately so the warning says that plainly instead of
                    // "no matching officer", which would send someone
                    // looking for a typo that isn't there.
                    if (!$officerMatches) {
                        $ineligible = App\ExecuteRow(
                            "SELECT bl.status FROM {$this->tableOfficers} ao
                             JOIN {$this->tableListings} bl ON bl.officer_id = ao.accountable_officer_id
                             WHERE (ao.last_name ILIKE " . $this->str('%' . $lastName . '%') . " OR public.similarity({$full}, {$s}) > 0.2)
                             AND bl.status != 'CLOSED'
                             ORDER BY public.similarity({$full}, {$s}) DESC, bl.listing_id DESC
                             LIMIT 1",
                            'DB'
                        );
                        if ($ineligible) {
                            $officerIneligibleReason = 'already ' . strtolower($ineligible['status']);
                        }
                    }
                }
            }

            if ($firstStationHint === null && $stationHint) {
                $firstStationHint = $stationHint;
            }

            $officers[] = [
                'risk_no' => $riskNo,
                'approved_bond_amount' => $approvedAmount,
                'officer_name_guess' => $officerName,
                'position_title_guess' => $positionTitle,
                'officer_matches' => $officerMatches,
                'ineligible_reason' => $officerIneligibleReason,
            ];
        }

        // Region is one shared field for the whole letter too (Section 1's
        // Region dropdown) — matched off whichever officer's Station text
        // was read first.
        $regionMatches = [];
        if ($firstStationHint) {
            $words = array_filter(preg_split('/[\s,\-]+/', $firstStationHint), fn ($w) => mb_strlen($w) >= 4);
            foreach ($words as $word) {
                $regionMatches = App\ExecuteRowsAssociative(
                    "SELECT rc_id, rc_name FROM {$this->tableRc} WHERE status = 'Active' AND rc_name ILIKE " . $this->str('%' . $word . '%') . ' ORDER BY rc_name LIMIT 5',
                    'DB'
                ) ?: [];
                if ($regionMatches) {
                    break;
                }
            }
        }

        return [
            'cl_number' => $clNumber,
            'cl_date' => $this->normalizeDate($clDate),
            'effectivity_date' => $this->normalizeDate($effectivityDate),
            'expiration_date' => $this->normalizeDate($expirationDate),
            'rc_id_guess' => count($regionMatches) === 1 ? $regionMatches[0]['rc_id'] : null,
            'region_matches' => $regionMatches,
            'officers' => $officers,
            'raw_text' => $text,
        ];
    }

    // MM/DD/YYYY (the paper form's convention) -> YYYY-MM-DD (this app's
    // <input type="date"> convention), or null if unparseable/absent.
    private function normalizeDate(?string $mdy): ?string
    {
        if ($mdy === null) {
            return null;
        }
        $parts = preg_split('#[/\-]#', $mdy);
        if (count($parts) !== 3) {
            return null;
        }
        [$m, $d, $y] = $parts;
        if (strlen($y) === 2) {
            $y = ((int) $y < 70 ? '20' : '19') . $y;
        } elseif (strlen($y) !== 4) {
            // A misread/cut-off year (OCR dropped a digit) — rather than
            // guess, refuse it so the field stays blank for manual entry.
            return null;
        }
        if (!checkdate((int) $m, (int) $d, (int) $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }

    // Encodes AND posts a Confirmation Letter in one call — no DRAFT state,
    // no separate per-officer round trips. Everything (header fields +
    // every named officer's Risk No./Approved Amount) is composed
    // client-side first and sent here once; every officer is validated
    // (exists, currently APPLIED, no risk-number conflict) before any
    // write happens, so a bad entry can't leave a half-created letter
    // behind the way the old create-draft-then-add-one-by-one flow could.
    // Does the same things createCl() + addOfficerToCl() (xN) + postCl()
    // used to across three separate requests: close each officer's old
    // listing, open their new ACTIVE one, link both to this CL.
    public function postNewCl($data)
    {
        $clNumber = trim((string) ($data['cl_number'] ?? ''));
        $clDate = trim((string) ($data['cl_date'] ?? ''));
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        $effectivity = trim((string) ($data['new_effectivity_date'] ?? ''));
        $scannedFile = trim((string) ($data['scanned_file_url'] ?? ''));
        $officersIn = (array) ($data['officers'] ?? []);
        if ($clNumber === '' || $clDate === '' || $rcId === '' || $effectivity === '') {
            return $this->fail('CL number, date, region, and new effectivity date are required.');
        }
        if (App\ExecuteRow("SELECT cl_id FROM {$this->tableCl} WHERE cl_number = " . $this->str($clNumber), 'DB')) {
            return $this->fail('A Confirmation Letter with this number already exists.');
        }
        if (!$officersIn) {
            return $this->fail('Add at least one officer named in the letter.');
        }

        // Validate every officer up front — same eligibility rule
        // addOfficerToCl() used (must have a current APPLIED listing) and
        // the same risk-number uniqueness riskNumberConflict() already
        // checks against current listings, Bond History, and every
        // posted CL — plus a check against duplicates within this same
        // submission, which riskNumberConflict() alone can't see yet.
        $clean = [];
        $seenRisk = [];
        foreach ($officersIn as $o) {
            $officerId = (int) ($o['officer_id'] ?? 0);
            $riskNo = trim((string) ($o['new_risk_no'] ?? ''));
            $amount = (float) ($o['approved_bond_amount'] ?? 0);
            if (!$officerId || $riskNo === '' || $amount <= 0) {
                return $this->fail('Every officer needs a selected name, a Risk No., and an Approved Bond Amount.');
            }
            $listing = App\ExecuteRow(
                "SELECT listing_id, status FROM {$this->tableListings} WHERE officer_id = {$officerId} AND status != 'CLOSED' ORDER BY listing_id DESC LIMIT 1",
                'DB'
            );
            if (!$listing || $listing['status'] !== 'APPLIED') {
                $reason = $listing ? "currently {$listing['status']}, not Applied" : 'has no active listing';

                return $this->fail("One of the named officers is not eligible — {$reason}.");
            }
            $riskKey = strtoupper($riskNo);
            if (isset($seenRisk[$riskKey])) {
                return $this->fail("Risk No. {$riskNo} is entered for more than one officer on this same letter.");
            }
            $seenRisk[$riskKey] = true;
            $conflict = $this->riskNumberConflict($riskNo);
            if ($conflict) {
                return $this->fail($conflict);
            }
            $clean[] = ['officer_id' => $officerId, 'old_listing_id' => (int) $listing['listing_id'], 'new_risk_no' => $riskNo, 'approved_bond_amount' => $amount];
        }

        $userId = (int) App\CurrentUserID();
        App\Execute('BEGIN', 'DB');
        try {
            $clRow = App\ExecuteRow(
                "INSERT INTO {$this->tableCl} (cl_number, cl_date, rc_id, new_effectivity_date, new_expiration_date, scanned_file_url, status, created_by, posted_by, posted_at)
                 VALUES (" . $this->str($clNumber) . ', ' . $this->str($clDate) . ', ' . $this->str($rcId) . ', '
                    . $this->str($effectivity) . ", (" . $this->str($effectivity) . "::date + INTERVAL '1 year')::date, "
                    . $this->strOrNull($scannedFile) . ", 'POSTED', {$userId}, {$userId}, CURRENT_TIMESTAMP)
                 RETURNING cl_id, new_expiration_date",
                'DB'
            );
            $clId = (int) $clRow['cl_id'];
            $newExpiration = $clRow['new_expiration_date'];

            foreach ($clean as $o) {
                $clOfficerRow = App\ExecuteRow(
                    "INSERT INTO {$this->tableClOfficers} (cl_id, officer_id, old_listing_id, new_risk_no, approved_bond_amount)
                     VALUES ({$clId}, {$o['officer_id']}, {$o['old_listing_id']}, " . $this->str($o['new_risk_no']) . ", {$o['approved_bond_amount']})
                     RETURNING cl_officer_id",
                    'DB'
                );
                App\Execute(
                    "UPDATE {$this->tableListings} SET status = 'CLOSED', closed_by_cl_id = {$clId}, updated_at = CURRENT_TIMESTAMP WHERE listing_id = {$o['old_listing_id']}",
                    'DB'
                );
                $newListing = App\ExecuteRow(
                    "INSERT INTO {$this->tableListings}
                        (officer_id, rc_id, status, risk_no, approved_bond_amount, effectivity_date, expiration_date,
                         last_cl_id, replaces_listing_id, created_by)
                     VALUES ({$o['officer_id']}, " . $this->str($rcId) . ", 'ACTIVE', "
                        . $this->str($o['new_risk_no']) . ", {$o['approved_bond_amount']}, "
                        . $this->str($effectivity) . ', ' . $this->str($newExpiration) . ", "
                        . "{$clId}, {$o['old_listing_id']}, {$userId})
                     RETURNING listing_id",
                    'DB'
                );
                App\Execute(
                    "UPDATE {$this->tableClOfficers} SET new_listing_id = {$newListing['listing_id']} WHERE cl_officer_id = {$clOfficerRow['cl_officer_id']}",
                    'DB'
                );
            }
            App\Execute('COMMIT', 'DB');
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return $this->fail('Could not post Confirmation Letter: ' . $e->getMessage());
        }

        return $this->ok(['cl_id' => $clId, 'officers_posted' => count($clean), 'new_expiration_date' => $newExpiration]);
    }

    public function createCl($data)
    {
        $clNumber = trim((string) ($data['cl_number'] ?? ''));
        $clDate = trim((string) ($data['cl_date'] ?? ''));
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        $newEffectivity = trim((string) ($data['new_effectivity_date'] ?? ''));
        $scannedFile = trim((string) ($data['scanned_file_url'] ?? ''));
        if ($clNumber === '' || $clDate === '' || $rcId === '' || $newEffectivity === '') {
            return $this->fail('CL number, date, region, and new effectivity date are required.');
        }
        if (App\ExecuteRow("SELECT cl_id FROM {$this->tableCl} WHERE cl_number = " . $this->str($clNumber), 'DB')) {
            return $this->fail('A Confirmation Letter with this number already exists.');
        }
        $userId = (int) App\CurrentUserID();
        // New expiration = new effectivity + 1 year, same as the guide's
        // auto-shown field.
        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableCl} (cl_number, cl_date, rc_id, new_effectivity_date, new_expiration_date, scanned_file_url, created_by)
             VALUES (" . $this->str($clNumber) . ', ' . $this->str($clDate) . ', ' . $this->str($rcId) . ', '
                . $this->str($newEffectivity) . ", (" . $this->str($newEffectivity) . "::date + INTERVAL '1 year')::date, "
                . $this->strOrNull($scannedFile) . ", {$userId})
             RETURNING cl_id, new_expiration_date",
            'DB'
        );

        return $this->ok($row);
    }

    // Manually backfills a paper Confirmation Letter that predates this
    // system straight into the CL Register — inserted already POSTED, with
    // its own officer rows, but deliberately does NOT touch bond_listings
    // (no closing an "old" listing, no new ACTIVE listing): the officers'
    // current listings already reflect reality, and a historical CL from
    // years ago shouldn't retroactively rewrite them. It only ever shows up
    // in the CL Register and in its own CL summary modal.
    public function addLegacyCl($data)
    {
        $clNumber = trim((string) ($data['cl_number'] ?? ''));
        $clDate = trim((string) ($data['cl_date'] ?? ''));
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        $effectivity = trim((string) ($data['new_effectivity_date'] ?? ''));
        $expiration = trim((string) ($data['new_expiration_date'] ?? ''));
        $scannedFile = trim((string) ($data['scanned_file_url'] ?? ''));
        $officers = (array) ($data['officers'] ?? []);
        if ($clNumber === '' || $clDate === '' || $rcId === '' || $effectivity === '' || $expiration === '') {
            return $this->fail('CL number, date, region, effectivity, and expiration are required.');
        }
        if (App\ExecuteRow("SELECT cl_id FROM {$this->tableCl} WHERE cl_number = " . $this->str($clNumber), 'DB')) {
            return $this->fail('A Confirmation Letter with this number already exists.');
        }
        if (!$officers) {
            return $this->fail('Add at least one officer named in the letter.');
        }
        $clean = [];
        foreach ($officers as $o) {
            $officerId = (int) ($o['officer_id'] ?? 0);
            $riskNo = trim((string) ($o['new_risk_no'] ?? ''));
            $amount = (float) ($o['approved_bond_amount'] ?? 0);
            if (!$officerId || $riskNo === '') {
                return $this->fail('Every officer needs a selected name and a Risk No.');
            }
            if (!App\ExecuteRow("SELECT accountable_officer_id FROM {$this->tableOfficers} WHERE accountable_officer_id = {$officerId}", 'DB')) {
                return $this->fail('One of the named officers no longer exists.');
            }
            $clean[] = ['officer_id' => $officerId, 'new_risk_no' => $riskNo, 'approved_bond_amount' => $amount];
        }
        $userId = (int) App\CurrentUserID();

        App\Execute('BEGIN', 'DB');
        try {
            $clRow = App\ExecuteRow(
                "INSERT INTO {$this->tableCl} (cl_number, cl_date, rc_id, new_effectivity_date, new_expiration_date, scanned_file_url, status, created_by, posted_by, posted_at)
                 VALUES (" . $this->str($clNumber) . ', ' . $this->str($clDate) . ', ' . $this->str($rcId) . ', '
                    . $this->str($effectivity) . ', ' . $this->str($expiration) . ', '
                    . $this->strOrNull($scannedFile) . ", 'POSTED', {$userId}, {$userId}, CURRENT_TIMESTAMP)
                 RETURNING cl_id",
                'DB'
            );
            $clId = (int) $clRow['cl_id'];
            foreach ($clean as $o) {
                App\Execute(
                    "INSERT INTO {$this->tableClOfficers} (cl_id, officer_id, new_risk_no, approved_bond_amount)
                     VALUES ({$clId}, {$o['officer_id']}, " . $this->str($o['new_risk_no']) . ", {$o['approved_bond_amount']})",
                    'DB'
                );
            }
            App\Execute('COMMIT', 'DB');
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return $this->fail('Could not add legacy CL: ' . $e->getMessage());
        }

        return $this->ok(['cl_id' => $clId]);
    }

    public function getCl($id)
    {
        return $this->getClSummary($id);
    }

    // Autocomplete for "officers named in the letter" — only APPLIED
    // officers are valid; anything else is refused with the reason why.
    // No search text and no region = every non-Closed officer, so the CL
    // draft's "Officers named in the letter" panel can list all of them by
    // default; both filters narrow from there, name search ANDed with the
    // region pick.
    public function searchApplicableOfficers($params)
    {
        $search = trim((string) ($params['search'] ?? ''));
        $rcId = trim((string) ($params['rc_id'] ?? ''));
        $full = $this->fullNameSql();
        $where = ["bl.status != 'CLOSED'"];
        $orderBy = 'ao.last_name, ao.first_name';
        if ($search !== '') {
            $s = $this->str($search);
            // Nearest-match, not just substring — a dropped middle
            // initial, an OCR typo, or words in a different order
            // (e.g. "JAIRA ORTIZ" vs "ORTIZ, JAIRA") still finds the
            // right officer, ranked by how close it actually is.
            // similarity() needs the public. prefix: this connection's
            // search_path is set to just "fims" (see phpfn.php's SET
            // search_path TO ... on connect), so pg_trgm's functions
            // (installed in public, the extension's default schema)
            // don't resolve unqualified the way fims.* tables always do.
            $where[] = "({$full} ILIKE " . $this->str('%' . $search . '%') . " OR public.similarity({$full}, {$s}) > 0.2)";
            $orderBy = "public.similarity({$full}, {$s}) DESC, ao.last_name, ao.first_name";
        }
        if ($rcId !== '' && strtoupper($rcId) !== 'ALL') {
            $where[] = 'bl.rc_id = ' . $this->str($rcId);
        }
        $whereSql = implode(' AND ', $where);
        $rows = App\ExecuteRowsAssociative(
            "SELECT bl.listing_id, ao.accountable_officer_id AS officer_id, {$full} AS employee_name, ao.position_title, bl.status,
                    bl.rc_id, rc.rc_name
             FROM {$this->tableOfficers} ao
             JOIN {$this->tableListings} bl ON bl.officer_id = ao.accountable_officer_id
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = bl.rc_id
             WHERE {$whereSql}
             ORDER BY {$orderBy}",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    private function riskNumberConflict(string $riskNo, ?int $excludeListingId = null): ?string
    {
        $sql = "SELECT bl.risk_no, bl.status, {$this->fullNameSql()} AS employee_name
                FROM {$this->tableListings} bl
                JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = bl.officer_id
                WHERE bl.risk_no = " . $this->str($riskNo);
        if ($excludeListingId) {
            $sql .= " AND bl.listing_id != {$excludeListingId}";
        }
        $existing = App\ExecuteRow($sql, 'DB');
        if ($existing) {
            return "Risk No. {$riskNo} is already used by {$existing['employee_name']} (status: {$existing['status']}).";
        }

        return null;
    }

    public function addOfficerToCl($clId, $data)
    {
        $clId = (int) $clId;
        $officerId = (int) ($data['officer_id'] ?? 0);
        $newRiskNo = trim((string) ($data['new_risk_no'] ?? ''));
        $approvedAmount = (float) ($data['approved_bond_amount'] ?? 0);
        if (!$officerId) {
            return $this->fail('Select an officer.');
        }
        $cl = App\ExecuteRow("SELECT cl_id, status FROM {$this->tableCl} WHERE cl_id = {$clId}", 'DB');
        if (!$cl) {
            return $this->fail('Confirmation Letter not found.');
        }
        if ($cl['status'] === 'POSTED') {
            return $this->fail('This Confirmation Letter has already been posted and is read-only.');
        }
        $listing = App\ExecuteRow(
            "SELECT listing_id, status FROM {$this->tableListings} WHERE officer_id = {$officerId} AND status != 'CLOSED' ORDER BY listing_id DESC LIMIT 1",
            'DB'
        );
        if (!$listing || $listing['status'] !== 'APPLIED') {
            $reason = $listing ? "currently {$listing['status']}, not Applied" : 'has no active listing';
            return $this->fail("This officer is not eligible — {$reason}.");
        }
        if (App\ExecuteRow("SELECT cl_officer_id FROM {$this->tableClOfficers} WHERE cl_id = {$clId} AND officer_id = {$officerId}", 'DB')) {
            return $this->fail('This officer is already on this letter.');
        }
        if ($newRiskNo !== '') {
            $conflict = $this->riskNumberConflict($newRiskNo);
            if ($conflict) {
                return $this->fail($conflict);
            }
            // Also reject a risk number already used elsewhere in this same
            // draft CL (duplicate within the form, before anything posts).
            if (App\ExecuteRow("SELECT cl_officer_id FROM {$this->tableClOfficers} WHERE cl_id = {$clId} AND new_risk_no = " . $this->str($newRiskNo), 'DB')) {
                return $this->fail("Risk No. {$newRiskNo} is already entered for another officer on this same letter.");
            }
        }

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableClOfficers} (cl_id, officer_id, old_listing_id, new_risk_no, approved_bond_amount)
             VALUES ({$clId}, {$officerId}, {$listing['listing_id']}, " . $this->strOrNull($newRiskNo) . ', '
                . ($approvedAmount ?: 'NULL') . ")
             RETURNING cl_officer_id",
            'DB'
        );

        return $this->ok($row);
    }

    public function removeOfficerFromCl($clId, $clOfficerId)
    {
        $clId = (int) $clId;
        $clOfficerId = (int) $clOfficerId;
        $cl = App\ExecuteRow("SELECT status FROM {$this->tableCl} WHERE cl_id = {$clId}", 'DB');
        if ($cl && $cl['status'] === 'POSTED') {
            return $this->fail('This Confirmation Letter has already been posted and is read-only.');
        }
        App\Execute("DELETE FROM {$this->tableClOfficers} WHERE cl_id = {$clId} AND cl_officer_id = {$clOfficerId}", 'DB');

        return $this->ok();
    }

    public function postCl($id)
    {
        $clId = (int) $id;
        $cl = App\ExecuteRow("SELECT * FROM {$this->tableCl} WHERE cl_id = {$clId}", 'DB');
        if (!$cl) {
            return $this->fail('Confirmation Letter not found.');
        }
        if ($cl['status'] === 'POSTED') {
            return $this->fail('This Confirmation Letter has already been posted.');
        }
        $officers = App\ExecuteRowsAssociative(
            "SELECT * FROM {$this->tableClOfficers} WHERE cl_id = {$clId}",
            'DB'
        ) ?: [];
        if (!$officers) {
            return $this->fail('Add at least one officer to this letter before posting.');
        }
        foreach ($officers as $row) {
            if (!$row['new_risk_no'] || !$row['approved_bond_amount']) {
                return $this->fail('Every officer on the letter needs a Risk No. and an Approved Bond Amount before posting.');
            }
            // Re-check uniqueness right before posting (defends against a
            // conflicting row added by a different session since this one
            // was first encoded).
            $conflict = $this->riskNumberConflict($row['new_risk_no'], (int) $row['old_listing_id']);
            if ($conflict) {
                return $this->fail($conflict);
            }
        }

        $userId = (int) App\CurrentUserID();
        App\Execute('BEGIN', 'DB');
        try {
            foreach ($officers as $row) {
                if ($row['old_listing_id']) {
                    App\Execute(
                        "UPDATE {$this->tableListings}
                         SET status = 'CLOSED', closed_by_cl_id = {$clId}, updated_at = CURRENT_TIMESTAMP
                         WHERE listing_id = {$row['old_listing_id']}",
                        'DB'
                    );
                }
                $newListing = App\ExecuteRow(
                    "INSERT INTO {$this->tableListings}
                        (officer_id, rc_id, status, risk_no, approved_bond_amount, effectivity_date, expiration_date,
                         last_cl_id, replaces_listing_id, created_by)
                     VALUES ({$row['officer_id']}, " . $this->str($cl['rc_id']) . ", 'ACTIVE', "
                        . $this->str($row['new_risk_no']) . ", {$row['approved_bond_amount']}, "
                        . $this->str($cl['new_effectivity_date']) . ', ' . $this->str($cl['new_expiration_date']) . ", "
                        . "{$clId}, " . ($row['old_listing_id'] ?: 'NULL') . ", {$userId})
                     RETURNING listing_id",
                    'DB'
                );
                App\Execute(
                    "UPDATE {$this->tableClOfficers} SET new_listing_id = {$newListing['listing_id']} WHERE cl_officer_id = {$row['cl_officer_id']}",
                    'DB'
                );
            }
            App\Execute(
                "UPDATE {$this->tableCl} SET status = 'POSTED', posted_by = {$userId}, posted_at = CURRENT_TIMESTAMP WHERE cl_id = {$clId}",
                'DB'
            );
            App\Execute('COMMIT', 'DB');
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return $this->fail('Could not post Confirmation Letter: ' . $e->getMessage());
        }

        return $this->ok(['cl_id' => $clId, 'officers_posted' => count($officers)]);
    }

    // One row per officer listed on a posted CL (not one row per CL) — COA's
    // register is kept at risk-number granularity, so Accountable Officer
    // and Risk No. need a row each rather than being folded into a count.
    // Period (date_from/date_to) takes priority when given; "year" stays
    // supported underneath it for anything still calling with the old param.
    // Shared by getClRegister() (JSON, for the on-screen table) and
    // exportClRegisterXlsx() (the same rows, laid out as a workbook).
    private function clRegisterRows(array $params): array
    {
        $rcId = trim((string) ($params['region'] ?? ''));
        $year = (int) ($params['year'] ?? 0);
        $dateFrom = trim((string) ($params['date_from'] ?? ''));
        $dateTo = trim((string) ($params['date_to'] ?? ''));
        $where = ["cl.status = 'POSTED'"];
        if ($rcId !== '') {
            $where[] = 'cl.rc_id = ' . $this->str($rcId);
        }
        if ($dateFrom !== '' || $dateTo !== '') {
            if ($dateFrom !== '') {
                $where[] = 'cl.cl_date >= ' . $this->str($dateFrom);
            }
            if ($dateTo !== '') {
                $where[] = 'cl.cl_date <= ' . $this->str($dateTo);
            }
        } elseif ($year > 0) {
            $where[] = "EXTRACT(YEAR FROM cl.cl_date) = {$year}";
        }
        $whereSql = implode(' AND ', $where);

        return App\ExecuteRowsAssociative(
            "SELECT cl.cl_id, cl.cl_number, cl.cl_date, cl.rc_id, rc.rc_name, cl.new_effectivity_date,
                    cl.new_expiration_date, cl.posted_at,
                    clo.cl_officer_id, {$this->fullNameSql()} AS employee_name, clo.new_risk_no, clo.approved_bond_amount
             FROM {$this->tableCl} cl
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cl.rc_id
             JOIN {$this->tableClOfficers} clo ON clo.cl_id = cl.cl_id
             JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = clo.officer_id
             WHERE {$whereSql}
             ORDER BY cl.cl_date DESC, ao.last_name, ao.first_name",
            'DB'
        ) ?: [];
    }

    public function getClRegister($params)
    {
        return $this->ok($this->clRegisterRows($params));
    }

    // The register as a real workbook — a title block, a styled header row,
    // thin borders, a peso number format on the amount column, and column
    // widths sized to what's actually in them (not Excel's own fixed
    // default) — instead of the tab-separated text the on-screen "Export"
    // button used to fake a .xls out of.
    public function exportClRegisterXlsx($params): string
    {
        $rows = $this->clRegisterRows($params);
        $columns = ['CL No.', 'CL Date', 'Region', 'Accountable Officer', 'Risk No.', 'Effectivity', 'Expiration', 'Approved Amount'];

        $x = new XlsxWriter('CL Register');
        $font = static fn (array $o = []): array => array_merge(['name' => 'Arial', 'sz' => 10], $o);
        $sTitle = $x->style(['font' => $font(['sz' => 14, 'bold' => true, 'color' => '1F497D'])]);
        $sSub = $x->style(['font' => $font(['sz' => 9, 'color' => '595959'])]);
        $thStyle = $x->style(['font' => $font(['bold' => true, 'color' => 'FFFFFF']), 'fill' => '1F497D', 'border' => 'FFFFFF',
            'align' => ['h' => 'center', 'v' => 'center']]);
        $cellStyle = $x->style(['font' => $font(), 'border' => 'D9D9D9', 'align' => ['v' => 'center']]);
        $amountStyle = $x->style(['font' => $font(), 'border' => 'D9D9D9', 'align' => ['h' => 'right', 'v' => 'center'], 'numFmt' => '#,##0.00']);

        $ncol = count($columns);
        $r = 0;
        $x->set($r, 0, 'NHA Fidelity Bond — CL Register (for COA)', $sTitle);
        $x->merge($r, 0, $r, $ncol - 1);
        $x->rowHeight($r++, 20);

        $filters = [];
        if (!empty($params['region'])) {
            $rc = App\ExecuteRow('SELECT rc_name FROM ' . $this->tableRc . ' WHERE rc_id = ' . $this->str($params['region']), 'DB');
            $filters[] = 'Region: ' . ($rc['rc_name'] ?? $params['region']);
        }
        if (!empty($params['date_from']) || !empty($params['date_to'])) {
            $filters[] = 'Period: ' . ($params['date_from'] ?? '…') . ' to ' . ($params['date_to'] ?? '…');
        }
        $filters[] = 'Generated: ' . date('Y-m-d H:i');
        $x->set($r, 0, implode('   |   ', $filters), $sSub);
        $x->merge($r, 0, $r, $ncol - 1);
        $x->rowHeight($r++, 16);
        $x->rowHeight($r++, 6);   // breathing room above the table

        $widths = array_map(fn ($c) => mb_strlen($c), $columns);
        foreach ($columns as $c => $label) {
            $x->set($r, $c, $label, $thStyle);
        }
        $x->rowHeight($r++, 20);

        foreach ($rows as $row) {
            $cells = [
                $row['cl_number'], $row['cl_date'], $row['rc_name'] ?? '', $row['employee_name'],
                $row['new_risk_no'] ?? '', $row['new_effectivity_date'], $row['new_expiration_date'],
            ];
            foreach ($cells as $c => $v) {
                $x->set($r, $c, (string) $v, $cellStyle);
                $widths[$c] = max($widths[$c], mb_strlen((string) $v));
            }
            $amount = (float) $row['approved_bond_amount'];
            $x->set($r, 7, $amount, $amountStyle);
            $widths[7] = max($widths[7], mb_strlen(number_format($amount, 2)));
            $r++;
        }

        // Auto-fit: the widest cell per column plus a little breathing
        // room, clamped so one long outlier can't blow the sheet out.
        foreach ($widths as $c => $w) {
            $x->colWidth($c, max(10, min(45, $w + 3)));
        }

        return $x->render();
    }

    // ── E. Bond History ──────────────────────────────────────────────────

    public function getHistory($params)
    {
        $rcId = trim((string) ($params['region'] ?? ''));
        $search = trim((string) ($params['search'] ?? ''));
        $where = ["bl.status = 'CLOSED'"];
        if ($rcId !== '') {
            $where[] = 'bl.rc_id = ' . $this->str($rcId);
        }
        if ($search !== '') {
            $where[] = $this->fullNameSql() . ' ILIKE ' . $this->str('%' . $search . '%');
        }
        $whereSql = implode(' AND ', $where);
        $rows = App\ExecuteRowsAssociative(
            "SELECT bl.listing_id, {$this->fullNameSql()} AS employee_name, ao.position_title, bl.rc_id, rc.rc_name,
                    bl.bond_no, bl.risk_no, bl.approved_bond_amount, bl.effectivity_date, bl.expiration_date,
                    bl.closed_by_cl_id, cl.cl_number AS closed_by_cl_number
             FROM {$this->tableListings} bl
             JOIN {$this->tableOfficers} ao ON ao.accountable_officer_id = bl.officer_id
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = bl.rc_id
             LEFT JOIN {$this->tableCl} cl ON cl.cl_id = bl.closed_by_cl_id
             WHERE {$whereSql}
             ORDER BY bl.updated_at DESC",
            'DB'
        );

        return $this->ok($rows ?: []);
    }
}
