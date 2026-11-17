# COB — Corporate Operating Budget (monitoring)

Page `app/pages/cob-page.php` (menu: **Monitoring → COB**), modals `app/pages/readone/cob-readone.php` (View) and `readone/cob-report-readone.php`
(Generate Report), services `app/services/CobService.php`, `CobReportService.php` and `XlsxWriter.php`, routes `app/routes/cob.php`. Read-only: the COB is never edited, it is what the Budget Request flow produces once a request is **Approved**.

The table follows the **Corporate Operating Budget Monitoring** sheet: *Code | Name | Approved Amount | Revision / Realignment | Revised Amount | CBA Requested |
Available for Earmarking | RFA Approved | LAA Issued | Remaining Balance*, as a tree (Region → Program → Sub-Program → Project), a dash for zero.

## What is in it

Only budget requests whose status is **Approved** (`fims.settings_status`, `status_for = 'budget_req'`, name `Approved`). A Draft, For Review,
Endorsed or Returned request never appears in a table, a total or a filter option — every query in `CobService` starts from that condition
(`conditions()`), the same rule `BudgetLineItemService::fetchApproved()` applies to the Memo Request form's COB list.

The budget comes from the Budget Request tables:

```
fims.budget_req                       fiscal year, requesting RC (rc_id), status
  └ fims.budget_req_program           a Program (+ optional Sub-Program)
      └ fims.budget_req_project       a Project of that Program (+ location)
          ├ fims.budget_line_items    chart-of-accounts lines — the budget amounts live here
          └ fims.budget_req_project_attachment   supporting documents (uploaded file or pasted link)
```

and what has been drawn against it from the memo requests (CBA / RFA / LAA) through the reservation ledger `fims.budget_line_item_earmark`
(joined to `fims.budget_allocation` for the type; see `BudgetLineItemService`).

One COB "record" is one **budget line item**. A line item of an older request that never got a program block still counts — it shows as
"No program" / "No project" rather than disappearing.

## The eight columns

Every row, group and total is the SUM of these per line item (`CobService::lineAmountExprs()`):

| Column | Meaning | From |
|---|---|---|
| **Approved Amount** | What FMD suggested in the Budget Proposal module | `budget_line_items.suggested_amount`, as it stands |
| **Revision / Realignment** | Net of the approved Budget Revisions (an increase, or a decrease in brackets) | `budget_line_items.adjustment` |
| **Revised Amount** | Approved Amount + Revision / Realignment | `suggested_amount + adjustment` |
| **CBA Requested** | What approved CBAs (and CFAs) took off the budget | earmark rows of CBA / CFA allocations: `original_amount`, only those that did take it off (`balance_applied`) and are not released |
| **Available for Earmarking** | Revised Amount − CBA Requested: what a new CBA can still draw | `suggested_amount + adjustment − CBA Requested` |
| **RFA Approved** | Drawn from the CBAs by approved RFAs | the `converted` earmark rows of RFA allocations |
| **LAA Issued** | Drawn from the CBAs by issued LAAs | the `converted` earmark rows of LAA allocations |
| **Remaining Balance** | Revised Amount − RFA Approved − LAA Issued | A CBA only *reserves* money; an RFA / LAA actually uses it, so only they reduce what remains |

The columns follow the sheet's own arithmetic from Approved Amount, so three identities hold on every row, group and total (the tests check them on every node):
`Approved + Revision = Revised`, `Revised − CBA = Available`, `Revised − RFA − LAA = Remaining`.

> **Caveat — `suggested_amount` is also the line's live balance.** An approved CBA/CFA takes its amount off `suggested_amount`, and an approved Budget Revision moves it by the
> same delta as `adjustment`. Taking it as the Approved Amount therefore means that on a line that already has a CBA or a revision, Revised and Available count that CBA / revision
> a second time (e.g. a 900,000 line with a 200,000 CBA is stored as 700,000: Approved 700,000, Available 500,000, though 700,000 is still free). If the sheet should show the amount
> as first suggested instead, rebuild it in `CobService::lineAmountExprs()` as `suggested_amount + CBA Requested − adjustment`.

