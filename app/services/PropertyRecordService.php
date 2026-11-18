<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Property Records — the user SEARCHES a PO number in the NHA Property Inventory System (PIS), PICKS items and SAVES them.
 * Only a PO that is already in the IAR module (fims.inspection_acceptance_report.po_number) can be searched or saved: any other PO is refused
 * with "…is not in the IAR module" (checked in search() AND save(); the PO may be typed with or without this system's "PO-" prefix).
 *
 *   search(po)       PIS  GET {PIS_API_BASE_URL}/inventory_by_po?page=&pageSize=&PO_NO=   Authorization: Bearer {PIS_API_TOKEN}
 *                    (both in .env, never in code or in the browser) → the PO's items with status 1-4, which of them are already saved,
 *                    and the PO's ONE responsibility center.
 *   save(po, ids)    stores only the selected items (and only the PIS fields in SAVED_KEYS) in fims.property_records — one row per PO,
 *                    the items in its line_transaction JSONB array (see 34 - fims_property_records.sql). The PIS is asked again on
 *                    save: nothing the browser sends except the PO number and the chosen inv_id values is trusted.
 *   listSaved()      what is saved, for the page's cards and table.
 *
 * Status (PIS "SERV") that can be searched / saved: 1 Issued · 2 Returned: Unserviceable · 3 Returned: Serviceable · 4 Donation
 * (5 Dropped: Disposed · 6 Dropped: Destroyed · 7 Dropped: Donation · 8 Dropped: Transfer are not offered). The PIS API itself only allows
 * the PO_NO filter, so the status is filtered here.
 *
 * ONE responsibility center per PO (fims.responsibility_code), the first that matches:
 *   1) PIS RCCODE            = responsibility_code.rc_code                 → 'RC code'
 *   2) PIS department / district text (dept_proj_desc, District, DEPTCD) = the name of a responsibility center → 'Department / District'
 *   3) the responsibility center of the PO in the IAR module (budget allocation RFA of that PO)             → 'IAR purchase order'
 * When several items point to different centers inside one step, the center most items point to wins.
 */
class PropertyRecordService
{
    private string $t = 'fims.property_records';

    /** The PIS fields that are saved — under the PIS's own names. */
    private const SAVED_KEYS = [
        'inv_id', 'TYPENO', 'GL', 'accnt_desc', 'GLCODE_SUB', 'inv_glcode_sub', 'PROPNO', 'SNO', 'ITEMDESC', 'MODELNO',
        'DATEACQD', 'COST', 'UNIT', 'SERV', 'RCCODE', 'dept_proj_desc', 'PO_NO', 'DEPTCD', 'District',
    ];

    /** PIS TYPENO of a property record (PAR). Any other type (e.g. 2) is not a property record: it is shown with a warning and cannot be saved. */
    private const PROPERTY_TYPENO = '1';

    /** SERV codes that can be searched / saved. */
    private const VISIBLE_STATUSES = ['1', '2', '3', '4'];

    private const STATUS_LABELS = [
        '1' => 'Issued',
        '2' => 'Returned: Unserviceable',
        '3' => 'Returned: Serviceable',
        '4' => 'Donation',
    ];

    /** The property categories of the register, by GL code (shown as cards even with no record). */
    private const CATEGORIES = [
        '1-06-05-020' => 'Office Equipment',
        '1-06-05-030' => 'Info. And Comm. Tech. Equip.',
        '1-06-05-070' => 'Communication Equipment',
        '1-06-05-080' => 'Construction & Heavy Equip.',
        '1-06-05-110' => 'Medical Equipment',
        '1-06-05-130' => 'Sports Equipment',
        '1-06-05-990' => 'Other Machinery & Equipment',
        '1-06-06-010' => 'Motor Vehicles',
        '1-06-07-010' => 'Furniture & Fixtures',
        '1-06-99-990' => 'Other Property, Plant & Equipment',
    ];

    /** Straight-line depreciation of a PAR unit (same convention as the accounting register): 60 months, 10% salvage; ICS never depreciates. */
    private const USEFUL_LIFE_MONTHS = 60;
    private const DEPRECIABLE_RATE   = 0.9;

    /** PIS "UNIT" is a code, not a label. */
    private const UNIT_LABELS = ['1' => 'PCS', '2' => 'UNIT', '3' => 'SET'];

    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 30;       // safety cap per PO

    /** An account row of at least this amount has items in the IAR module (same value as PoDrService::ITEM_MIN_AMOUNT). */
    private const IAR_ITEM_MIN_AMOUNT = 50000;

