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
 * No functions yet. Add them as the need arises.
 */
class GlobalService
{
}
