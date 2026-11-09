<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * GlobalService
 *
 * Holds ONLY reusable helper / utility functions that are likely to be used by
 * multiple services (e.g. formatting, date handling, generic validation).
 *
 * Rules:
 * - Add a function here only when it is (or will clearly be) shared by 2+ services.
 * - Feature-specific logic and database operations belong in that feature's own
 *   service file, not here.
 * - Keep functions generic and stateless; do not put business rules here.
 * - Code shared across modules (all FIMS apps) belongs in `_shared/`, not here.
 *
 * Functions:
 * - generateProgramCode()  next program code "YYYY-<rc_code>-NNN" (see below).
 *   NOTE: this one reads the database, which the rules above would normally keep in
 *   the feature's own service (ProgramService). It lives here by request, so it can
 *   be called from any service or route: GlobalService::generateProgramCode().
 */
class GlobalService
{
    /**
     * Builds the next program code: "<year>-<rc_code>-<NNN>", e.g. 2026-35-074-0000001-000-101.
     *
     *  - year    : the current year, or $year when given.
     *  - rc_code : fims.responsibility_code.rc_code for the logged-in user's uac.users.rc_id
     *              ($userId, else the current user).
     *  - NNN     : last 3 digits of the highest existing fims.program.program_code that ends in
     *              "-NNN", plus one, zero-padded to 3 digits. Codes that don't end in "-NNN"
     *              (e.g. "SAMPLE-1") are ignored, and an empty table starts at 001.
     *
     * The number is a suggestion read at call time — nothing is reserved. Two callers asking at
     * the same moment get the same value; program_code's primary key makes the second insert fail.
     *
     * @throws \InvalidArgumentException when no user is logged in, the user has no rc_id, or
     *                                   the 3-digit sequence is exhausted (999).
     */
    public static function generateProgramCode(?int $userId = null, ?int $year = null): string
    {
        $userId = $userId ?: (int) App\CurrentUserID();
        if ($userId <= 0) {
            throw new \InvalidArgumentException('No logged-in user — cannot build the RC part of the program code.');
        }

        $rcCode = trim((string) App\ExecuteScalar(
            'SELECT rc.rc_code FROM uac.users u ' .
            'JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id ' .
            'WHERE u.user_id = ' . $userId,
            'DB'
        ));
        if ($rcCode === '') {
            throw new \InvalidArgumentException('Your user account has no RC code (users.rc_id). Ask an administrator to set it.');
        }

        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(program_code FROM '-([0-9]{3})$') AS INTEGER)), 0) FROM fims.program",
            'DB'
        );
        if ($last >= 999) {
            throw new \InvalidArgumentException('The 3-digit program sequence is exhausted (999).');
        }

        return sprintf('%d-%s-%03d', $year ?: (int) date('Y'), $rcCode, $last + 1);
    }

    /**
     * Where sub-program codes for a program start: ['prefix' => '001', 'next' => 3].
     *
     *  - prefix : the last 3 digits of the program code (2026-35-074-0000000-000-001 -> "001").
     *  - next   : highest existing fims.sub_program.sub_program_code of the form "<prefix>-NNN", plus one
     *             (1 when there is none). sub_program_code is the table's primary key, so the lookup is
     *             by prefix, not by program, to never suggest a code that is already taken.
     *
     * Codes are formatted "<prefix>-<NNN>" (001-001, 001-002, …) — see formatSubProgramCode(). A caller
     * adding several line items takes `next` once and counts up itself. Nothing is reserved.
     *
     * @throws \InvalidArgumentException when the program code does not end in "-NNN" or the sequence is exhausted.
     */
    public static function nextSubProgramNumber(string $programCode): array
    {
        if (!preg_match('/-([0-9]{3})$/', trim($programCode), $m)) {
            throw new \InvalidArgumentException("Program code '{$programCode}' does not end in a 3-digit sequence.");
        }
        $prefix = $m[1];

        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(sub_program_code FROM '-([0-9]{3})$') AS INTEGER)), 0)
             FROM fims.sub_program
             WHERE sub_program_code ~ " . App\QuotedValue('^' . $prefix . '-[0-9]{3}$', App\DataType::STRING, 'DB'),
            'DB'
        );
        if ($last >= 999) {
            throw new \InvalidArgumentException("The 3-digit sub-program sequence for program {$prefix} is exhausted (999).");
        }

        return ['prefix' => $prefix, 'next' => $last + 1];
    }

    public static function formatSubProgramCode(string $prefix, int $number): string
    {
        return sprintf('%s-%03d', $prefix, $number);
    }
}
