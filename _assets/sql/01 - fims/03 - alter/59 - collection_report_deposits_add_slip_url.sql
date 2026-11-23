-- Deposit Slip attachment per Deposit line — replaces the Status column (Swept/Not
-- Swept) in the Register/Edit modal's Deposit table with a place to paste the actual
-- proof-of-deposit link/file path, same "paste file path or URL" convention as FTLA's
-- supporting-documents table (ftla-form.php) — no new file-storage infra, just a plain
-- URL/path string. is_swept itself is untouched (Fund Sweeping still reads/writes it;
-- a swept row is still locked read-only in the UI via its own dimmed/disabled styling,
-- just without the separate text badge that used to spell it out).
ALTER TABLE fims.collection_report_deposits
    ADD COLUMN IF NOT EXISTS deposit_slip_url TEXT;
