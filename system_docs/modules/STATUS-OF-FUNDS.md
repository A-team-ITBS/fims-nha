# Status of Funds

Registry → **Status of Funds**. Read-only: where each fund source stands — what is available, what has been disbursed — with the
records behind it, and a printable Utilization Report. Page `app/pages/status-of-fund-page.php` (view `views/StatusOfFundPage.php`,
controller `StatusOfFundPageController`, menu item 972 under Registry 551), API `app/routes/status_of_fund.php`, service
`app/services/StatusOfFundService.php`. **No new table and no SQL file** — it reads what the other fund pages keep.

## What a "fund source" is

FIMS has no fund-source master table (`fund_source` is plain text on `fims.bank_accounts` and `fims.disbursement_vouchers`), so a
fund source here is one of three groups of `fims.fund_tracking` records:

| Key | Fund source | Records |
|---|---|---|
| `NG` | NG Subsidy | SARO + NCA whose Nature of Fund is **not** SUPPLEMENTAL |
| `TRUST` | Trust Funds | TRUST FUND records and the OR records under them |
| `SUPP` | Supplemental Funds | SARO + NCA whose Nature of Fund is SUPPLEMENTAL |

An NCA follows its SARO's Nature of Fund and Program (`parent_id`); an NCA with no SARO uses its own and has no program. A record
of any other tracking type belongs to no fund source. Soft-deleted records are never counted. Each record counts in its own
`fiscal_year`.

## The figures

| Figure | NG / SUPP | TRUST |
|---|---|---|
| SARO released | Σ SARO amounts | — |
| NCA released | Σ NCA amounts | — |
| Receipts | — | Σ OR amounts |
| **Funds available** | NCA released | Receipts |
| **Disbursed** | Σ gross amount of the **RELEASED** vouchers, each counted ONCE — see "Where a voucher counts" below | the RFA / LAA part only; `null` (shown "—", not 0) until an RFA / LAA of the Trust Fund type of fund has a released voucher |
| Balance | available − disbursed | available |
| Utilization | disbursed / available × 100 (null when there is nothing to measure against) | null |
| Unreleased | SARO released − NCA released | — |

NG / SUPP also come split **per Program**: the SARO's `sub_program` → `fims.sub_program` → `fims.program` (a SARO with no sub-program is under
"No program"; an NCA goes under its SARO's program). Per program: SARO released, NCA released, Disbursed, Undisbursed NCA (NCA released −
disbursed), Utilization (disbursed / NCA released). TRUST comes per trust fund, with the ORs under it (`receipts`, `or_count`); ORs with
no trust fund are in the totals only.

Without `fund_tracking.dv_id` (the alter file has not been run) the SARO part is missing: `disbursed` is only what the RFAs / LAAs brought, `null` when that is nothing.

## Where a voucher counts

A RELEASED disbursement voucher (`disbursement_vouchers.status_id` = RELEASED — the check is released) counts in a fund source by the first link it has. It is never counted twice.

