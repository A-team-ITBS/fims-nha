<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * The print payload of a SAVED record — what the memo request form's Print Preview (printCBA() in
 * pages/form/budget-allocation-form.php) builds from its fields, built here from the saved record instead, so a list can
 * print a request or a CAF without opening a form. The payload goes to the shared print preview:
 *     prp_open(payload, { type })      (pages/readone/print-preview-readone.php -> POST /print/{doc})
 * Field names: system_docs/modules/PRINT-FORMS.md (CbaPrintService, RfaPrintService, LaaPrintService, CafPrintService).
 *
 *   forAllocation($id)  a memo request: CBA -> CBA, RFA -> RFA, LAA -> LAA (the allocation_type picks the form)
 *   forCaf($id)         a CAF (fims.caf): the CAF form, from the CAF and the RFA it certifies
 *
 * Both answer { success, doc, payload } — `doc` is the PrintService::DOCS key to print with — or { success: false, message }.
 * The mapping itself is in the two static builders (allocationPayload / cafPayload), which touch no database.
 *
 * What is NOT in a payload: the signatories (the FMD Department Manager is filled in by the print service when blank), the
 * letterhead (the preview loads it) and, for a request that has not been approved yet, its allocation number — that is only
 * given by the final approval, so it prints blank (the memo form shows a provisional number only while it is being filled in).
 */
class PrintPayloadService
{
    /** allocation_type -> the PrintService::DOCS key of its form. The list's tabs: CBA / RFA / LAA. */
    private const ALLOCATION_DOCS = ['CBA' => 'CBA', 'RFA' => 'RFA', 'LAA' => 'LAA'];

    /**
     * The memo request forms (the PrintService::DOCS keys of ALLOCATION_DOCS) and the CAF are for FMD and administrators to
     * print. routes/print.php refuses POST /print/{doc} for these to anyone else (403, PRINT_DENIED); so do forAllocation() and
     * forCaf() below — the preview, the PDF and the DOCX all come through those, so none of them can be reached.
     */
    public const FMD_ONLY_DOCS = ['CBA', 'RFA', 'LAA', 'CAF'];

    /** The answer (403) for anyone who may not print a memo request form or a CAF. */
    public const PRINT_DENIED = 'Only the Financial Management Department or an administrator can print the CBA, RFA, LAA or CAF.';

    // -----------------------------------------------------------------------
    // A memo request — GET /print/payload/allocation/{id}
    // $userId = the logged-in user (the route's JWT): a memo request's form is only handed to an FMD user or a superadmin
    // (GlobalService::canPrintAllocationForm), so a missing user id is refused too. Every allocation type that has a form is
    // gated, so a form added to ALLOCATION_DOCS later is restricted by default.
    // -----------------------------------------------------------------------
    public function forAllocation(int|string $id, ?int $userId = null): array
    {
        try {
            $res = (new BudgetAllocationService())->fetchById($id);
            if (!$res['success']) {
                return $res + ['httpCode' => 404];
            }
            $a = $res['data'];
            $type = strtoupper(trim((string) ($a['allocation_type'] ?? '')));
            if (!isset(self::ALLOCATION_DOCS[$type])) {
                return ['success' => false, 'httpCode' => 400, 'message' => ($type !== '' ? "A {$type} request" : 'This request') . ' has no printed form.'];
            }
            if (!GlobalService::canPrintAllocationForm($userId)) {
                return ['success' => false, 'httpCode' => 403, 'message' => self::PRINT_DENIED];
            }

            return [
                'success' => true,
                'doc' => self::ALLOCATION_DOCS[$type],
                'payload' => self::allocationPayload($a, $this->categoryName($a['category_id'] ?? null)),
            ];
        } catch (\Throwable $e) {
            App\LogError('PrintPayloadService::forAllocation ' . $e->getMessage());

            return ['success' => false, 'httpCode' => 500, 'message' => 'Failed to prepare the print.'];
        }
    }

