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

One COB "record" is one **budget line item**. A line item with no program never disappears: a COE budget request carries line items of its own
(`budget_line_items.budget_req_project_id IS NULL`, no Program / Sub-Program / Project), and so does an older request that never got a program block.
In the plain tree they sit under "No program" / "No project"; the two monitoring pages ask for the CO / COE split below, which gives them a proper home.

### CO and COE

A line's **expense class** is read off its chart-of-accounts code (`CobService::expenseClassSql()` — the same prefix rule as
`DisbursementVoucherService::deriveExpenseClass()` and the Budget Proposal "View as COB"; there is no stored column for it):

| Account code starts with | Class | Side |
|---|---|---|
| `1` | **CO** (capital outlay) | CO |
| `5-01` | **PS** (Personal Services) | COE |
| `5-02` | **MOOE** (Maintenance & Other Operating Expenses) | COE |

`costTypeOf()` decides the side: a line is **COE** when it has **no program** (a COE budget request) **or** its account is PS / MOOE; otherwise it is CO. A
programless line whose account is neither PS nor MOOE is kept, as COE → "Other (not PS / MOOE)". (The Budget Request form already keeps PS / MOOE accounts to
COE requests and everything else to programs, so the two rules agree on current data; the tie-break only matters for older requests.)

`GET /cob/rollup?...&split_coe=1` (both pages send it; the Generate Report and the other layouts do not, so their tree is unchanged) cuts the tree in two at the
top, each half a root node (`type: 'bucket'`, key `CO` / `COE`) carrying the sums of what is under it:

```
CO    Program → Sub-Program → Region → Project        (the Region is the one attached to the project, fims.project.rc_id; the filing office when the project has none)
COE   PS | MOOE → Originating Unit → Request        (the leaf is the line's project if it has one, else the request: type 'request', named by its reference no.)
```

With `with_allocations=1` a request leaf gets its RFAs / LAAs and vouchers like a project does; a COE request's PS and MOOE lines are separate leaves, so an RFA
that drew on both shows under each with what it drew there (`leafKey()`). In the View modal the request's programless lines come back in `unassigned_line_items`
and are shown as a **COE** card split into PS / MOOE; every line carries its `expense_class`.

## The eight columns

Every row, group and total is the SUM of these per line item (`CobService::lineAmountExprs()`):

| Column | Meaning | From |
|---|---|---|
| **Approved Amount** | What FMD suggested in the Budget Proposal module, as first suggested — before any Budget Revision or CBA touched the line | `budget_line_items.suggested_amount` — stable: no CBA, RFA, LAA or revision ever moves it (see the note below) |
| **Revision / Realignment** | **A project** (or a COE request) shows **only the latest** approved revision or realignment on it (what the most recent approved request changed), each with its sign and colour: an addition is **+** (green), a deduction **−** (red). A realignment is **−** on the budget that gave funds up and **+** on the one that received them; earlier ones are not listed. **Parent rows** (bucket, program, sub-program, unit, class) **and the TOTAL have no figure in this column** — there is no total of the revisions / realignments (Revised Amount is still worked out from all of them). "The latest" is per project (per request and PS / MOOE class under COE): the request approved last among those that touched any of its lines, and what it did on that project's lines | a project's lines are the latest request's rows of the ledger `fims.budget_line_item_revision_transaction` (`latest_revision_amount` — a `REVISION` / `LEGACY` row; `latest_realignment_amount` — the `REALIGNMENT_IN` / `REALIGNMENT_OUT` rows), worked out after the SQL by `CobService::latestByLeaf()` / `latestByLine()`. The net of all of them is `revision_amount` = `BudgetLineBalanceSql::revisionExpr()`, which Revised Amount is built from but this column does not show |
| **Revised Amount** | The Approved Amount net of the Revision / Realignment — a decrease lowers it. A line no revision has touched shows its Approved Amount | `budget_line_items.revised_amount` — a **generated** column, `suggested_amount + adjustment` (`03 - alter_table/54`), so it is always `Approved Amount + Revision / Realignment`. The Revision / Realignment total behind it is `budget_line_items.adjustment` (the ledger total, kept by a trigger — `03 - alter_table/53`); both are read straight from the table (`BudgetLineBalanceSql::revisedExpr()` / `revisionExpr()`) |
| **CBA Requested** | What approved CBAs (and CFAs) hold against the budget, plus what RFAs / LAAs have since drawn from them | earmark rows of not-deleted allocations: `amount` of the `active` rows (CBA / CFA reservations) + the `converted` RFA / LAA rows (`BudgetLineBalanceSql::earmarkAggregateSql()`) |
| **Available for Earmarking** | Revised Amount − CBA Requested: what a new CBA can still draw | `revised_amount − CBA Requested` |
| **RFA Approved** | Drawn from the CBAs by approved RFAs | the `converted` earmark rows of RFA allocations |
| **LAA Issued** | Drawn from the CBAs by issued LAAs | the `converted` earmark rows of LAA allocations |
| **Remaining Balance** | Revised Amount − CBA Requested | What is left of the revised budget once the CBAs' reservations — and what RFAs / LAAs drew from them, which is already inside CBA Requested — are taken off. The same figure as Available for Earmarking, the RFA / LAA page's Unallocated Amount and the Budget Revision form's Current balance (`BudgetLineBalanceSql`) |

