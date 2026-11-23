<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Collecting officers (fims.collecting_officers) — custodians of collections per
 * region, each optionally linked to a GL account (+ SL sub-account), same
 * GL/SL tagging shape as AccountableOfficerService. Used to share that
 * service's own table (discriminated by an officer_type column) but now has
 * its own table instead — a Collecting Officer isn't an Accountable Officer
 * in the Fidelity Bond sense (no bond_listings/confirmation_letter_officers/
 * office_order_requests coverage), so this table only carries the columns
 * both kinds of officer actually share. Name columns follow uac.users'
 * convention (first_name/middle_name/last_name/suffix) — same as
 * AccountableOfficerService, kept deliberately parallel to that class rather
 * than factored into a shared base, since the two tables/FKs are
 * independent and any future divergence (e.g. Collecting Officers gaining
 * their own bond-like tracking) shouldn't ripple into the other.
 */
class CollectingOfficerService
{
    private string $table = 'fims.collecting_officers';

    // Builds "Last, First Middle Suffix" for display/search, skipping empty parts.
    private const FULL_NAME_SQL = "trim(both ', ' from
        concat_ws(', ', nullif(trim(co.last_name), ''),
            trim(concat_ws(' ', nullif(trim(co.first_name), ''), nullif(trim(co.middle_name), ''), nullif(trim(co.suffix), ''))))
    )";

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    /** Validate region (Responsibility Center) exists. */
    private function validateRc(?string $rcId): ?array
    {
        $rcId = trim((string) ($rcId ?? ''));
        if ($rcId === '') {
            return null;
        }

        $exists = (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM fims.responsibility_code WHERE rc_id = " . $this->str($rcId),
            'DB'
        );

        return $exists === 0
            ? ['success' => false, 'message' => "Region '{$rcId}' does not exist."]
            : null;
    }

    /** Validate COA account exists. */
    private function validateCoaAccount(?string $accountId): ?array
    {
        $accountId = trim((string) ($accountId ?? ''));
        if ($accountId === '') {
            return null;
        }

        $exists = (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM fims.chart_of_accounts WHERE account_id = " . $this->str($accountId),
            'DB'
        );

        return $exists === 0
            ? ['success' => false, 'message' => "Account '{$accountId}' does not exist in the Chart of Accounts."]
            : null;
    }

    /**
     * $page === null returns every match (dropdowns); otherwise paginates.
     * The row's PK is aliased AS accountable_officer_id so the page's
     * shared JS (ao_actionMenu()/ao_openModal(), written against that field
     * name from the Accountable Officers side) works unchanged for this
     * tab too — see accountable-officer-page.php's ao_switchType().
     */
    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', string $status = ''): array
    {
        try {
            $where = ['1=1'];
            if ($status !== '') {
                $where[] = "COALESCE(co.status, 'ACTIVE') = " . $this->str(strtoupper($status));
            }
            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(co.first_name ILIKE {$s} OR co.middle_name ILIKE {$s} OR co.last_name ILIKE {$s} OR rc.rc_name ILIKE {$s})";
            }
            $whereSql = implode(' AND ', $where);

            $selectJoin = "SELECT co.*, co.collecting_officer_id AS accountable_officer_id, " . self::FULL_NAME_SQL . " as officer_name,
                        COALESCE(rc.rc_code, '') as rc_code, COALESCE(rc.rc_name, '') as rc_name,
                        COALESCE(c.account_code, '') as coa_account_code, COALESCE(c.account_title, '') as coa_account_name,
                        COALESCE(s.account_code, '') as sl_account_code, COALESCE(s.account_title, '') as sl_account_name
                 FROM {$this->table} co
                 LEFT JOIN fims.responsibility_code rc ON co.rc_id = rc.rc_id
                 LEFT JOIN fims.chart_of_accounts c ON co.coa_account_id = c.account_id
                 LEFT JOIN fims.chart_of_accounts s ON co.sl_code = s.account_id
                 WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows("{$selectJoin} ORDER BY co.last_name ASC, co.first_name ASC", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} co WHERE {$whereSql}", 'DB');
            $data   = App\ExecuteRows("{$selectJoin} ORDER BY co.last_name ASC, co.first_name ASC LIMIT {$limit} OFFSET {$offset}", 'DB');

            $summary = App\ExecuteRow(
                "SELECT COUNT(*) FILTER (WHERE COALESCE(co.status, 'ACTIVE') = 'ACTIVE')  AS active,
                        COUNT(*) FILTER (WHERE COALESCE(co.status, 'ACTIVE') <> 'ACTIVE') AS inactive
                 FROM {$this->table} co WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'    => true,
                'data'       => $data ?: [],
                'summary'    => $summary,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('CollectingOfficerService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load collecting officers.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id;
            $data = App\ExecuteRow(
                "SELECT co.*, co.collecting_officer_id AS accountable_officer_id, " . self::FULL_NAME_SQL . " as officer_name,
                        rc.rc_code, rc.rc_name, c.account_code as coa_account_code, c.account_title as coa_account_name,
                        s.account_code as sl_account_code, s.account_title as sl_account_name
                 FROM {$this->table} co
                 LEFT JOIN fims.responsibility_code rc ON co.rc_id = rc.rc_id
                 LEFT JOIN fims.chart_of_accounts c ON co.coa_account_id = c.account_id
                 LEFT JOIN fims.chart_of_accounts s ON co.sl_code = s.account_id
                 WHERE co.collecting_officer_id = {$id}",
                'DB'
            );

            if (!$data) {
                return ['success' => false, 'message' => "Collecting officer #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('CollectingOfficerService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load collecting officer.'];
        }
    }

    public function create(array $body): array
    {
        try {
            $firstName = trim((string) ($body['first_name'] ?? ''));
            $lastName  = trim((string) ($body['last_name'] ?? ''));
            if ($firstName === '' || $lastName === '') {
                return ['success' => false, 'message' => 'first_name and last_name are required.'];
            }
            $middleName = trim((string) ($body['middle_name'] ?? ''));
            $suffix     = trim((string) ($body['suffix'] ?? ''));

            $rcId = trim((string) ($body['rc_id'] ?? ''));
            if ($err = $this->validateRc($rcId)) {
                return $err;
            }
            $accountId = trim((string) ($body['coa_account_id'] ?? ''));
            if ($err = $this->validateCoaAccount($accountId)) {
                return $err;
            }

            $columns = ['first_name', 'last_name'];
            $values  = [$this->str($firstName), $this->str($lastName)];
            if ($middleName !== '') {
                $columns[] = 'middle_name';
                $values[]  = $this->str($middleName);
            }
            if ($suffix !== '') {
                $columns[] = 'suffix';
                $values[]  = $this->str($suffix);
            }
            if ($rcId !== '') {
                $columns[] = 'rc_id';
                $values[]  = $this->str($rcId);
            }
            if ($accountId !== '') {
                $columns[] = 'coa_account_id';
                $values[]  = $this->str($accountId);
            }
            // Same gap as AccountableOfficerService::create() — the Add
            // form's GL/SL section lets a Sub-Ledger be picked before the
            // officer even exists, so this has to be accepted on create
            // too, not just update().
            $slCode = trim((string) ($body['sl_code'] ?? ''));
            if ($slCode !== '') {
                $columns[] = 'sl_code';
                $values[]  = $this->str($slCode);
            }
            if (array_key_exists('status', $body) && trim((string) $body['status']) !== '') {
                $columns[] = 'status';
                $values[]  = $this->str($body['status']);
            }

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ') RETURNING collecting_officer_id',
                'DB'
            );

            return ['success' => true, 'message' => 'Collecting officer created successfully.', 'data' => ['collecting_officer_id' => (int) $row['collecting_officer_id']]];
        } catch (\Exception $e) {
            App\LogError('CollectingOfficerService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create collecting officer.'];
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $id    = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            if (array_key_exists('rc_id', $body) && ($err = $this->validateRc($body['rc_id']))) {
                return $err;
            }
            if (array_key_exists('coa_account_id', $body) && ($err = $this->validateCoaAccount($body['coa_account_id']))) {
                return $err;
            }

            $set = [];
            if (array_key_exists('first_name', $body)) {
                $firstName = trim((string) $body['first_name']);
                if ($firstName === '') {
                    return ['success' => false, 'message' => 'first_name cannot be empty.'];
                }
                $set[] = 'first_name = ' . $this->str($firstName);
            }
            if (array_key_exists('last_name', $body)) {
                $lastName = trim((string) $body['last_name']);
                if ($lastName === '') {
                    return ['success' => false, 'message' => 'last_name cannot be empty.'];
                }
                $set[] = 'last_name = ' . $this->str($lastName);
            }
            if (array_key_exists('middle_name', $body)) {
                $middleName = trim((string) $body['middle_name']);
                $set[]      = 'middle_name = ' . ($middleName !== '' ? $this->str($middleName) : 'NULL');
            }
            if (array_key_exists('suffix', $body)) {
                $suffix = trim((string) $body['suffix']);
                $set[]  = 'suffix = ' . ($suffix !== '' ? $this->str($suffix) : 'NULL');
            }
            if (array_key_exists('rc_id', $body)) {
                $rcId  = trim((string) $body['rc_id']);
                $set[] = 'rc_id = ' . ($rcId !== '' ? $this->str($rcId) : 'NULL');
            }
            if (array_key_exists('coa_account_id', $body)) {
                $accountId = trim((string) $body['coa_account_id']);
                $set[]     = 'coa_account_id = ' . ($accountId !== '' ? $this->str($accountId) : 'NULL');
            }
            if (array_key_exists('sl_code', $body)) {
                $slCode = trim((string) $body['sl_code']);
                $set[]  = 'sl_code = ' . ($slCode !== '' ? $this->str($slCode) : 'NULL');
            }
            if (array_key_exists('status', $body)) {
                $status = trim((string) $body['status']);
                $set[]  = 'status = ' . ($status !== '' ? $this->str($status) : "'ACTIVE'");
            }
            if (!$set) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            $set[] = 'updated_at = CURRENT_TIMESTAMP';

            App\Execute("UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE collecting_officer_id = {$id}", 'DB');

            return ['success' => true, 'message' => 'Collecting officer updated successfully.'];
        } catch (\Exception $e) {
            App\LogError('CollectingOfficerService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update collecting officer.'];
        }
    }
}