    /**
     * The items entered in the IAR module (fims.inspection_acceptance_report → line_transaction[].items) whose account is one of the
     * property categories (CATEGORIES, or a sub-ledger of one). One entry per item:
     *   { po_number, iar_number, dr_number, rc_id, rc_name, account_code, account_title, description, serial_no, property_no,
     *     date_acquired (the delivery date of its DR), amount (acquisition cost) }
     * The item's account is the one stored with it; an older item without one is matched to the account row of the same amount
     * (or, failing that, the account rows of 50,000 or more in order). The responsibility center is the PO's (the RFA's requesting RC).
     */
    public function iarItems(): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT i.po_number, i.iar_number, i.line_transaction, a.requesting_rc_id AS rc_id, rc.rc_name
                   FROM fims.inspection_acceptance_report i
                   LEFT JOIN LATERAL (
                        SELECT ba.requesting_rc_id FROM fims.budget_allocation ba
                         WHERE ba.po_number = i.po_number AND UPPER(COALESCE(ba.allocation_type, '')) = 'RFA' AND ba.deleted_at IS NULL
                         ORDER BY ba.created_at DESC, ba.allocation_id DESC LIMIT 1
                   ) a ON TRUE
                   LEFT JOIN fims.responsibility_code rc ON rc.rc_id = a.requesting_rc_id
                  ORDER BY i.iar_number",
                'DB'
            ) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $drs = is_string($r['line_transaction']) ? json_decode($r['line_transaction'], true) : $r['line_transaction'];
                foreach (is_array($drs) ? $drs : [] as $dr) {
                    $items   = array_values(array_filter(is_array($dr['items'] ?? null) ? $dr['items'] : [], 'is_array'));
                    $entries = array_values(array_filter(is_array($dr['lines'] ?? null) ? $dr['lines'] : [], 'is_array'));
                    if (!$items) {
                        continue;
                    }
                    $pool = [];   // the account rows that need items, not matched to an item yet
                    foreach ($entries as $en) {
                        if ((float) ($en['amount'] ?? 0) >= self::IAR_ITEM_MIN_AMOUNT) {
                            $pool[] = $en;
                        }
                    }
                    foreach ($items as $k => $it) {
                        $code  = trim((string) ($it['account_code'] ?? ''));
                        $title = '';
                        if ($code !== '') {
                            foreach ($entries as $en) {
                                if (($en['account_code'] ?? '') === $code) {
                                    $title = (string) ($en['account_title'] ?? '');
                                    break;
                                }
                            }
                        } else {
                            $cents = (int) round((float) ($it['amount'] ?? 0) * 100);
                            $hit   = null;
                            foreach ($pool as $pk => $en) {
                                if ((int) round((float) $en['amount'] * 100) === $cents) {
                                    $hit = $pk;
                                    break;
                                }
                            }
                            $hit ??= isset($pool[$k]) ? $k : null;
                            if ($hit !== null) {
                                $code  = (string) ($pool[$hit]['account_code'] ?? '');
                                $title = (string) ($pool[$hit]['account_title'] ?? '');
                                unset($pool[$hit]);
                            }
                        }
                        $gl = preg_match('/^(\d+-\d+-\d+-\d+)/', $code, $mt) ? $mt[1] : $code;
                        if ($code === '' || !isset(self::CATEGORIES[$gl])) {
                            continue;   // only the property accounts
                        }
                        $out[] = [
                            'po_number' => $r['po_number'], 'iar_number' => $r['iar_number'], 'dr_number' => (string) ($dr['dr_number'] ?? ''),
                            'rc_id' => $r['rc_id'], 'rc_name' => $r['rc_name'],
                            'account_code' => $code, 'account_title' => $title !== '' ? $title : self::CATEGORIES[$gl],
                            'description' => (string) ($it['description'] ?? ''), 'serial_no' => $it['serial_no'] ?? null, 'property_no' => $it['property_no'] ?? null,
                            'date_acquired' => $dr['delivery_date'] ?? null, 'amount' => (float) ($it['amount'] ?? 0),
                            // the JEV created for the DR's pro-forma (recorded on the DR by the IAR module's Submit)
                            'jev_id' => $dr['jev_id'] ?? null, 'jev_no' => $dr['jev_no'] ?? null, 'jev_date' => null,
                        ];
                    }
                }
            }
            // the date of each JEV (one query)
            $jevIds = array_values(array_unique(array_filter(array_map(fn($x) => (int) $x['jev_id'], $out))));
            if ($jevIds) {
                $dates = [];
                foreach (App\ExecuteRows("SELECT jev_id, TO_CHAR(jev_date, 'YYYY-MM-DD') AS jev_date FROM fims.journal_entry_vouchers WHERE jev_id IN (" . implode(',', $jevIds) . ')', 'DB') ?: [] as $j) {
                    $dates[(int) $j['jev_id']] = $j['jev_date'];
                }
                foreach ($out as &$x) {
                    $x['jev_date'] = $dates[(int) $x['jev_id']] ?? null;
                }
                unset($x);
            }
            usort($out, fn($a, $b) => strcmp((string) $b['date_acquired'], (string) $a['date_acquired']));

            return ['success' => true, 'data' => ['items' => $out]];
        } catch (\Exception $e) {
            App\LogError('PropertyRecordService::iarItems ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the items of the IAR module.'];
        }
    }

    /**
     * Submits the depreciation pro-forma as a Journal Entry Voucher — the same creation as the IAR module's pro-forma (a standalone
     * JEV, then sent for approval: DRAFT → FOR_SECTION_CHIEF). body: { jev_date, book_id, particulars, lines[] }. The same
     * particulars (= the same quarter) in the same book can only be submitted once: while that JEV exists a second one is refused.
     */
    public function submitProforma(array $body, int $userId): array
    {
        try {
            $part = trim((string) ($body['particulars'] ?? ''));
            $book = (int) ($body['book_id'] ?? 0);
            if ($part === '' || $book <= 0) {
                return ['success' => false, 'message' => 'Select a book and enter the particulars.'];
            }
            $dup = App\ExecuteRows('SELECT jev_no FROM fims.journal_entry_vouchers WHERE book_id = ' . $book . ' AND particulars = ' . $this->q($part) . ' LIMIT 1', 'DB');
            if ($dup) {
                return ['success' => false, 'message' => "This pro-forma was already submitted as Journal Entry Voucher {$dup[0]['jev_no']} (same book and particulars).", 'httpCode' => 409];
            }
            $voucher = new DisbursementVoucherService();
            $result  = $voucher->createStandaloneJev($body, $userId);
            if (!empty($result['success'])) {
                $jevId = (int) $result['data']['jev_id'];
                $sub   = $voucher->changeJevStatus($jevId, 'FOR_SECTION_CHIEF', $userId);
                if (!empty($sub['success'])) {
                    $result['data']['status'] = 'FOR_SECTION_CHIEF';
                    $result['message']        = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' created and submitted for approval.';
                } else {
                    $result['message'] = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' was created as a draft, but could not be submitted for approval: ' . ($sub['message'] ?? 'unknown error');
                }
            }

            return $result;
        } catch (\Exception $e) {
            App\LogError('PropertyRecordService::submitProforma ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to submit the pro-forma.'];
        }
    }

    /**
     * The JEV a depreciation pro-forma was already submitted as: the JEV of the book whose particulars are any of the given texts
     * (the standard sentence of the quarter, or what the user typed). Returns { jev_id, jev_no } or null when none was submitted.
     */
    public function proformaJev(int $bookId, array $particulars): ?array
    {
        $texts = array_values(array_unique(array_filter(array_map(fn($t) => trim((string) $t), $particulars), fn($t) => $t !== '')));
        if ($bookId <= 0 || !$texts) {
            return null;
        }
        $r = App\ExecuteRows('SELECT jev_id, jev_no FROM fims.journal_entry_vouchers WHERE book_id = ' . $bookId . ' AND particulars IN (' . implode(',', array_map(fn($t) => $this->q($t), $texts)) . ') ORDER BY jev_id LIMIT 1', 'DB');

        return $r ? ['jev_id' => (int) $r[0]['jev_id'], 'jev_no' => (string) $r[0]['jev_no']] : null;
    }

    /** Titles of chart-of-accounts codes (for the depreciation pro-forma JEV): code => title; a code that is not found is left out. */
    public function accountTitles(array $codes): array
    {
        try {
            $codes = array_values(array_unique(array_filter(array_map(fn($c) => trim((string) $c), $codes), fn($c) => $c !== '' && strlen($c) <= 40)));
            if (!$codes) {
                return ['success' => true, 'data' => new \stdClass()];
            }
            $titles = [];
            foreach (App\ExecuteRows('SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN (' . implode(',', array_map(fn($c) => $this->q($c), $codes)) . ')', 'DB') ?: [] as $r) {
                $titles[$r['account_code']] = $r['account_title'];
            }

            return ['success' => true, 'data' => $titles ?: new \stdClass()];
        } catch (\Exception $e) {
            App\LogError('PropertyRecordService::accountTitles ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the account titles.'];
        }
    }

    private function q($v): string
    {
        return App\QuotedValue((string) $v, App\DataType::STRING, 'DB');
    }

    private function config(): array
    {
        $base  = rtrim((string) App\Env('PIS_API_BASE_URL', 'https://apigenerator.staging.nha.gov.ph/api'), '/');
        $token = trim((string) App\Env('PIS_API_TOKEN', ''));

        return [$base, $token];
    }

    /** One PIS request. Returns [json array|null, error message|null]. */
    private function request(string $base, string $token, array $query): array
    {
        $ch = curl_init($base . '/inventory_by_po?' . http_build_query($query));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Authorization: Bearer ' . $token],
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [null, 'The Property Inventory System could not be reached' . ($err !== '' ? " ({$err})" : '') . '.'];
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            return [null, "The Property Inventory System returned an unreadable response (HTTP {$http})."];
        }
        if ($http === 401 || $http === 403) {
            return [null, 'The Property Inventory System refused the access token. Check PIS_API_TOKEN in .env.'];
        }
        if (empty($json['success'])) {
            return [null, (string) ($json['message'] ?? "The Property Inventory System returned an error (HTTP {$http}).")];
        }

        return [$json, null];
    }

    /** Every PIS row of one PO number (all pages). Returns [rows[], error|null]. */
    private function fetchAllRows(string $base, string $token, string $po): array
    {
        $rows = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            [$json, $err] = $this->request($base, $token, ['page' => $page, 'pageSize' => self::PAGE_SIZE, 'PO_NO' => $po]);
            if ($err !== null) {
                return [[], $err];
            }
            $data  = is_array($json['data'] ?? null) ? $json['data'] : [];
            $total = (int) ($json['pagination']['total'] ?? count($data));
            $rows  = array_merge($rows, $data);
            if (!$data || count($rows) >= $total) {
                break;
            }
        }

        return [$rows, null];
    }

    /**
     * The PIS rows of a PO number as typed by the user. PIS PO numbers never carry this system's own "PO-" prefix, so a PO that finds
     * nothing is retried without it. Returns [rows[], poNumberUsedInPIS, error|null].
     */
    private function fetchPo(string $po): array
    {
        [$base, $token] = $this->config();
        if ($token === '') {
            return [[], $po, 'The Property Inventory System access token is not set (PIS_API_TOKEN in .env).'];
        }
        [$rows, $err] = $this->fetchAllRows($base, $token, $po);
        if ($err !== null) {
            return [[], $po, $err];
        }
        $used = $po;
        if (!$rows && preg_match('/^PO-/i', $po)) {
            $used = (string) preg_replace('/^PO-/i', '', $po);
            [$rows, $err] = $this->fetchAllRows($base, $token, $used);
            if ($err !== null) {
                return [[], $used, $err];
            }
        }

        return [$rows, $used, null];
    }

    /** The IAR of a PO number (typed with or without the "PO-" prefix, any case), or null when the PO is not in the IAR module. */
    private function iarFor(string $po): ?array
    {
        $p     = strtoupper(trim($po));
        $forms = array_values(array_unique([$p, 'PO-' . $p, (string) preg_replace('/^PO-/', '', $p)]));
        $in    = implode(',', array_map(fn($x) => $this->q($x), $forms));
        $r     = App\ExecuteRows(
            "SELECT iar_number, po_number FROM fims.inspection_acceptance_report WHERE UPPER(po_number) IN ({$in}) ORDER BY iar_id DESC LIMIT 1",
            'DB'
        );

        return $r ? ['iar_number' => (string) $r[0]['iar_number'], 'po_number' => (string) $r[0]['po_number']] : null;
    }

    private function notInIar(string $po): array
    {
        return ['success' => false, 'message' => "PO '{$po}' is not in the IAR module. Only a PO that already has an IAR can be searched — add its IAR in the IAR module first."];
    }

    private function text($v): string
    {
        return trim((string) ($v ?? ''));
    }

    /** A PIS row reduced to SAVED_KEYS (what is stored). */
    private function keep(array $r): array
    {
        $out = [];
        foreach (self::SAVED_KEYS as $k) {
            $v = $r[$k] ?? null;
            if ($k === 'inv_id' || $k === 'GLCODE_SUB') {
                $out[$k] = $v === null || $v === '' ? null : (int) $v;
            } elseif ($k === 'COST') {
                $out[$k] = (float) ($v ?? 0);
            } else {
                $out[$k] = $this->text($v);
            }
        }

        return $out;
    }

    /** A stored / PIS item (SAVED_KEYS) → what the page shows. */
    private function display(array $k, bool $saved = false): array
    {
        $serv = (string) ($k['SERV'] ?? '');
        $unit = (string) ($k['UNIT'] ?? '');

        return [
            'inv_id'        => $k['inv_id'] ?? null,
            'saved'         => $saved,
            'property_no'   => $this->text($k['PROPNO'] ?? ''),
            'description'   => $this->text($k['ITEMDESC'] ?? ''),
            'model_no'      => $this->text($k['MODELNO'] ?? ''),
            'serial_no'     => $this->text($k['SNO'] ?? ''),
            'type_no'       => $this->text($k['TYPENO'] ?? ''),
            'is_property'   => $this->text($k['TYPENO'] ?? '') === self::PROPERTY_TYPENO,
            'gl_code'       => $this->text($k['GL'] ?? ''),
            'category'      => $this->text($k['accnt_desc'] ?? ''),
            'gl_code_sub'   => $k['GLCODE_SUB'] ?? null,
            'sub_category'  => $this->text($k['inv_glcode_sub'] ?? ''),
            'unit'          => self::UNIT_LABELS[$unit] ?? $unit,
            'cost'          => (float) ($k['COST'] ?? 0),
            'date_acquired' => $this->text($k['DATEACQD'] ?? ''),
            'status'        => $serv,
            'status_label'  => self::STATUS_LABELS[$serv] ?? $serv,
            'rc_code'       => $this->text($k['RCCODE'] ?? ''),
            'location'      => $this->text($k['dept_proj_desc'] ?? ''),
            'department'    => $this->text($k['DEPTCD'] ?? ''),
            'district'      => $this->text($k['District'] ?? ''),
            'po_no'         => $this->text($k['PO_NO'] ?? ''),
        ];
    }

    /** Numbers for the cards: totals, per status (1-4) and per category (GL code). */
    private function summarize(array $items): array
    {
        $byStatus = [];
        foreach (self::STATUS_LABELS as $code => $label) {
            $byStatus[$code] = ['status' => (string) $code, 'label' => $label, 'count' => 0, 'total_cost' => 0.0];
        }
        $byCategory = [];
        foreach (self::CATEGORIES as $gl => $name) {
            $byCategory[$gl] = ['gl_code' => $gl, 'category' => $name, 'count' => 0, 'total_cost' => 0.0];
        }
        $totalCost  = 0.0;
        foreach ($items as $it) {
            $totalCost += $it['cost'];
            if (isset($byStatus[$it['status']])) {
                $byStatus[$it['status']]['count']++;
                $byStatus[$it['status']]['total_cost'] += $it['cost'];
            }
            $key = $it['gl_code'] !== '' ? $it['gl_code'] : '—';
            $byCategory[$key] ??= ['gl_code' => $it['gl_code'], 'category' => $it['category'], 'count' => 0, 'total_cost' => 0.0];
            if ($byCategory[$key]['category'] === '') { $byCategory[$key]['category'] = $it['category']; }
            $byCategory[$key]['count']++;
            $byCategory[$key]['total_cost'] += $it['cost'];
        }
        $round = function ($s) {
            $s['total_cost'] = round($s['total_cost'], 2);

            return $s;
        };

        return [
            'total_items' => count($items),
            'total_cost'  => round($totalCost, 2),
            'by_status'   => array_values(array_map($round, $byStatus)),
            'by_category' => array_values(array_map($round, $byCategory)),
        ];
    }

    // ───────────────────────── responsibility center ─────────────────────────

    /** "Region 10 - Bukidnon | Misamis" and "region 10 - bukidnon/misamis" compare equal: lower case, one space, "|" = "/", no space around "/". */
    private function norm($s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = str_replace('|', '/', $s);
        $s = (string) preg_replace('/\s*\/\s*/', '/', $s);

        return (string) preg_replace('/\s+/', ' ', $s);
    }

    /** All responsibility centers: [ {rc_id, rc_code, rc_name} ]. */
    private function rcRows(): array
    {
        return App\ExecuteRows("SELECT rc_id, rc_code, rc_name FROM fims.responsibility_code ORDER BY rc_code", 'DB') ?: [];
    }

    /** The center most often chosen in $votes (rc_id => count), the first one on a tie. */
    private function top(array $votes, array $byId): ?array
    {
        if (!$votes) {
            return null;
        }
        arsort($votes);

        return $byId[array_key_first($votes)] ?? null;
    }

    /**
     * Steps 1 and 2 of the responsibility-center search, on the PIS rows only (no database): returns [rc|null, 'RC code'|'Department / District'|null].
     * $rcs = [ {rc_id, rc_code, rc_name} ].
     */
    private function matchRc(array $rows, array $rcs): array
    {
        $byId = $byCode = $byName = [];
        foreach ($rcs as $rc) {
            $byId[(string) $rc['rc_id']] = $rc;
            $byCode[trim((string) $rc['rc_code'])] = $rc;
            $byName[$this->norm($rc['rc_name'])] = $rc;
        }

        // 1) PIS RCCODE = rc_code
        $votes = [];
        foreach ($rows as $r) {
            $code = trim((string) ($r['RCCODE'] ?? ''));
            if ($code !== '' && isset($byCode[$code])) {
                $id = (string) $byCode[$code]['rc_id'];
                $votes[$id] = ($votes[$id] ?? 0) + 1;
            }
        }
        if ($rc = $this->top($votes, $byId)) {
            return [$rc, 'RC code'];
        }

        // 2) PIS department / district text = a center's name (exact, then a long text contained in exactly one center's name)
        $votes = [];
        foreach ($rows as $r) {
            $hit = null;
            foreach ([$r['dept_proj_desc'] ?? '', $r['District'] ?? '', $r['DEPTCD'] ?? ''] as $cand) {
                $n = $this->norm($cand);
                if ($n === '') {
                    continue;
                }
                if (isset($byName[$n])) {
                    $hit = $byName[$n];
                    break;
                }
                if (mb_strlen($n) >= 12) {                       // short codes (e.g. "BML") only ever match a name exactly
                    $contains = array_values(array_filter($rcs, fn($rc) => str_contains($this->norm($rc['rc_name']), $n)));
                    if (count($contains) === 1) {
                        $hit = $contains[0];
                        break;
                    }
                }
            }
            if ($hit) {
                $id = (string) $hit['rc_id'];
                $votes[$id] = ($votes[$id] ?? 0) + 1;
            }
        }
        if ($rc = $this->top($votes, $byId)) {
            return [$rc, 'Department / District'];
        }

        return [null, null];
    }

    /** Step 3: the responsibility center of the PO in the IAR module (the RFA's requesting office). */
    private function iarRc(string $po, array $rcs, ?string $iarPo = null): ?array
    {
        $forms = array_values(array_unique(array_filter([$po, 'PO-' . $po, (string) preg_replace('/^PO-/i', '', $po), $iarPo])));
        $in    = implode(',', array_map(fn($p) => $this->q($p), $forms));
        $row   = App\ExecuteRows(
            "SELECT requesting_rc_id FROM fims.budget_allocation
             WHERE UPPER(COALESCE(allocation_type, '')) = 'RFA' AND deleted_at IS NULL AND po_number IN ({$in}) AND requesting_rc_id IS NOT NULL
             ORDER BY created_at DESC LIMIT 1",
            'DB'
        );
        if (!$row) {
            return null;
        }
        foreach ($rcs as $rc) {
            if ((string) $rc['rc_id'] === (string) $row[0]['requesting_rc_id']) {
                return $rc;
            }
        }

        return null;
    }

    /** The PO's ONE responsibility center: {rc_id, rc_code, rc_name} or null. The rc_id (fims.responsibility_code.rc_id) is what is saved. */
    private function resolveRc(array $rows, string $po, ?string $iarPo = null): ?array
    {
        $rcs = $this->rcRows();
        [$rc] = $this->matchRc($rows, $rcs);
        if (!$rc) {
            $rc = $this->iarRc($po, $rcs, $iarPo);
        }

        return $rc ? ['rc_id' => (string) $rc['rc_id'], 'rc_code' => (string) $rc['rc_code'], 'rc_name' => (string) $rc['rc_name']] : null;
    }

    // ───────────────────────── saved items ─────────────────────────

    /** Which of these PIS inv_id values are already saved (in any PO row)? Returns a set: inv_id => true. */
    private function savedInvIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in   = implode(',', array_map(fn($i) => $this->q((string) $i), $ids));
        $rows = App\ExecuteRows(
            "SELECT DISTINCT it ->> 'inv_id' AS inv_id FROM {$this->t} p, jsonb_array_elements(p.line_transaction) AS it WHERE it ->> 'inv_id' IN ({$in})",
            'DB'
        ) ?: [];

        return array_fill_keys(array_map(fn($r) => (int) $r['inv_id'], $rows), true);
    }

    private function itemsOf($json): array
    {
        $a = is_string($json) ? json_decode($json, true) : $json;

        return is_array($a) ? array_values(array_filter($a, 'is_array')) : [];
    }

    // ───────────────────────── public: search / save / list ─────────────────────────

    /** GET /property-record/search?po_number= — the PO's items from PIS (status 1-4), what is already saved, and the PO's one responsibility center. */
    public function search(string $poNumber): array
    {
        try {
            $po = trim($poNumber);
            if ($po === '') {
                return ['success' => false, 'message' => 'Enter a PO number to search.'];
            }
            if (mb_strlen($po) > 100) {
                return ['success' => false, 'message' => 'The PO number must be 100 characters or fewer.'];
            }
            $iar = $this->iarFor($po);                      // the PO must already be in the IAR module
            if (!$iar) {
                return $this->notInIar($po);
            }
            [$rows, $used, $err] = $this->fetchPo($po);
            if ($err !== null) {
                return ['success' => false, 'message' => $err];
            }

            $kept  = array_map(fn($r) => $this->keep($r), $rows);
            // only PAR (TYPENO 1) is a property record: the other items (ICS) are not shown at all
            $kept  = array_values(array_filter($kept, fn($k) => $this->text($k['TYPENO'] ?? '') === self::PROPERTY_TYPENO));
            $shown = array_values(array_filter($kept, fn($k) => in_array((string) $k['SERV'], self::VISIBLE_STATUSES, true)));
            $saved = $this->savedInvIds(array_column($shown, 'inv_id'));
            $items = array_map(fn($k) => $this->display($k, isset($saved[(int) $k['inv_id']])), $shown);
            usort($items, fn($a, $b) => strcmp($a['gl_code'] . $a['property_no'], $b['gl_code'] . $b['property_no']));

            // the PO's responsibility center: the one already saved for this PO, else resolved from PIS / the IAR module
            $existing = App\ExecuteRows(
                "SELECT p.rc_id, rc.rc_code, rc.rc_name FROM {$this->t} p LEFT JOIN fims.responsibility_code rc ON rc.rc_id = p.rc_id WHERE p.po_number = " . $this->q($used),
                'DB'
            );
            if ($existing && $existing[0]['rc_id'] !== null) {
                $rc = ['rc_id' => (string) $existing[0]['rc_id'], 'rc_code' => (string) $existing[0]['rc_code'], 'rc_name' => (string) $existing[0]['rc_name']];
            } else {
                $rc = $this->resolveRc($rows, $used, $iar['po_number']);
            }

            return ['success' => true, 'data' => [
                'po_number'        => $used,
                'iar_number'       => $iar['iar_number'],
                'iar_po'           => $iar['po_number'],
                'items'            => $items,
                'hidden_by_status' => count($kept) - count($shown),         // PIS rows of this PO with status 5-8, not offered
                'saved_count'      => count(array_filter($items, fn($i) => $i['saved'])),
                'rc'               => $rc,
                'summary'          => $this->summarize(array_values(array_filter($items, fn($i) => $i['is_property']))),
            ]];
        } catch (\Throwable $e) {
            App\LogError('PropertyRecordService::search - ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not search the property records: ' . $e->getMessage()];
        }
    }

    /** POST /property-record/save {po_number, inv_ids[]} — saves ONLY the selected items of that PO (PIS is read again). */
    public function save(string $poNumber, array $invIds, ?int $userId = null): array
    {
        try {
            $po  = trim($poNumber);
            $ids = array_values(array_unique(array_filter(array_map('intval', $invIds), fn($i) => $i > 0)));
            if ($po === '') {
                return ['success' => false, 'message' => 'The PO number is required.'];
            }
            if (!$ids) {
                return ['success' => false, 'message' => 'Select at least one item to save.'];
            }
            $iar = $this->iarFor($po);                      // the PO must already be in the IAR module
            if (!$iar) {
                return $this->notInIar($po);
            }
            [$rows, $used, $err] = $this->fetchPo($po);
            if ($err !== null) {
                return ['success' => false, 'message' => $err];
            }

            // only items that really belong to this PO in PIS and have a status that can be saved
            $kept = array_map(fn($r) => $this->keep($r), $rows);
            $pick = array_values(array_filter(
                $kept,
                fn($k) => in_array((int) $k['inv_id'], $ids, true) && in_array((string) $k['SERV'], self::VISIBLE_STATUSES, true)
            ));
            if (!$pick) {
                return ['success' => false, 'message' => 'None of the selected items was found in the Property Inventory System for this PO (or its status cannot be saved).'];
            }
            $notProp = array_filter($pick, fn($k) => $this->text($k['TYPENO'] ?? '') !== self::PROPERTY_TYPENO);
            if ($notProp) {
                return ['success' => false, 'message' => 'This is not a property record: only items with TYPENO 1 (PAR) can be saved, but ' . count($notProp) . ' of the selected item' . (count($notProp) === 1 ? ' is' : 's are') . ' TYPENO ' . $this->text(reset($notProp)['TYPENO'] ?? '') . '.'];
            }
            $already = $this->savedInvIds(array_column($pick, 'inv_id'));
            $new     = array_values(array_filter($pick, fn($k) => !isset($already[(int) $k['inv_id']])));
            if (!$new) {
                return ['success' => false, 'message' => 'The selected item' . (count($pick) === 1 ? ' is' : 's are') . ' already saved.'];
            }
            $rc   = $this->resolveRc($rows, $used, $iar['po_number']);
            $by   = $userId ? (int) $userId : 'NULL';
            $json = fn(array $items) => $this->q(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '::jsonb';

            App\Execute('BEGIN', 'DB');
            try {
                $cur = App\ExecuteRows("SELECT rc_id, line_transaction FROM {$this->t} WHERE po_number = " . $this->q($used) . ' FOR UPDATE', 'DB');
                if ($cur) {
                    $items = array_merge($this->itemsOf($cur[0]['line_transaction']), $new);
                    $set   = ['line_transaction = ' . $json($items)];
                    if ($cur[0]['rc_id'] === null && $rc) {            // the PO keeps the center it already has; only an empty one is filled
                        $set[] = 'rc_id = ' . $this->q($rc['rc_id']);
                    }
                    App\Execute("UPDATE {$this->t} SET " . implode(', ', $set) . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE po_number = " . $this->q($used), 'DB');
                } else {
                    App\Execute(
                        "INSERT INTO {$this->t} (po_number, rc_id, line_transaction, created_by) VALUES ("
                            . $this->q($used) . ', ' . ($rc ? $this->q($rc['rc_id']) : 'NULL') . ', ' . $json($new) . ", {$by})",
                        'DB'
                    );
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            $skipped = count($pick) - count($new);

            return ['success' => true, 'message' => 'Saved ' . count($new) . ' item' . (count($new) === 1 ? '' : 's') . " of PO {$used} to the property records"
                . ($rc ? " ({$rc['rc_name']})" : ' (no responsibility center could be matched)') . ($skipped ? "; {$skipped} already saved" : '') . '.',
                'data' => ['saved' => count($new), 'skipped' => $skipped, 'rc' => $rc]];
        } catch (\Throwable $e) {
            App\LogError('PropertyRecordService::save - ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not save the property records: ' . $e->getMessage()];
        }
    }

    /** GET /property-record/list — every saved item (one row of items per PO), with its PO's responsibility center, and the numbers for the cards. */
    public function listSaved(): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT p.property_record_id, p.po_number, p.line_transaction, rc.rc_code, rc.rc_name, iar.iar_number
                 FROM {$this->t} p
                 LEFT JOIN fims.responsibility_code rc ON rc.rc_id = p.rc_id
                 LEFT JOIN LATERAL (SELECT i.iar_number FROM fims.inspection_acceptance_report i
                                     WHERE UPPER(i.po_number) IN (UPPER(p.po_number), 'PO-' || UPPER(p.po_number)) ORDER BY i.iar_id DESC LIMIT 1) iar ON TRUE
                 ORDER BY p.property_record_id DESC",
                'DB'
            ) ?: [];
            $items = [];
            foreach ($rows as $r) {
                foreach ($this->itemsOf($r['line_transaction']) as $k) {
                    $items[] = $this->display($k, true) + [
                        'saved_po'      => $r['po_number'],
                        'iar_number'    => (string) ($r['iar_number'] ?? ''),
                        'rc_name'       => (string) ($r['rc_name'] ?? ''),
                        'rc_center_code' => (string) ($r['rc_code'] ?? ''),
                    ];
                }
            }

            $grouped = $this->groupRows($items);

            return ['success' => true, 'data' => ['items' => $grouped, 'po_count' => count($rows), 'summary' => $this->summarizeRows($grouped)]];
        } catch (\Throwable $e) {
            App\LogError('PropertyRecordService::listSaved - ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not load the property records: ' . $e->getMessage()];
        }
    }

    // ───────────────────────── register rows (ICS combined, PAR one per unit) ─────────────────────────

    private function normText($s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtoupper((string) $s)));
    }

    /** For comparing: punctuation, spacing and case are ignored ("FLASH DRIVE, 32GB" = "FLASH DRIVE 32GB"); "N/A" and "0" mean blank. */
    private function keyText($s): string
    {
        $t = trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper((string) $s)));

        return in_array($t, ['N A', 'NA', '0', 'NONE'], true) ? '' : $t;
    }

    /** Months of depreciation up to $to (default today): it starts the month AFTER the acquisition date (acquired Jan 1 → first month is February). */
    private function monthsSince(string $date, string $to = 'today'): int
    {
        $d = substr($date, 0, 10);
        if ($d === '' || !strtotime($d)) {
            return 0;
        }
        $a = new \DateTime($d);
        $b = new \DateTime($to);

        return max(0, ((int) $b->format('Y') - (int) $a->format('Y')) * 12 + ((int) $b->format('n') - (int) $a->format('n')));
    }

    /** Monthly / accumulated depreciation and book value of ONE unit. ICS: none. */
    private function depreciation(float $cost, string $date, bool $par): array
    {
        if (!$par || $cost <= 0) {
            return ['monthly' => 0.0, 'accum' => 0.0, 'begin' => 0.0, 'book' => round($cost, 2)];
        }
        $base    = round($cost * self::DEPRECIABLE_RATE, 2);
        $monthly = round($base / self::USEFUL_LIFE_MONTHS, 2);
        $accum   = min(round($monthly * $this->monthsSince($date), 2), $base);
        $begin   = min(round($monthly * $this->monthsSince($date, (date('Y') - 1) . '-12-31'), 2), $base);   // accumulated at January 1 of this year

        return ['monthly' => $monthly, 'accum' => $accum, 'begin' => $begin, 'book' => round($cost - $accum, 2)];
    }

    /**
     * The saved units (one per PIS item) → the register rows. Units of the SAME PO + category (GL) + sub category + item name are ONE row, whatever their
     * model, serial, property number, department, cost or status: the quantity is added, property numbers / departments are stacked, serials and models listed.
     *   Unit Cost        = the cost of one unit (the average when the units differ)
     *   Acquisition Cost (a) = the units' costs added = quantity x unit cost
     *   Additional Cost  (b) = 0 (nothing in PIS carries it)
     *   Total book balance (c) = a + b;  Estimated useful life 5 years;  Salvage / residual value = 10% of c
     * Every saved item is a PAR (PIS TYPENO 1) and depreciates (straight line, from its own acquisition date).
     * The description reads "{qty} {unit}s {ITEMDESC}; {serial}, {model} (PAR No. {property numbers})".
     */
    private function groupRows(array $units): array
    {
        $rows = [];
        foreach ($units as $u) {
            $dep = $this->depreciation($u['cost'], $u['date_acquired'], true);
            $key = implode('|', [$this->normText($u['saved_po']), $u['gl_code'], $this->keyText($u['sub_category']), $this->keyText($u['description'])]);
            if (!isset($rows[$key])) {
                $rows[$key] = $u + ['row_type' => 'PAR', 'par_no' => null, 'qty' => 0, 'units' => []];
            }
            $rows[$key]['qty']++;
            $rows[$key]['units'][] = [
                'inv_id'       => $u['inv_id'],
                'property_no'  => $u['property_no'],
                'department'   => $u['department'],
                'serial_no'    => $u['serial_no'],
                'model_no'     => $u['model_no'],
                'cost'         => $u['cost'],
                'status'       => $u['status'],
                'status_label' => $u['status_label'],
                'accum_depr'   => $dep['accum'],
                'accum_begin'  => $dep['begin'],
                'monthly_depr' => $dep['monthly'],
                'book_value'   => $dep['book'],
            ];
        }

        $out = [];
        foreach ($rows as $r) {
            $clean   = fn($v) => ($v === '' || $v === 'N/A' || $v === '0') ? '' : $v;
            $serials = array_values(array_unique(array_filter(array_map(fn($x) => $clean($x['serial_no']), $r['units']))));
            $models  = array_values(array_unique(array_filter(array_map(fn($x) => $clean($x['model_no']), $r['units']))));
            $depts   = array_values(array_unique(array_filter(array_map(fn($x) => $x['department'], $r['units']))));
            $props   = array_values(array_filter(array_map(fn($x) => $x['property_no'], $r['units'])));
            $unit    = strtolower($r['unit'] !== '' ? $r['unit'] : 'unit');
            $specs   = implode(', ', array_filter([implode(' / ', $serials), implode(' / ', $models)]));
            $r['description_full'] = $r['qty'] . ' ' . $unit . ($r['qty'] === 1 || substr($unit, -1) === 's' ? '' : 's') . ' ' . $r['description']
                . ($specs !== '' ? '; ' . $specs : '') . ($props ? ' (PAR No. ' . implode(', ', $props) . ')' : '');
            $r['departments']  = $depts;
            $r['department']   = implode(', ', $depts);
            $r['property_nos'] = $props;
            $r['property_no']  = implode(', ', $props);
            $r['statuses']     = array_values(array_unique(array_column($r['units'], 'status')));
            $r['acq_cost']     = round(array_sum(array_column($r['units'], 'cost')), 2);          // a = quantity x unit cost
            $r['cost']         = round($r['acq_cost'] / $r['qty'], 2);                             // unit cost
            $r['add_cost']     = 0.0;                                                              // b
            $r['total_cost']   = round($r['acq_cost'] + $r['add_cost'], 2);                        // c = a + b
            $r['life_years']   = self::USEFUL_LIFE_MONTHS / 12;
            $r['salvage']      = round($r['total_cost'] * (1 - self::DEPRECIABLE_RATE), 2);
            $r['accum_depr']   = round(array_sum(array_column($r['units'], 'accum_depr')), 2);
            $r['accum_begin']  = round(array_sum(array_column($r['units'], 'accum_begin')), 2);
            $r['depr_year']    = round(max(0, $r['accum_depr'] - $r['accum_begin']), 2);
            $r['monthly_depr'] = round(array_sum(array_column($r['units'], 'monthly_depr')), 2);
            $r['book_value']   = round(array_sum(array_column($r['units'], 'book_value')), 2);
            $r['remarks']      = $r['book_value'] <= $r['salvage'] + 0.01 ? 'Fully Depreciated' : '';
            $out[] = $r;
        }

        return $out;
    }

    /** Numbers for the category cards of the list: book value, accumulated depreciation and number of register rows per category. */
    private function summarizeRows(array $rows): array
    {
        $byCategory = [];
        foreach (self::CATEGORIES as $gl => $name) {
            $byCategory[$gl] = ['gl_code' => $gl, 'category' => $name, 'count' => 0, 'total_cost' => 0.0, 'total_book' => 0.0, 'total_depr' => 0.0];
        }
        $units = 0;
        foreach ($rows as $r) {
            $units += $r['qty'];
            $key = $r['gl_code'] !== '' ? $r['gl_code'] : '—';
            $byCategory[$key] ??= ['gl_code' => $r['gl_code'], 'category' => $r['category'], 'count' => 0, 'total_cost' => 0.0, 'total_book' => 0.0, 'total_depr' => 0.0];
            $byCategory[$key]['count']++;
            $byCategory[$key]['total_cost'] += $r['total_cost'];
            $byCategory[$key]['total_book'] += $r['book_value'];
            $byCategory[$key]['total_depr'] += $r['accum_depr'];
        }
        $round = function ($s) {
            foreach (['total_cost', 'total_book', 'total_depr'] as $k) {
                $s[$k] = round($s[$k], 2);
            }

            return $s;
        };

        return ['total_items' => $units, 'by_category' => array_values(array_map($round, $byCategory))];
    }
}
