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
| **Disbursed** | Σ gross amount of the **RELEASED** voucher linked to each SARO (`fund_tracking.dv_id`, `03 - alter_table/61`) | `null` — Trust Funds are not linked to vouchers; the page shows "—", not 0 |
| Balance | available − disbursed | available |
| Utilization | disbursed / available × 100 (null when there is nothing to measure against) | null |
| Unreleased | SARO released − NCA released | — |

NG / SUPP also come split **per Program**: the SARO's `sub_program` → `fims.sub_program` → `fims.program` (a SARO with no sub-program is under
"No program"; an NCA goes under its SARO's program). Per program: SARO released, NCA released, Disbursed, Undisbursed NCA (NCA released −
disbursed), Utilization (disbursed / NCA released). TRUST comes per trust fund, with the ORs under it (`receipts`, `or_count`); ORs with
no trust fund are in the totals only.

Without `fund_tracking.dv_id` (the alter file has not been run) every `disbursed` is `null` — shown as "—".

## API (`app/routes/status_of_fund.php`, behind the JWT middleware)

- `GET /status_of_fund/overview?fiscal_year=` — `data.fiscal_years` (years a live record carries, newest first), `data.sources[]` (one summary row per
  fund source: the figures above plus the counts) and `data.totals` (`sources`, `available`, `disbursed`, `balance`, `utilization`).
- `GET /status_of_fund/source/{key}?fiscal_year=` — one fund source in full: the same row plus `programs[]` (NG / SUPP) or `trust_funds[]` (TRUST).
  404 for a key other than `NG` / `TRUST` / `SUPP`.
- `fiscal_year` omitted = every year combined.

The SARO → NCA tree on the page is the existing `GET /fund_tracking/list` (`type=SARO&type=NCA&roots_only=1&root_type=SARO`, plus
`exclude_nature_of_fund=SUPPLEMENTAL` for NG Subsidy or `nature_of_fund=SUPPLEMENTAL` for Supplemental Funds, and `fiscal_year`).

## The page

- **Overview**: three cards (fund sources, funds available, disbursed), filters (Fiscal Year — newest year by default — and Fund Source), the
  Fund sources table (open / report icons per row, a TOTAL row), and the **Utilization Report** of all the fund sources.
- **Detail** (`#NG`, `#TRUST`, `#SUPP` in the URL; the chips switch source): cards, the per-program (or per-trust-fund) table, and — for NG / SUPP — the
  SARO → NCA tree table (the look of `saro-registry-page.php`, without its Actions column, "+" buttons and forms: this page only reads; paged, 10 SAROs).
- **Utilization Report** (modal): the sheet — letterhead, per-program (per fund source on the overview, per trust fund for Trust Funds) table, summary,
  signature lines. **Print** sends only the sheet to the printer through a hidden frame (Save as PDF from the print dialog); **Excel (.csv)** downloads the table.

## Not built (no data behind it)

The mockups also showed requests *waiting for assignment / review* per fund source, a Corporate Funds source (actual receipts), NDRRMC, Trust Funds split
by book (NGC / PPMO), a fund code / book / status per source, and a GAA allocation. None of these exist in the database today: RFAs / LAAs carry no fund
source (only a free-text `fund_source_name` on the CFA / CBA prints), and there is no fund-source master. They need a decision and a table before they can be added.

## Checked

`StatusOfFundService` against a real PostgreSQL loaded with every `_assets/sql` file (53 checks: year filter, NCA following its SARO, soft-deleted records,
orphan NCA / OR, only RELEASED vouchers counted, program split, trust split, unknown key, empty year); the page's JavaScript in jsdom with the service's real
payloads (105 checks: both views, filters, tree expand / collapse, escaping, report, CSV, deep link, API failure) and rendered in Chrome with the real `nha-fims.css`.
