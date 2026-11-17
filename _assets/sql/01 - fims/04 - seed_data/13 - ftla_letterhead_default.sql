-- Saved default letterhead for FTLA (fims.letterhead_layout) — same row layout
-- as LetterheadLayoutService::FACTORY_ROW (the system-wide built-in banner), but
-- with Treasury's exact FTLA tagline ("Build Better More Housing" — no "and"),
-- confirmed against ftla-preparation.php, ftlafundtransfer.php and
-- ftlasettlement.php. Scoped to FTLA only; every other document (CBA, RFA, LAA,
-- ...) keeps the shared factory default's "Build Better and More Housing".
-- Deletable via "Restore original" in the print preview's letterhead editor,
-- which reverts FTLA back to the shared factory default.
INSERT INTO fims.letterhead_layout (document_code, layout, updated_at)
VALUES (
    'FTLA',
    '{
        "items": [
            {"type": "image", "asset": "logo",  "height": 57, "x": 14.8, "y": 4.5},
            {"type": "title", "line1": "NATIONAL HOUSING AUTHORITY", "line2": "Build Better More Housing",
             "size1": 16, "size2": 9, "bold1": true, "width": 315.3, "align": "center", "x": 91.2, "y": 18.6},
            {"type": "image", "asset": "brand", "height": 55, "x": 417.3, "y": 5.5},
            {"type": "image", "asset": "seal",  "height": 66, "x": 547.7, "y": 0}
        ],
        "footer_text": ""
    }'::jsonb,
    CURRENT_TIMESTAMP
)
ON CONFLICT (document_code) DO UPDATE SET layout = EXCLUDED.layout, updated_at = CURRENT_TIMESTAMP;