Revised, Available and Remaining are all worked out, so three identities hold on every row, group and total:
`Approved + Revision = Revised` (the Revision is signed, so this is the Approved Amount less a decrease), `Revised − CBA = Available` and `Revised − CBA = Remaining` (so Available and Remaining are one figure).

> **Revised Amount is worked out, not read.** It used to be the stored `budget_line_items.revised_amount` column, which only a Budget Revision approval wrote (`0` for every other line, so those
> lines showed `0` Revised, Available and Remaining). The COB now computes it per line, so an untouched line shows its Approved Amount; the column is back as a **generated** one (`suggested_amount + adjustment`, `03 - alter_table/54 - budget_line_items_revised_amount_generated.sql`), so it is always right and nothing writes it.
>
> **`suggested_amount` is stable; revisions live in their own ledger.** Since the stable-`suggested_amount` model (`BudgetLineBalanceSql`) nothing moves `suggested_amount` after Budget Processing sets it: a CBA / CFA only
> reserves (an `active` earmark row), an RFA / LAA draws that reservation, and an approved Budget Revision writes one row per budget line to `fims.budget_line_item_revision_transaction` (the signed amount, the previous
> amount and the new amount — `BudgetRevisionService::approve()`). The Revision column is the sum of those rows. Approved = `suggested_amount`, Revised = Approved + Revision, Available = Revised − CBA Requested, and
> the same figures are what the approval checks use, so the COB and the Memo Request balance can never disagree. A revision may not take a line below what is held against it (CBAs + RFAs + LAAs).
> What an older approval had already done to `adjustment` is moved into the ledger by `03 - alter_table/52 - budget_line_item_revision_transaction_backfill.sql` (one `LEGACY` row per line); that script never
> touches `suggested_amount`.
> The ledger also keeps the line's **running adjustment**: each row's `adjustment` is the line's total after that transaction (the earlier total + its `amount`), and `budget_line_items.adjustment` is that total, written into the table by a trigger on the ledger
> (`03 - alter_table/53 - budget_line_item_revision_transaction_adjustment.sql`) — approving only inserts the ledger row. The COB still reads the ledger sum (the same number) and shows only the **latest** adjustment on a project.
> The RFA / LAA Monitoring page shows this same Revised Amount (see below), so the two pages always agree.

Rules worth knowing:
- A **deleted** memo request (`budget_allocation.deleted_at`) counts for nothing — the earmark rows of a deleted RFA / LAA survive in the ledger, so the allocation's `deleted_at` is what is filtered on.
- `balance_applied` no longer matters: every `active` / `converted` earmark row of a not-deleted allocation counts, which keeps `Available = Revised − CBA` true.
- A CBA fully drawn by RFAs / LAAs still shows its original amount in CBA Requested (its reservation shrinks as it is drawn, the RFA / LAA rows that drew it add up to the rest).
- Carry Over Obligation, Obligation and Disbursement are **not** part of the COB table any more: nothing writes `disbursement`, and `account_payable` is only filled by CBA / CFA approvals — it was a CBA figure under another name.

### Five more figures — for the RFA / LAA Monitoring page

`CobService` also sends five figures per row, group and total (`AMOUNT_KEYS` goes on after the eight — and then the two parts of Revision / Realignment, for its tooltip) for `rfa-laa-monitoring-page.php`, whose columns are
Revised Amount · RFA / LAA Amount · Disbursement · Unallocated Amount · Account Payable. The COB page, its View modal and its report name the keys they show,
so they are unaffected.