    // -----------------------------------------------------------------------
    // A CAF — GET /print/payload/caf/{id}
    // $userId = the logged-in user (the route's JWT): a CAF is only handed to an FMD user or a superadmin
    // (GlobalService::canPrintAllocationForm), so a missing user id is refused too — before anything is read.
    // -----------------------------------------------------------------------
    public function forCaf(int|string $id, ?int $userId = null): array
    {
        try {
            if (!GlobalService::canPrintAllocationForm($userId)) {
                return ['success' => false, 'httpCode' => 403, 'message' => self::PRINT_DENIED];
            }

            $res = (new CafService())->fetchById($id);
            if (!$res['success']) {
                return $res + ['httpCode' => 404];
            }
            $caf = $res['data'];

            // The RFA it certifies gives the purpose, the payee and the requesting office. A CAF whose RFA is gone (or that
            // was never linked to one) still prints, from its own lines.
            $rfa = [];
            if ((int) ($caf['allocation_id'] ?? 0) > 0) {
                $r = (new BudgetAllocationService())->fetchById((int) $caf['allocation_id']);
                $rfa = $r['success'] ? $r['data'] : [];
            }

            return ['success' => true, 'doc' => 'CAF', 'payload' => self::cafPayload($caf, $rfa, $this->vendorAddress($rfa['vendor_id'] ?? null))];
        } catch (\Throwable $e) {
            App\LogError('PrintPayloadService::forCaf ' . $e->getMessage());

            return ['success' => false, 'httpCode' => 500, 'message' => 'Failed to prepare the print.'];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // The mappings (no database)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * fims.responsibility_code.office_type -> the department type the print forms switch on (the memo form's
     * cbaRcDeptType()): Regional -> REGIONAL, District -> DISTRICT, any other office type -> MAIN, none -> ''.
     */
    public static function deptType(?string $officeType): string
    {
        $t = trim((string) $officeType);
        if ($t === 'Regional') {
            return 'REGIONAL';
        }
        if ($t === 'District') {
            return 'DISTRICT';
        }

        return $t !== '' ? 'MAIN' : '';
    }

    /**
     * A saved memo request (BudgetAllocationService::fetchById row) -> its print payload, field for field what printCBA()
     * sends for the same request. $categoryName = its Budget Allocation Category ("Goods and Services"), '' when none.
     */
    public static function allocationPayload(array $a, string $categoryName = ''): array
    {
        $type = strtoupper(trim((string) ($a['allocation_type'] ?? 'CBA')));
        $dept = self::deptType($a['requesting_rc_office_type'] ?? '');
        $regional = in_array($dept, ['REGIONAL', 'DISTRICT'], true);
        // A request can now cover several project blocks (BudgetAllocationService::fetchById()'s `projects`) —
        // the amount totals below are summed across ALL of them, so they stay correct even though the
        // program/sub-program/project fields further down (program_code, project_name, ...) only ever show the
        // FIRST block (mirrored onto $a by BudgetAllocationService::attachProjects()). Printing every block's own
        // project label is a later fast-follow.
        $lines = self::linesOfAllocation($a);

        $requested = array_sum(array_map(fn ($l) => self::num($l['requested_amount'] ?? 0), $lines));
        if ($requested <= 0) {
            $requested = self::num($a['requested_total'] ?? 0);   // an old record without lines: its flat total
        }
        $allocated = array_sum(array_map(fn ($l) => self::num($l['allocated_amount'] ?? 0), $lines));
        $requestedStr = $requested > 0 ? self::money($requested) : '';
        $allocatedStr = $allocated > 0 ? self::money($allocated) : '';

        $office = trim((string) ($a['requesting_rc_name'] ?? ''));
        $year = (string) ($a['fiscal_year'] ?? '');
        $description = (string) ($a['description'] ?? '');

        $payload = [
            'allocation_number' => (string) ($a['allocation_number'] ?? ''),
            'allocation_type' => $type,
            'fiscal_year' => $year,
            'fiscal_year_label' => $year,
            'document_type' => (string) ($a['document_type'] ?? ''),
            'category_name' => $categoryName,
            'program_code' => (string) ($a['program_code'] ?? ''),
            'program_name' => (string) ($a['program_name'] ?? ''),
            'sub_program_code' => (string) ($a['sub_program_code'] ?? ''),
            'sub_program_name' => (string) ($a['sub_program_name'] ?? ''),
            'project_code' => (string) ($a['project_code'] ?? ''),
            'project_name' => (string) ($a['project_name'] ?? ''),
            'project_description' => $description,
            'requested_amount' => $requestedStr,
            'allocated_amount' => $allocatedStr,
            'remarks' => (string) ($a['remarks'] ?? ''),
            // the CO certificate's validity wording: for bidding purposes / for whatever legal purpose it may serve
            'is_bidding' => self::truthy($a['is_bidding'] ?? false),
            'fund_source_name' => self::fundSource($lines),
            // the day the request was created (the LAA writes its day without the leading zero)
            'date' => self::longDate($a['created_at'] ?? null, $type === 'LAA' ? 'F j, Y' : 'F d, Y'),
        ];
        // A Regional / District requesting office prints the regional CBA.
        if ($regional) {
            $payload['issuing_office'] = 'REGIONAL';
        }

        // The regional CBA reads its one amount as `amount ?? allocated_amount ?? requested_amount`, and `??` takes an EMPTY
        // allocated_amount ('' — a CBA has no cash requirement) over the requested one, printing P0.00. So a CBA says its
        // amount outright: the requested one (else the allocated), as the main-office CBA does ("requested first, else
        // allocated"). The main-office CBA never reads `amount`, so this changes nothing for it. (RFA and LAA set it below.)
        if ($type === 'CBA') {
            $payload['amount'] = $requestedStr !== '' ? $requestedStr : $allocatedStr;
        }

        if ($type === 'RFA') {
            $payload['rfa_no'] = $payload['allocation_number'];
            $payload['amount'] = $requestedStr;
            $payload['particulars'] = $description;
            // payee = the requesting office; MAIN prints the STAFF form, Regional / District the REGIONAL OFFICE form
            $payload['payee_name'] = $office;
            $payload['requesting_department_type'] = $dept;
            if ($categoryName !== '') {
                $payload['rfa_type'] = mb_strtoupper($categoryName) . ' – Php 30 Million and below (' . ($dept === 'MAIN' ? 'STAFF' : 'REGIONAL OFFICE') . ')';
            }
            foreach (['cert_correct_name', 'cert_correct_title', 'cert_correct_office',
                'signatory_name_2', 'signatory_position_2', 'signatory_name_3', 'signatory_position_3',
                'signatory_name_4', 'signatory_position_4'] as $blank) {
                $payload[$blank] = '';
            }
            $payload['this_request'] = $requestedStr !== '' ? $requestedStr : '0';
            // Part II's money (a, b, c, g — and h, j = the amount) is worked out by RfaPrintService from the COB lines this request
            // draws on, across ALL its project blocks: their ids go in, the print service reads each COB's current balance, Account
            // Payable and Disbursement itself (this builder touches no database).
            $payload['cob_line_item_ids'] = self::lineItemIds($lines);
            // a COB has no SARO / OR reference of its own: the funding source prints blank
            $payload['funding_source'] = [
                'saro_no' => '', 'saro_date' => '', 'or_no' => '', 'or_date' => '',
                'corporate_receipts' => false, 'ng_support' => false, 'subsidy' => false, 'equity' => false,
                'agency_name' => '',
            ];
        }

        if ($type === 'LAA') {
            $payload['laa_number'] = $payload['allocation_number'];
            $payload['amount'] = $requestedStr;
            $payload['description'] = $description;
            foreach (['for_name', 'for_title', 'thru_name', 'thru_title', 'sig_name', 'sig_title'] as $blank) {
                $payload[$blank] = '';
            }
            // the requesting office: the addressee / "To transfer obligation made to <office> …"
            $payload['office_name'] = $office;
        }

        return $payload;
    }

    /**
     * A CAF (CafService row) + the RFA it certifies (BudgetAllocationService::fetchById row, [] when there is none) -> the CAF
     * print payload. The CAF carries its RFA's number, which prints "per RFA# …". Purpose = the RFA's description; the amount
     * = the sum of the CAF Amount of its lines; the fund = the Type of Fund of its first line.
     *
     * Every CAF is laid out like the CAF template (CafPrintService::templateStyle()). An FMD (main office) CAF names the RFA's
     * vendor as the payee, when there is one. A Regional / District requesting office words the payee the regional way:
     * "… entered into with <VENDOR> with principal office at <address> …", or, when the RFA has no vendor, "… in favor of
     * <the requesting office> …".
     */
    public static function cafPayload(array $caf, array $rfa = [], string $vendorAddress = ''): array
    {
        $lines = self::lines($caf['line_transaction'] ?? null);
        $amount = array_sum(array_map(fn ($l) => self::num($l['caf_amount'] ?? 0), $lines));
        $fund = self::fundSource($lines) ?: self::fundSource(self::lines($rfa['line_transaction'] ?? null));
        $dept = self::deptType($rfa['requesting_rc_office_type'] ?? '');
        $vendor = trim((string) ($rfa['vendor_name'] ?? ''));
        $office = trim((string) ($rfa['requesting_rc_name'] ?? ''));
        $address = trim($vendorAddress);

        $payload = [
            'caf_number' => (string) ($caf['caf_number'] ?? ''),
            'allocation_number' => (string) ($rfa['allocation_number'] ?? ''),   // "per RFA# …"
            'caf_amount' => self::money($amount),
            'purpose' => (string) ($rfa['description'] ?? ''),
            'fund_source_name' => $fund,
            'date' => self::isoDate($caf['created_at'] ?? null),
            'payee_name' => $vendor,
            'payee_office_address' => $vendor !== '' ? $address : '',
        ];

        if (in_array($dept, ['REGIONAL', 'DISTRICT'], true)) {
            $payload['issuing_office'] = 'REGIONAL';
            $payload['ref_type'] = 'RFA';
            if ($vendor !== '') {
                $payload['body_variant'] = 'entered_into_with';
            } else {
                $payload['body_variant'] = 'in_favor_of';
                $payload['payee_name'] = $office;
            }
        }

        return $payload;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * All of an allocation's lines across every project block (`projects`, see
     * BudgetAllocationService::attachProjects()) — falls back to the row's own mirrored line_transaction when
     * `projects` isn't present (e.g. a CAF row, which has no project blocks of its own).
     */
    private static function linesOfAllocation(array $a): array
    {
        if (isset($a['projects']) && is_array($a['projects']) && $a['projects']) {
            $lines = [];
            foreach ($a['projects'] as $block) {
                array_push($lines, ...self::lines($block['line_transaction'] ?? null));
            }

            return $lines;
        }

        return self::lines($a['line_transaction'] ?? null);
    }

    /** line_transaction -> a list of line arrays (a single object counts as one line; anything else as none). */
    private static function lines(mixed $lt): array
    {
        if (is_string($lt)) {
            $lt = json_decode($lt, true);
        }
        if (!is_array($lt)) {
            return [];
        }

        return array_values(array_filter(array_is_list($lt) ? $lt : [$lt], 'is_array'));
    }

    /** The budget line item (COB) ids of line_transaction lines — each once, blanks and non-numbers dropped, in the order they first appear. */
    private static function lineItemIds(array $lines): array
    {
        $ids = [];
        foreach ($lines as $l) {
            $id = $l['budget_line_item_id'] ?? null;
            if (is_numeric($id) && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    /** The Type of Fund of the first line that has one ("Sources of Fund" on the certificates). */
    private static function fundSource(array $lines): string
    {
        foreach ($lines as $l) {
            $t = trim((string) ($l['type_of_fund'] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /** A stored amount ("1,500.50", 1500.5, null, junk) -> a non-negative float; junk counts as 0. */
    private static function num(mixed $v): float
    {
        $s = str_replace(',', '', trim((string) $v));

        return is_numeric($s) && (float) $s > 0 ? (float) $s : 0.0;
    }

    private static function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    private static function truthy(mixed $v): bool
    {
        return $v === true || $v === 1 || in_array(strtolower(trim((string) $v)), ['1', 't', 'true', 'yes', 'on'], true);
    }

    /** The calendar day of a timestamp ("2026-11-16 09:15:00.5+08") as Y-m-d, '' when it is not one. */
    private static function isoDate(mixed $ts): string
    {
        $s = trim((string) $ts);

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $s) && ($d = \DateTime::createFromFormat('Y-m-d', substr($s, 0, 10))) && $d->format('Y-m-d') === substr($s, 0, 10)
            ? substr($s, 0, 10) : '';
    }

    /** A timestamp as a written date ("November 16, 2026"); null (print date = today) when it is not one. */
    private static function longDate(mixed $ts, string $format): ?string
    {
        $iso = self::isoDate($ts);

        return $iso === '' ? null : \DateTime::createFromFormat('Y-m-d', $iso)->format($format);
    }

    private function categoryName(mixed $categoryId): string
    {
        $id = (int) $categoryId;
        if ($id <= 0) {
            return '';
        }

        // soft-deleted categories keep their name: an old request still reads as it did
        return trim((string) App\ExecuteScalar("SELECT name FROM fims.budget_category WHERE budget_category_id = {$id}", 'DB'));
    }

    private function vendorAddress(mixed $vendorId): string
    {
        $id = (int) $vendorId;
        if ($id <= 0) {
            return '';
        }

        return trim((string) App\ExecuteScalar("SELECT address FROM fims.vendors WHERE vendor_id = {$id}", 'DB'));
    }
}