1. **A SARO** — the voucher form's SARO field (`fund_tracking.dv_id`). Its gross amount is under that SARO's fund source (NG / SUPP) and program.
2. **An RFA / LAA** — the RFA / LAA the voucher is connected to (`budget_allocation.dv_id`, the voucher form's RFA field) drew from budget lines (`budget_line_item_earmark`, status `converted`). The voucher's gross amount is spread over those lines in proportion to what each drew — the same split the RFA / LAA Monitoring page uses — and each line says where its share goes:

   | The budget line | Fund source |
   |---|---|
   | created by a supplemental request (`budget_line_items.supplemental_revision_id`) | Supplemental Funds |
   | Type of Fund `NG SUPPORT - GAA / NF / UF` (`NG SUPPORT%`, `NG SUBSIDY%`) | NG Subsidy |
   | Type of Fund `TRUST FUND` | Trust Funds |
   | any other (`CORPORATE RECEIPT`, or none) | none on this page |

   The share is under the budget line's own program (`budget_req_program`) and in the fiscal year of its budget request, so the Fiscal Year filter and the per-program table follow the budget request, not the SARO.
3. **Neither** — counted nowhere. The page says so (below).

`vouchers` (in both API answers, always over every fiscal year) reconciles it: `released_count` / `released` (all RELEASED vouchers), `counted` (in a fund source), `not_counted`, `unlinked_count` / `unlinked` (no SARO and no live RFA / LAA — the ones to fix), `other_fund` (went through a Type of Fund that is not a fund source here).

## API (`app/routes/status_of_fund.php`, behind the JWT middleware)

- `GET /status_of_fund/overview?fiscal_year=` — `data.fiscal_years` (years a live record carries, newest first), `data.sources[]` (one summary row per
  fund source: the figures above plus the counts, and `disbursed_rfa` — the part of `disbursed` that came through an RFA / LAA), `data.totals`
  (`sources`, `available`, `disbursed`, `balance`, `utilization`) and `data.vouchers` (the reconciliation above).
- `GET /status_of_fund/source/{key}?fiscal_year=` — one fund source in full: the same row plus `programs[]` (NG / SUPP) or `trust_funds[]` (TRUST), and `vouchers`.
  404 for a key other than `NG` / `TRUST` / `SUPP`.
- `fiscal_year` omitted = every year combined.

The SARO → NCA tree on the page is the existing `GET /fund_tracking/list` (`type=SARO&type=NCA&roots_only=1&root_type=SARO`, plus
`exclude_nature_of_fund=SUPPLEMENTAL` for NG Subsidy or `nature_of_fund=SUPPLEMENTAL` for Supplemental Funds, and `fiscal_year`).

## The page

- **Overview**: three cards (fund sources, funds available, disbursed), filters (Fiscal Year — newest year by default — and Fund Source), the
  Fund sources table (open / report icons per row, a TOTAL row), and the **Utilization Report** of all the fund sources.
- **Detail** (`#NG`, `#TRUST`, `#SUPP` in the URL; the chips switch source): cards, the per-program (or per-trust-fund) table, and — for NG / SUPP — the
  SARO → NCA tree table (the look of `saro-registry-page.php`, without its Actions column, "+" buttons and forms: this page only reads; paged, 10 SAROs).
- **A notice** (amber, above the filters, not printed) when released vouchers are counted nowhere: how much, how many have no SARO and no RFA / LAA ("pick the SARO on the voucher, or connect its RFA / LAA"), and how much went through a Type of Fund that is not a fund source here. It is what to look at when a voucher was released and nothing moved.
- **Utilization Report** (modal): the sheet — letterhead, per-program (per fund source on the overview, per trust fund for Trust Funds) table, summary,
  signature lines. **Print** sends only the sheet to the printer through a hidden frame (Save as PDF from the print dialog); **Excel (.csv)** downloads the table.

## Not built (no data behind it)

The mockups also showed requests *waiting for assignment / review* per fund source, a Corporate Funds source (actual receipts), NDRRMC, Trust Funds split
by book (NGC / PPMO), a fund code / book / status per source, and a GAA allocation. None of these exist in the database today (a Corporate Funds source would need its receipts; a Corporate Receipt RFA is only reported as `other_fund`): RFAs / LAAs carry no fund
source (only a free-text `fund_source_name` on the CFA / CBA prints), and there is no fund-source master. They need a decision and a table before they can be added.

## Checked

`StatusOfFundService` against a real PostgreSQL loaded with every `_assets/sql` file (53 checks: year filter, NCA following its SARO, soft-deleted records,
orphan NCA / OR, only RELEASED vouchers counted, program split, trust split, unknown key, empty year; and 26 for the RFA / LAA path: a voucher with no SARO counted
through its RFA by the budget line's type of fund, a supplemental line as Supplemental, one RFA over lines of two fund sources split in proportion, a voucher with a SARO
AND an RFA counted once, an unreleased / deleted-RFA voucher not counted, the fiscal year of the budget request, the reconciliation figures, a database without the
supplemental tag or the SARO link); the page's JavaScript in jsdom with the service's real
payloads (105 checks: both views, filters, tree expand / collapse, escaping, report, CSV, deep link, API failure) and rendered in Chrome with the real `nha-fims.css`.
