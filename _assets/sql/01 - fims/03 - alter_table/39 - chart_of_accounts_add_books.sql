-- Add books column to chart_of_accounts table
-- Books are comma-separated values (e.g., "MO, CISFA, NGC, MPLC")

ALTER TABLE IF EXISTS fims.chart_of_accounts
ADD COLUMN IF NOT EXISTS books VARCHAR(255);

-- Add helpful comment
COMMENT ON COLUMN fims.chart_of_accounts.books IS 'Comma-separated list of books (e.g., "MO, CISFA, NGC, MPLC") this account applies to';
