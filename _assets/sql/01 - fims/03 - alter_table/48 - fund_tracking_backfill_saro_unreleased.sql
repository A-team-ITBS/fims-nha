-- ============================================================
-- UPDATE: fims.fund_tracking.unreleased  (run after "47 - fund_tracking_add_unreleased")
--   * a SARO's `unreleased` is its balance: its amount less every live NCA released under it. The API (FundTrackingServices::
--     refreshSaroUnreleased) keeps it up to date from now on — whenever a SARO or one of its NCAs is added, changed or deleted — but
--     the SAROs saved before that have none yet. This fills them in. Only SAROs get one; every other record keeps NULL.
--
-- Changes no other column (updated_at is left alone). Safe to re-run: it just recomputes the same figures.
-- ============================================================

UPDATE fims.fund_tracking s
   SET unreleased = COALESCE(s.amount, 0) - COALESCE((
           SELECT SUM(n.amount)
             FROM fims.fund_tracking n
            WHERE n.parent_id = s.id
              AND n.deleted_at IS NULL
              AND n.tracking_type_id = (SELECT t.fund_tracking_type_id FROM fims.fund_tracking_type t
                                         WHERE UPPER(t.fund_tracking_code) = 'NCA' AND t.deleted_at IS NULL
                                         ORDER BY t.fund_tracking_type_id LIMIT 1)
       ), 0)
 WHERE s.deleted_at IS NULL
   AND s.tracking_type_id = (SELECT t.fund_tracking_type_id FROM fims.fund_tracking_type t
                              WHERE UPPER(t.fund_tracking_code) = 'SARO' AND t.deleted_at IS NULL
                              ORDER BY t.fund_tracking_type_id LIMIT 1);
