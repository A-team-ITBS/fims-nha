-- Saved default letterhead for FTLAREQ (fims.letterhead_layout) — the regional
-- office's request memo to Treasury (FtlaRequestPrintService). Same corrected
-- tagline as "13 - ftla_letterhead_default.sql" ("Build Better More Housing" —
-- no "and", matching Treasury's FTLA pages), kept consistent with the FTLA
-- bank letter and with the in-form Review step preview (ftla-form.php).
INSERT INTO fims.letterhead_layout (document_code, layout, updated_at)
VALUES (
    'FTLAREQ',
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
