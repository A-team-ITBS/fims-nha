-- Enables fuzzy/"nearest match" text search (trigram similarity) used by
-- Fidelity Bond's officer-name search (CL draft's "Officers named in the
-- letter" picker, and the Confirmation-Letter-scan auto-extract's officer
-- match) — an exact ILIKE substring match misses names with a dropped
-- middle initial, an OCR typo, or words in a different order.
CREATE EXTENSION IF NOT EXISTS pg_trgm;