| Key | Per budget line |
|---|---|
| `revised_computed` | The COB page's Revised Amount, unchanged (`Approved + Revision` = `suggested_amount + the ledger total`): the budget as FMD suggested it, net of the approved revisions / realignments (the revision is signed, so a decrease lowers it). **This page's Revised Amount** — one definition, so it can never differ from the COB page's |
| `rfa_laa_amount` | `rfa_approved + laa_issued` |
| `disbursement` | What the disbursement vouchers connected to the line's RFAs / LAAs (`budget_allocation.dv_id`) have disbursed: each voucher's `gross_amount` spread over the draws of the RFAs / LAAs that point at it, in proportion to what each drew, so it is never counted twice and adds back up to the voucher. Cancelled / rejected vouchers count for nothing. The stored `budget_line_items.disbursement` column, which nothing writes, is not used |
| `unallocated_amount` | `revised_computed − cba_requested` — the remaining balance, the same figure as the COB page's Remaining Balance |
| `account_payable` | `rfa_laa_amount − disbursement` — what the RFAs / LAAs have drawn that has not been disbursed yet. Worked out per line; the stored `budget_line_items.account_payable` column is not used |

Disbursement therefore comes from the vouchers, and is part of every project row, the TOTAL and the cards; Account Payable (`RFA / LAA Amount − Disbursement`) follows it.
Until an RFA / LAA has a voucher connected, its Disbursement is 0 and its Account Payable equals what it drew. A voucher shared by RFAs / LAAs of several projects is
split between them by what each drew, so the voucher row under an RFA shows the voucher's whole `gross_amount` while the project rows carry only their share.

