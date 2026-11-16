# COB — Corporate Operating Budget (monitoring)

Page `app/pages/cob-page.php` (menu: **Monitoring → COB**), modals `app/pages/readone/cob-readone.php` (View) and `readone/cob-report-readone.php`
(Generate Report), services `app/services/CobService.php`, `CobReportService.php` and `XlsxWriter.php`, routes `app/routes/cob.php`. Read-only: the COB is never edited, it is what the Budget Request flow produces once a request is **Approved**.

## What is in it

Only budget requests whose status is **Approved** (`fims.settings_status`, `status_for = 'budget_req'`, name `Approved`). A Draft, For Review,
Endorsed or Returned request never appears in a table, a total or a filter option — every query in `CobService` starts from that condition
(`conditions()`), the same rule `BudgetLineItemService::fetchApproved()` applies to the Memo Request form's COB list.

The data comes from the Budget Request tables only:

```
fims.budget_req                       fiscal year, requesting RC (rc_id), status
  └ fims.budget_req_program           a Program (+ optional Sub-Program)
      └ fims.budget_req_project       a Project of that Program (+ location)
          ├ fims.budget_line_items    chart-of-accounts lines — every amount lives here
          └ fims.budget_req_project_attachment   supporting documents (uploaded file or pasted link)
```

One COB "record" is one **budget line item**. A line item of an older request that never got a program block still counts — it shows as
"No program" / "No project" rather than disappearing.

## The figures

| Column | Source | Meaning |
|---|---|---|
| **Fund Balance** | `budget_line_items.suggested_amount` | What FMD approved and is still free. Already reduced by every approved CBA, already moved by every approved Budget Revision |
| Adjustment | `budget_line_items.adjustment` | Net of approved Budget Revisions. A reference figure: Fund Balance already includes it, so it is **not** added to Total |
| **Carry Over Obligation** | `budget_line_items.account_payable` | Obligated, not yet disbursed (the old COB used account payable for this too) |
| **Total** | Fund Balance + Carry Over Obligation | |
| Proposed / Obligation / Disbursement | `proposed_amount` / `obligation` / `disbursement` | Shown in the View modal |

There is **no "New Funds" column**: nothing in the Budget Request tables says what a "new fund" is. If it is defined later (a column, or a rule over
revisions), it is a one-line addition to `CobService::sumsSql()` and the two table renderers.

## The page

Three tabs share one set of filters (Fiscal Year, Type of Fund, Component, Region, Program, Search) and the KPI cards:

| Tab | Shows |
|---|---|
| Sources and Uses of Funds | Type of Fund → Component → Program → Project → Account |
| Uses of Funds Per Component | Component → Type of Fund → Program → Project → Account |
| Uses of Funds Per Region | Region → Office → Program → Project → Account. A District office rolls up into its Regional office; the central departments into "Central Office" |

Behaviour worth knowing:
- Opens on the **newest fiscal year that has approved data**; "All Years" is one choice away. Reset goes back to that year.
- Trees are built on the server (`GET /cob/rollup`); every node has the same shape and every level foots to the one above. A node whose Type of Fund /
  Component / Program was never set is kept as **Unassigned** and sorted last. Funds sort NG Support → other → Trust Fund → Corporate Receipt; Components
  Capital Outlay → Other-Capital Outlay → COE (by name, not id: seeded ids follow insertion order, which a fresh install does not guarantee).
- A project row has **View** — the approved request in full (programs, projects, line items, supporting documents) in `window.cobModal`. The same
  project in two requests (a long-running project is budgeted every year) is two rows, each opening its own request.
- Amount Unit (Full / Thousands / Millions / Billions) is a display scale: nothing is refetched, and the choice is remembered per browser.
- Open tree rows stay open across filter changes and tab switches. Expand all / Collapse all per tab.
- A pasted document link is user text: the API only ever returns an `http(s)` address as `view_url` (an upload becomes a presigned URL), and the page checks it
  again before it becomes an `href`.

## Generate Report (PDF / Excel)

Each of the three tree tabs has a **Generate Report** button — the same idea as the old COB's report dialog (`app/pages/readone/cob-report-readone.php`,
`window.cobReport.open(tab)`):

1. **What to include** — how deep the report goes, shallowest first: the first level only → + the second → + Program → + Projects → + Accounts
   (Sources and Uses: Type of Fund / Component / …; Per Component: Component / Type of Fund / …; Per Region: Region / Office / …).
2. **Preview**, switchable between the **PDF look** (navy cover, shaded tree rows, depth markers `>` `-` `·` `+`) and the **Excel look**
   (navy header, blue total row, green first-level rows, a thin gap between groups).
3. **Export to PDF** (A4 landscape, header repeated on every page, "Page X of Y") or **Export to Excel** (`.xlsx`, merged two-row header, indented rows).

How it works:
- Built on the **server** (`GET /cob/report` → `CobReportService`), from the very tree the tab shows (`CobService::rollup()`): only Approved requests, the page's
  filters, the caller's RC scope, and the page's **Amount Unit**. Columns as on the page — Total, Fund Balance, Adjustment, Carry Over Obligation.
- The preview is the **HTML the PDF is laid out from** (Dompdf), so a PDF always matches what was previewed; the Excel preview is an imitation of the sheet from the
  same rows and palette. Export buttons are off while the preview loads or is empty, and the export asks for exactly the parameters the preview was drawn from.
- `format=json` returns the model plus both previews; `pdf` / `xlsx` return `{ success, filename, mime, data: base64 }` — the same shape the print forms use
  (`POST /print/{doc}`). File names: `Sources_and_Uses_of_Funds_Fund_Component_20261116.pdf` (title, level, date).
- Excel cells hold the **full peso value**; the number format only scales what is shown (a trailing comma divides by 1,000), so a Millions report still sums correctly.
  The `.xlsx` is written by `XlsxWriter` (ZipArchive; no PhpSpreadsheet in this project).
- Limits: the on-screen preview shows the first 400 rows (and says how many more the file has); a **PDF over 800 rows is refused** with advice to choose a shallower
  level or narrow the filters (Excel has no such limit). With every fiscal year in the report, project rows carry their year (`· FY 2026`) so the copies of a
  long-running project can be told apart.
- Amounts are printed without a currency sign (the unit is in the header): Dompdf's fonts have no peso glyph.

## Who sees what

Same rule as the Budget Request list (`GlobalService::getUserRcScope()` / `resolveBudgetReqRcFilter()`): a user with an RC sees that RC and its descendants;
a superadmin or a user with no RC sees every RC; the central review RC (FMD) sees every RC when the request carries `central=1` — the page always sends it,
the server honours it only for FMD. A `region_id` filter can only narrow the scope, never widen it. `GET /cob/read/{id}` answers 404 for a request that does
not exist, is not Approved, or is outside the caller's scope — the three look the same.

## Not in this version

The old COB (FMD app, `/cob-v3/*`) also had Excel / CSV import, a budget-utilization report, SARO and DBM-approved amounts, fund tracking, budget versions
and COE classifications. None of those have a source in the Budget Request tables, so they are not here. The report dialog has no dialog-only Program filter — it follows
the page's filters. There is no flat records tab either: `GET /cob/list` (`CobService::fetchAlls()`, one row per approved line item, paginated) still exists but the page no longer uses it. The old page itself is kept outside the
repo as `cob-page.old-design.php` next to the other old-COB reference files.