Rules worth knowing:
- A **deleted** memo request (`budget_allocation.deleted_at`) counts for nothing — the earmark rows of a deleted RFA / LAA survive in the ledger, so the allocation's `deleted_at` is what is filtered on.
- A CBA approved **before** "charge the line on approval" (`balance_applied = FALSE`) never reduced the line, so it is not in CBA Requested either — which keeps `Available = Revised − CBA` true.
- A CBA fully drawn by RFAs / LAAs still shows its original amount in CBA Requested (`original_amount`; the row's `amount` shrinks as it is drawn).
- Carry Over Obligation, Obligation and Disbursement are **not** part of the COB table any more: nothing writes `disbursement`, and `account_payable` is only filled by CBA / CFA approvals — it was a CBA figure under another name.

## The page

Three tabs share one set of filters (Fiscal Year, Type of Fund, Component, Region, Program, Search, Amount Unit) and four KPI cards (Approved Requests, Revised
Amount, Available for Earmarking, Remaining Balance):

| Tab | Shows |
|---|---|
| **Per Region** (default) | Region → Program → Sub-Program → Project. The Region row's code is its RC id (`R04`); a District office rolls up into its Regional office, the central departments into "Central Office" |
| Per Type of Fund | Type of Fund → Component → Program → Sub-Program → Project |
| Per Component | Component → Type of Fund → Program → Sub-Program → Project |

Behaviour worth knowing:
- Code and Name are separate columns (a code is its program / sub-program / project code, `R04` for a region; a fund or component has none). There is **no colour coding** of rows or columns:
  the hierarchy shows as indentation, a caret and bold group rows (everything above a project). A zero is a dash, a negative is in red brackets, the toolbar says which unit the table is in.
- Layout: the ten headings wrap onto two lines (the theme's default is one line, which made the eight long headings use up the whole table and squeeze Name to a few words per line), Name is never
  narrower than 260 px, an amount column never narrower than 116 px, and the table scrolls sideways inside its card on a narrow screen. A project's requesting office sits on its own muted line under the name.
- A program block **without** a Sub-Program has no Sub-Program row: its projects sit directly under the program, one step shallower.
- Opens on the **newest fiscal year that has approved data**; "All Years" is one choice away. Reset goes back to that year.
- Trees are built on the server (`GET /cob/rollup`); every node has the same shape and every level foots to the one above. A node whose Type of Fund /
  Component / Program was never set is kept as **Unassigned** and sorted last. Funds sort NG Support → other → Trust Fund → Corporate Receipt; Components
  Capital Outlay → Other-Capital Outlay → COE (by name, not id: seeded ids follow insertion order, which a fresh install does not guarantee); programs,
  sub-programs and projects by code; regions by RC code with Central Office first.
- A project row has **View** — the approved request in full (programs, projects, line items with the eight columns, supporting documents) in `window.cobModal`. The same
  project in two requests (a long-running project is budgeted every year) is two rows, each opening its own request.
- Amount Unit (Full / Thousands / Millions / Billions) is a display scale: nothing is refetched, and the choice is remembered per browser.
- Open tree rows stay open across filter changes and tab switches. Expand all / Collapse all per tab.
- A pasted document link is user text: the API only ever returns an `http(s)` address as `view_url` (an upload becomes a presigned URL), and the page checks it
  again before it becomes an `href`.

## Generate Report (PDF / Excel)

Each of the three tree tabs has a **Generate Report** button (`app/pages/readone/cob-report-readone.php`, `window.cobReport.open(tab)`):

1. **What to include** — how deep the report goes, shallowest first (Per Region: Region → + Program → + Sub-Program → + Projects; the other two start with their two
   top levels, then Program, Sub-Program, Projects). *Down to a level* means the same for every program: a program with no Sub-Program simply has no row of that level.
2. **Preview**, switchable between the **PDF look** and the **Excel look** — plain, like the page: a navy header, a grey TOTAL row, no row or column tints (bold group rows, indented names, light-grey cell lines).
3. **Export to PDF** (A4 landscape, header repeated on every page, "Page X of Y") or **Export to Excel** (`.xlsx`, one header row, indented names, a thin gap between first-level groups).

How it works:
- Built on the **server** (`GET /cob/report` → `CobReportService`), from the very tree the tab shows (`CobService::rollup()`): only Approved requests, the page's
  filters, the caller's RC scope, and the page's **Amount Unit**. The columns are the sheet's ten (Code, Name and the eight amounts).
- The preview is the **HTML the PDF is laid out from** (Dompdf), so a PDF always matches what was previewed; the Excel preview is an imitation of the sheet from the
  same rows and palette. Export buttons are off while the preview loads or is empty, and the export asks for exactly the parameters the preview was drawn from.
- `format=json` returns the model plus both previews; `pdf` / `xlsx` return `{ success, filename, mime, data: base64 }` — the same shape the print forms use
  (`POST /print/{doc}`). File names: `COB_Per_Region_Region_Program_SubProgram_20261116.pdf` (view, level, date).
- Excel cells hold the **full peso value**; the number format only scales what is shown (a trailing comma divides by 1,000) and prints a zero as a dash and a negative in brackets, so a Millions report still sums correctly.
  The `.xlsx` is written by `XlsxWriter` (ZipArchive; no PhpSpreadsheet in this project).
- Limits: the on-screen preview shows the first 400 rows (and says how many more the file has); a **PDF over 600 rows is refused** with advice to choose a shallower
  level or narrow the filters (Excel has no such limit). Dompdf keeps the whole table in memory (about 45 KB per cell; 600 rows × 10 columns peaks near 310 MB, ~11 s), so
  `pdf()` raises `memory_limit` to 512 MB — the figure the app's own PDF export uses (`PDF_MEMORY_LIMIT`) — and the time limit to 120 s. With every fiscal year in the report,
  project rows carry their year (`· FY 2026`) so the copies of a long-running project can be told apart.
- Amounts are printed without a currency sign (the unit is in the header): Dompdf's fonts have no peso glyph.

## Who sees what

Same rule as the Budget Request list (`GlobalService::getUserRcScope()` / `resolveBudgetReqRcFilter()`): a user with an RC sees that RC and its descendants;
a superadmin or a user with no RC sees every RC; the central review RC (FMD) sees every RC when the request carries `central=1` — the page always sends it,
the server honours it only for FMD. A `region_id` filter can only narrow the scope, never widen it. `GET /cob/read/{id}` answers 404 for a request that does
not exist, is not Approved, or is outside the caller's scope — the three look the same.

## Not in this version

The old COB (FMD app, `/cob-v3/*`) also had Excel / CSV import, a budget-utilization report, SARO and DBM-approved amounts, fund tracking, budget versions
and COE classifications. None of those have a source in the Budget Request tables, so they are not here. The report dialog has no dialog-only Program filter — it follows
the page's filters. There is no flat records tab and no `GET /cob/list` any more. The old page itself is kept outside the
repo as `cob-page.old-design.php` next to the other old-COB reference files.

## History

- First version: *Total | Fund Balance | Adjustment | Carry Over Obligation* over Type of Fund / Component / Region trees down to the account. Replaced by the
  Corporate Operating Budget Monitoring sheet's eight columns: the account level and the Office level are gone (an account is still in the View modal), and Sub-Program is a level.
- Second pass: the Approved Amount is `suggested_amount` as it stands (it had been rebuilt as the amount before CBAs and revisions), and the row / column colour coding was removed.