**Under a project** (RFA / LAA Monitoring page only — `GET /cob/rollup?...&with_allocations=1`): the project row opens onto the RFAs and LAAs that drew against
its budget lines — one row each, `RFA / LAA Amount` being the sum of that allocation's 'converted' earmark rows on the project's lines — and an RFA row opens onto
its disbursement voucher (`budget_allocation.dv_id`; the voucher is fetched from `fims.disbursement_vouchers` by that `dv_id`; `Disbursement` = the voucher's `gross_amount`, with its status, date and claimant). Only RFAs are linked to a
voucher, but the page does not depend on that: an RFA or LAA row with a `dv_id` drops down to its voucher (a `1 DV` tag marks it, `No DV` the rest). The voucher is shown whatever its status, a cancelled or rejected one too, with its status pill. These rows are detail: they are added
after the totals, so no figure above them changes. If they cannot be read the tree is returned without them and the page says so.

**Revisions and realignments under a project** (RFA / LAA Monitoring page only — `GET /cob/rollup?...&with_revisions=1`; the COB page shows the totals in its Revision / Realignment column and lists nothing under a project): the project row (a COE request, for the COE half) also opens onto the approved Budget Revisions / Realignments
recorded against its budget lines, one row per request, read from the ledger `fims.budget_line_item_revision_transaction`. A **revision** is one row (REV). A **realignment** is two: an *out* row (REA OUT, negative) on
the line that gave funds up and an *in* row (REA IN, positive) on the line that received them — so when both lines are in the same project both rows show under it, although the project's own figures net to zero
(−X + X); when they are in different projects the *out* row is under one and the *in* row under the other, and both projects' figures change. Each row names its budget line(s) and the reason. Its signed amount is in the
**Revised Amount** column (it is the change that was made to it), dashes elsewhere. A row for what was approved before the ledger
existed (the one `LEGACY` row per line) reads "Revisions / realignments approved before the ledger". Like the RFA / LAA rows these are detail rows added after the totals, so no figure changes, and the rows of a
project add up to its Revision / Realignment figure. They come before the project's RFAs / LAAs. If they cannot be read the tree is returned without them and the page says so (`revision_error`).

## The page

There is no tab bar: the page opens straight on **Per Program** (a stale `?tab=` in the URL is ignored), under one set of filters (Fiscal Year, Type of Fund, Component, Region, Program, Search, Amount
Unit) and four KPI cards (Approved Requests, Revised Amount, Available for Earmarking, Remaining Balance). The Generate Report button is hidden by the `$_cob_showReport` flag at the top of `cob-page.php`.

| View | Shows |
|---|---|
| **Per Program** (the one on the page) | Program → Sub-Program → Region → Project. The row under the Sub-Program is the **Region attached to the project** (`fims.project.rc_id`, picked on the Budget Request / Supplemental project card, joined to `fims.responsibility_code`): its code is the RC id and its name the office (rows in RC-code order). A project with no Region (an older one) and a COE line fall back to the office that filed the request (`fims.budget_req.rc_id`), which is what this row used to be for every project; a line with neither is kept, last, as "No originating unit". The office is named once, on its row, not again under each project. The Region filter, the Region drop-down and the Search box use the same office; WHO may see a request (the caller's RC scope) is still the filing office |
| Per Region, Per Type of Fund, Per Component | Still served by `GET /cob/rollup?layout_by=region\|fund_source\|component` (and their panels are still in the markup, hidden and never loaded), but nothing on the page switches to them |

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

## Supplemental projects (new projects released by a SARO)

A **Supplemental** request (Budget Amendment → the Supplemental tab, `BudgetRevisionService::approveSupplemental()`) adds a NEW project that is not in the approved COB. Once **approved** it is an ordinary
project of the filing office's Approved budget request for the SARO's fiscal year, so it appears **in its Program / Sub-Program under CO** — nothing else in the COB changes to show it, and **the SARO number is not
shown here**. Its budget lines are created with `suggested_amount` 0 and their amount is written to the ledger as `SUPPLEMENTAL` rows, so the amount is in **Revision / Realignment (Amendment)** and the
**Approved Amount is 0**; Revised Amount = that amount, and it flows into the parents' Revised Amount, Remaining Balance and the TOTAL like any other revision. On the RFA / LAA page the detail row reads
"Supplemental #id" (code `SUP`). Its budget lines are **tagged** (`fims.budget_line_items.supplemental_revision_id` = the request that created them, `03 - alter_table/63`): COB Monitoring and RFA / LAA Monitoring show them, while the Budget Requirements (proposal) screens — the list, **View Proposal** and its Excel / print export — leave them out, because the project was never part of a proposal (see `app/routes/README.md`, `budget_req.php`). The level between the Sub-Program and the Project is the project's Region (see Per Program above), so a supplemental project sits under the Region picked on its card.

## History

- First version: *Total | Fund Balance | Adjustment | Carry Over Obligation* over Type of Fund / Component / Region trees down to the account. Replaced by the
  Corporate Operating Budget Monitoring sheet's eight columns: the account level and the Office level are gone (an account is still in the View modal), and Sub-Program is a level.
- Second pass: the Approved Amount is `suggested_amount` as it stands (it had been rebuilt as the amount before CBAs and revisions), and the row / column colour coding was removed.
- Third pass (revised amount fix): "as it stands" turned out wrong for any line with a Budget Revision or a CBA, because `suggested_amount` is the live balance — the revision was subtracted from a
  figure that already contained it, and a CBA was taken off twice. The Approved Amount is rebuilt again as the amount before revisions and CBAs (`suggested_amount + CBA Requested − adjustment`) and the
  Revised Amount is `Approved + adjustment`. The RFA / LAA Monitoring page's Revised Amount (`revised_computed`, it had been `proposed_amount + adjustment`) is now that same figure: the proposed
  amount ignores what FMD suggested below the proposal, so the two pages differed by exactly that (₱21,000,000 on the dev data: ₱1,000,000 in CO, ₱20,000,000 in COE) and the RFA / LAA page's
  Unallocated Amount counted the cut as still drawable. CO / COE highlighting is unrelated to this.
- Revision / Realignment: ONE column. A **project** shows only its **latest** approved adjustment, **signed and coloured** (**+** green / **−** red); **parent rows and the TOTAL show nothing** in it. A cumulative figure hid the realignment on the project —
  one with earlier revisions (Project 1: +10,000,000) that then gave funds up (−5,000,000) read +5,000,000, so the budget that gave them up looked like it had received them — so the project rows show the latest (Project 1 *Realignment −5,000,000.00*, the budget
  that received the funds *Realignment +5,000,000.00*) and no total is shown above them. Revised Amount is still Approved + the net of all the adjustments, worked out per line from the same ledger.
- Revision ledger: approving a Budget Revision / Realignment records one transaction per line in `fims.budget_line_item_revision_transaction` — the amount, the previous amount and the new amount — and the COB's
  Revision column is the sum of that table. Together with the stable-`suggested_amount` model (a CBA only reserves, `suggested_amount` is never moved) the COB now reads `BudgetLineBalanceSql::exprs()`: Approved =
  `suggested_amount` (the `+ CBA Requested − adjustment` rebuild is gone), Revision = the ledger total, Revised = Approved + Revision, CBA Requested = reserved + committed earmarks. Older adjustments are moved
  into the ledger by `03 - alter_table/52 - budget_line_item_revision_transaction_backfill.sql`.
- Remaining Balance: it was Revised − RFA − LAA, so a CBA never lowered it (only Available). It is now **Revised − CBA Requested** (the earmark table's active + converted rows), the one remaining-balance
  definition (`BudgetLineBalanceSql`) behind the COB, the RFA / LAA page's Unallocated Amount (`revised_computed − cba_requested`) and the Budget Revision form's Current balance — which now also takes off
  what CBAs hold, so a revision's New Amount is the line's new remaining balance.
- CO / COE: the COB and RFA / LAA Monitoring pages separate **CO** (accounts `1…`, the programs' projects) from **COE** (no program; PS `5-01…` / MOOE `5-02…`), the way the
  Budget Proposal "View as COB" does; the "No program" / "No project" rows of those pages are gone.
