<?php
/**
 * Swaggerize Class
 *
 * Generates OpenAPI 3.0 JSON documentation from PHP route files
 * using PHP reflection to dynamically analyze service classes and route definitions.
 *
 * Systems supported:
 *   UAC          → uac schema          (User Access Control & Authentication)
 *   FMD          → fmd schema          (Financial Management Department)
 *   Accounting   → accounting schema   (Accounting Department)
 *   Treasury     → treasury schema     (Treasury Department)
 *   Notification → uac.notifications   (System-wide notifications)
 *
 * DB connection map (nha-fims):
 *   DB        → uac schema
 *   nhafims1  → core / shared
 *   nhafims2  → funding
 *   nhafims3  → budget_planning
 *   nhafims4  → budget_allocation
 *   nhafims5  → obligation
 *   nhafims6  → accounting
 *   nhafims7  → collection
 *   nhafims8  → revenue
 *   nhafims9  → treasury
 */

class Swaggerize {
    private $systems = [];
    private $title = 'NHA-FIMS API Documentation';
    private $description = 'National Housing Authority – Financial Information Management System';
    private $version = '1.0.0';
    private $basePath = '/{system}/api';
    private $servers = [];
    private $paths = [];
    private $components = [];
    private $tags = [];
    private $serviceClasses = [];
    private $routeDefinitions = [];
    private $pruneUnusedSchemas = false;

    public function __construct($systems = []) {
        $this->systems = is_array($systems) ? $systems : [$systems];
        $this->initializeComponents();
        $this->loadServiceClasses();
        $this->parseRouteFiles();
    }

    /**
     * Set API title
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    /**
     * Set API description
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }

    /**
     * Set API version
     */
    public function setVersion($version) {
        $this->version = $version;
        return $this;
    }

    /**
     * Set base path
     */
    public function setBasePath($basePath) {
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * Add server
     */
    public function addServer($url, $description = '') {
        $this->servers[] = [
            'url' => $url,
            'description' => $description
        ];
        return $this;
    }

    /**
     * Add tag
     */
    public function addTag($name, $description = '') {
        $this->tags[] = [
            'name' => $name,
            'description' => $description
        ];
        return $this;
    }

    /**
     * Initialize common components
     */
    private function initializeComponents() {
        $this->components = [
            'schemas' => [

                // ── Generic response envelopes ─────────────────────────────────
                'Error' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'message' => ['type' => 'string',  'example' => 'Error message'],
                        'error'   => ['type' => 'string',  'example' => 'Detailed error information']
                    ]
                ],
                'Success' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'message' => ['type' => 'string',  'example' => 'Operation successful'],
                        'data'    => ['type' => 'object',  'description' => 'Response data']
                    ]
                ],
                'PaginatedList' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'data'    => ['type' => 'array', 'items' => ['type' => 'object']],
                        'total'   => ['type' => 'integer', 'example' => 100],
                        'page'    => ['type' => 'integer', 'example' => 1],
                        'limit'   => ['type' => 'integer', 'example' => 25]
                    ]
                ],

                // ── LIBIS integration schemas ──────────────────────────────────
                'LibisChartOfAccount' => [
                    'type' => 'object',
                    'description' => 'One row of the LIBIS chart-of-accounts feed. '
                                   . 'Represents a GL account (from shared.chart_of_accounts) optionally joined '
                                   . 'with a Subsidiary Ledger detail (from accounting.sl_details). When there '
                                   . 'is no SL row, sl_detail_id and sl_code are null and ngas_code ends with "-00".',
                    'properties' => [
                        'account_id'    => ['type' => 'integer', 'example' => 1234, 'description' => 'shared.chart_of_accounts.account_id'],
                        'uacs_code'     => ['type' => 'string',  'example' => '1-01-01-010', 'description' => '4-part UACS chart-of-accounts code — shared.chart_of_accounts.account_code (the table is labelled "UACS Chart of Accounts").'],
                        'ngas_code'     => ['type' => 'string',  'example' => '1-01-01-010-01', 'description' => '5-part composite: uacs_code + "-" + sl_code (or uacs_code + "-00" when GL-only). This is the value LIBIS matches against LIBIS_ACCT_TYPES.'],
                        'account_title' => ['type' => 'string',  'example' => 'Cash - Collecting Officers', 'description' => 'GL parent account title — ALWAYS shared.chart_of_accounts.account_title, even for SL-backed rows. Gives LIBIS the GL context that edesc/ldesc hide when an SL row is present.'],
                        'sl_detail_id'  => ['type' => 'integer', 'nullable' => true, 'example' => 42,  'description' => 'accounting.sl_details.sl_detail_id — null for GL-only rows.'],
                        'sl_code'       => ['type' => 'string',  'nullable' => true, 'example' => '01', 'description' => '5th segment of ngas_code — null for GL-only rows.'],
                        'edesc'         => ['type' => 'string',  'example' => 'Cash - Collecting Officer', 'description' => 'English description (SL sl_name if available, else GL account_title).'],
                        'ldesc'         => ['type' => 'string',  'example' => 'Cash held by collecting officer', 'description' => 'Long description (SL sub_sl_name if available, else GL account_description).'],
                        'selectable'    => ['type' => 'boolean', 'example' => true, 'description' => 'True only if all involved rows are active AND coa.allow_transactions is true.'],
                        'account_type'  => ['type' => 'string', 'nullable' => true, 'example' => '557', 'description' => 'FIMS\'s own record of the matching LIBIS ACCT_TYPE (ACCOUNT TYPE_1.pdf), sourced from accounting.sl_masters.libis_acct_type for SL-backed rows or shared.chart_of_accounts.libis_acct_type for GL-only rows. Single value for SL rows; comma-separated list for a GL-only row matched by more than one ACCT_TYPE. Null when FIMS has no recorded LIBIS mapping for this row — LIBIS may still cross-check against its own LIBIS_ACCT_TYPES table by joining on ngas_code.'],
                    ],
                    'required' => ['account_id', 'uacs_code', 'ngas_code', 'account_title', 'edesc', 'ldesc', 'selectable'],
                ],
                'LibisChartOfAccountsResponse' => [
                    'type' => 'object',
                    'description' => 'Envelope returned by GET /LIBIS/api/v1/chart-of-accounts. '
                                   . 'Mirrors the exact JSON produced by LibisChartOfAccountsService::getChartOfAccounts.',
                    'properties' => [
                        'status'  => ['type' => 'integer', 'example' => 200],
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data'    => [
                            'type' => 'object',
                            'properties' => [
                                'total'             => ['type' => 'integer', 'example' => 1523, 'description' => 'Total rows matching the filter (before pagination).'],
                                'page'              => ['type' => 'integer', 'example' => 1],
                                'per_page'          => ['type' => 'integer', 'example' => 100, 'description' => 'Clamped to the range [1, 500].'],
                                'chart_of_accounts' => [
                                    'type'  => 'array',
                                    'items' => ['$ref' => '#/components/schemas/LibisChartOfAccount'],
                                ],
                            ],
                            'required' => ['total', 'page', 'per_page', 'chart_of_accounts'],
                        ],
                    ],
                    'required' => ['status', 'success', 'data'],
                ],

                // ── LIBIS bank-listing schemas (API 6) ─────────────────────────
                'LibisBankAccount' => [
                    'type' => 'object',
                    'description' => 'One NHA bank account from shared.bank_accounts, joined to shared.fund_sources '
                                   . 'and uac.departments for display labels. Returned inside '
                                   . '`bank_accounts[]` by GET /LIBIS/api/v1/bank-accounts.',
                    'properties' => [
                        'bank_account_id'        => ['type' => 'integer', 'example' => 1, 'description' => 'shared.bank_accounts.bank_account_id — the value LIBIS stores when tagging a collection deposit.'],
                        'account_number'         => ['type' => 'string',  'example' => '1892-1001-63'],
                        'account_name'           => ['type' => 'string',  'example' => 'NHA Main Operating Account'],
                        'bank_name'              => ['type' => 'string',  'example' => 'Land Bank of the Philippines', 'description' => 'Bank institution name. Free text — FIMS has no separate bank master table.'],
                        'bank_branch'            => ['type' => 'string',  'nullable' => true, 'example' => 'UN Avenue, Manila'],
                        'account_type'           => ['type' => 'string',  'nullable' => true, 'example' => 'CURRENT', 'description' => 'e.g. CURRENT, SAVINGS. No DB-level constraint — treat as free text.'],
                        'account_classification' => ['type' => 'string',  'nullable' => true, 'example' => 'NG Subsidy'],
                        'fund_source_id'         => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                        'fund_source_code'       => ['type' => 'string',  'nullable' => true, 'example' => 'NG-GAA',  'description' => 'shared.fund_sources.source_code — null when fund_source_id is unset or unresolvable.'],
                        'fund_source_name'       => ['type' => 'string',  'nullable' => true, 'example' => 'NG Support - GAA'],
                        'region_id'              => ['type' => 'integer', 'nullable' => true, 'example' => 100, 'description' => 'Despite the name this holds a uac.departments.department_id — that is how every FIMS service resolves it.'],
                        'region_code'            => ['type' => 'string',  'nullable' => true, 'example' => 'FMD', 'description' => 'uac.departments.department_code — null when region_id is unset or unresolvable.'],
                        'region_name'            => ['type' => 'string',  'nullable' => true, 'example' => 'Financial Management Department', 'description' => 'uac.departments.department_name — the same label FIMS screens show as "region".'],
                        'is_main_depository'     => ['type' => 'boolean', 'example' => true, 'description' => 'True for the authority-wide main depository account.'],
                        'status'                 => ['type' => 'string',  'example' => 'ACTIVE', 'description' => 'ACTIVE / INACTIVE / CLOSED by convention — not enforced by a DB constraint.'],
                    ],
                    'required' => ['bank_account_id', 'account_number', 'account_name', 'bank_name', 'is_main_depository', 'status'],
                ],
                'LibisBankAccountsResponse' => [
                    'type' => 'object',
                    'description' => 'Envelope returned by GET /LIBIS/api/v1/bank-accounts (API 6). '
                                   . 'Mirrors the exact JSON produced by LibisBankAccountsService::getBankAccounts. '
                                   . '`banks[]` is a DISTINCT bank-institution roll-up over the WHOLE filtered set '
                                   . '(not just the current page), so LIBIS can populate a bank dropdown and an '
                                   . 'account dropdown from a single call.',
                    'properties' => [
                        'status'  => ['type' => 'integer', 'example' => 200],
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data'    => [
                            'type' => 'object',
                            'properties' => [
                                'total'    => ['type' => 'integer', 'example' => 4, 'description' => 'Total accounts matching the filter (before pagination).'],
                                'page'     => ['type' => 'integer', 'example' => 1],
                                'per_page' => ['type' => 'integer', 'example' => 50, 'description' => 'Clamped to the range [1, 500].'],
                                'banks'    => [
                                    'type'        => 'array',
                                    'description' => 'DISTINCT bank institutions across the filtered set, alphabetical.',
                                    'items'       => [
                                        'type' => 'object',
                                        'properties' => [
                                            'bank_name'     => ['type' => 'string',  'example' => 'Land Bank of the Philippines'],
                                            'account_count' => ['type' => 'integer', 'example' => 2, 'description' => 'Number of matching accounts held at this bank.'],
                                        ],
                                        'required' => ['bank_name', 'account_count'],
                                    ],
                                ],
                                'bank_accounts' => [
                                    'type'  => 'array',
                                    'items' => ['$ref' => '#/components/schemas/LibisBankAccount'],
                                ],
                            ],
                            'required' => ['total', 'page', 'per_page', 'banks', 'bank_accounts'],
                        ],
                    ],
                    'required' => ['status', 'success', 'data'],
                ],

                // ── LIBIS invoice-inventory schemas (SRS 3.1.2.4) ─────────────
                // Sub-schemas: LibisInvoiceSeries (row shape used by /list) +
                // LibisInvoiceDistribution (per-officer stock breakdown).
                // Response envelopes: Register, DeliveryRecord, List, TagStatus.
                'LibisInvoiceDistribution' => [
                    'type' => 'object',
                    'description' => 'Per-officer stock breakdown for one invoice series, as returned inside `distribution[]` in the /list response. '
                                   . 'Derived from `treasury.v_invoice_stock_summary` joined with `treasury.nha_accountable_officers`.',
                    'properties' => [
                        'officer_id'        => ['type' => 'integer', 'example' => 78],
                        'officer_name'      => ['type' => 'string',  'example' => 'Juan Dela Cruz'],
                        'officer_code'      => ['type' => 'string',  'example' => 'NCR-001'],
                        'region'            => ['type' => 'string',  'example' => 'NCR', 'description' => 'Officer region name, or office_type fallback when region_name is null.'],
                        'booklets_received' => ['type' => 'integer', 'example' => 50, 'description' => 'Total booklets delivered to this officer from this series.'],
                        'booklets_used'     => ['type' => 'integer', 'example' => 12, 'description' => 'Booklets consumed (issuances recorded).'],
                        'booklets_balance'  => ['type' => 'integer', 'example' => 38, 'description' => 'Remaining booklets on hand (received minus used).'],
                        'is_low_stock'     => ['type' => 'boolean', 'example' => false, 'description' => 'True when booklets_balance ≤ series.low_stock_threshold.'],
                    ],
                    'required' => ['officer_id', 'officer_name', 'officer_code', 'region', 'booklets_received', 'booklets_used', 'booklets_balance', 'is_low_stock'],
                ],

                'LibisInvoiceSeries' => [
                    'type' => 'object',
                    'description' => 'One row from treasury.nha_invoice_series — a registered invoice/OR booklet series with its aggregate stock. '
                                   . 'Returned inside `series[]` by the /list endpoint.',
                    'properties' => [
                        'series_id'           => ['type' => 'integer', 'example' => 15, 'description' => 'System-generated ID. Use this value when calling /tag-status.'],
                        'series_code'         => ['type' => 'string',  'example' => 'OR-2026-NCR-01', 'description' => 'Series identifier (SRS: INVOICE_CODE).'],
                        'invoice_type'        => ['type' => 'string',  'example' => 'OR', 'description' => 'Type of invoice (e.g. OR, PAR).'],
                        'year_issued'         => ['type' => 'integer', 'example' => 2026, 'description' => 'Fiscal year this series belongs to.'],
                        'series_from'         => ['type' => 'integer', 'example' => 100001, 'description' => 'Starting OR serial number (SRS: INVOICE_FROM / OR_FROM).'],
                        'series_to'           => ['type' => 'integer', 'example' => 100500, 'description' => 'Ending OR serial number (SRS: INVOICE_TO / OR_TO).'],
                        'status'              => ['type' => 'string',  'enum' => ['ACTIVE', 'DEPLETED'], 'example' => 'ACTIVE'],
                        'low_stock_threshold' => ['type' => 'integer', 'example' => 1200, 'description' => 'Booklet count that triggers a low-stock alert.'],
                        'total_delivered'     => ['type' => 'integer', 'example' => 50, 'description' => 'Total booklets distributed across all officers.'],
                        'officer_count'       => ['type' => 'integer', 'example' => 1, 'description' => 'Number of officers holding booklets from this series.'],
                        'distribution'        => [
                            'type'        => 'array',
                            'description' => 'Per-officer stock breakdown. May be empty.',
                            'items'       => ['$ref' => '#/components/schemas/LibisInvoiceDistribution'],
                        ],
                    ],
                    'required' => ['series_id', 'series_code', 'invoice_type', 'year_issued', 'series_from', 'series_to', 'status', 'low_stock_threshold', 'total_delivered', 'officer_count', 'distribution'],
                ],

                'LibisAccountableFormRegisterResponse' => [
                    'type' => 'object',
                    'description' => 'Envelope returned by POST /LIBIS/api/v1/invoice-inventory/register (API 2, FR-TR-001). '
                                   . 'Echoes back the persisted series with status always "ACTIVE".',
                    'properties' => [
                        'status'  => ['type' => 'integer', 'example' => 200],
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data'    => [
                            'type' => 'object',
                            'properties' => [
                                'series_id'           => ['type' => 'integer', 'example' => 15, 'description' => 'System-generated ID. Store this — required for /deliver and /tag-status.'],
                                'series_code'         => ['type' => 'string',  'example' => 'OR-2026-NCR-01'],
                                'invoice_type'        => ['type' => 'string',  'example' => 'OR'],
                                'year_issued'         => ['type' => 'integer', 'example' => 2026],
                                'series_from'         => ['type' => 'integer', 'example' => 100001],
                                'series_to'           => ['type' => 'integer', 'example' => 100500],
                                'low_stock_threshold' => ['type' => 'integer', 'example' => 1200],
                                'status'              => ['type' => 'string',  'enum' => ['ACTIVE'], 'example' => 'ACTIVE', 'description' => 'Always ACTIVE on creation.'],
                            ],
                            'required' => ['series_id', 'series_code', 'invoice_type', 'year_issued', 'series_from', 'series_to', 'low_stock_threshold', 'status'],
                        ],
                        'message' => ['type' => 'string', 'example' => 'Invoice series registered successfully.'],
                    ],
                    'required' => ['status', 'success', 'data', 'message'],
                ],

                'LibisInvoiceDeliveryRecordResponse' => [
                    'type' => 'object',
                    'description' => 'Envelope returned by POST /LIBIS/api/v1/invoice-inventory/deliver (API 3, FR-TR-001). '
                                   . 'Echoes the persisted delivery record with status always "DELIVERED".',
                    'properties' => [
                        'status'  => ['type' => 'integer', 'example' => 200],
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data'    => [
                            'type' => 'object',
                            'properties' => [
                                'delivery_id'        => ['type' => 'integer', 'example' => 42, 'description' => 'System-generated delivery record ID.'],
                                'delivery_no'        => ['type' => 'string',  'example' => 'DEL-20260803-0017', 'description' => 'Auto-generated delivery reference number.'],
                                'series_id'          => ['type' => 'integer', 'example' => 15, 'description' => 'Invoice series ID the booklets came from.'],
                                'officer_id'         => ['type' => 'integer', 'example' => 78, 'description' => 'Accountable officer receiving the booklets.'],
                                'delivery_date'      => ['type' => 'string',  'format' => 'date', 'example' => '2026-08-03'],
                                'booklets_delivered' => ['type' => 'integer', 'example' => 50, 'description' => 'Number of booklets in this delivery.'],
                                'series_from'        => ['type' => 'integer', 'example' => 100001, 'description' => 'Starting serial of this delivery batch (may be 0 if not provided).'],
                                'series_to'          => ['type' => 'integer', 'example' => 100050, 'description' => 'Ending serial of this delivery batch (may be 0 if not provided).'],
                                'delivery_type'      => ['type' => 'string',  'enum' => ['INITIAL', 'REPLENISHMENT', 'TRANSFER', 'RETURN'], 'example' => 'INITIAL'],
                                'raf_reference'      => ['type' => 'string',  'example' => 'RAF-2026-NCR-001', 'description' => 'Report of Accountable Forms document reference.'],
                                'status'             => ['type' => 'string',  'enum' => ['DELIVERED'], 'example' => 'DELIVERED', 'description' => 'Always DELIVERED on creation.'],
                            ],
                            'required' => ['delivery_id', 'delivery_no', 'series_id', 'officer_id', 'delivery_date', 'booklets_delivered', 'delivery_type', 'status'],
                        ],
                        'message' => ['type' => 'string', 'example' => 'Delivery recorded.'],
                    ],
                    'required' => ['status', 'success', 'data', 'message'],
                ],

                'LibisAccountableFormsListResponse' => [
                    'type' => 'object',
                    'description' => 'Envelope returned by GET /LIBIS/api/v1/invoice-inventory/list (API 4, FR-TR-002). '
                                   . 'Paginated result set of registered invoice series with per-officer stock breakdown.',
                    'properties' => [
                        'status'  => ['type' => 'integer', 'example' => 200],
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data'    => [
                            'type' => 'object',
                            'properties' => [
                                'total'    => ['type' => 'integer', 'example' => 1, 'description' => 'Total rows matching the filter (before pagination).'],
                                'page'     => ['type' => 'integer', 'example' => 1],
                                'per_page' => ['type' => 'integer', 'example' => 10, 'description' => 'Clamped to the range [1, 100].'],
                                'series'   => [
                                    'type'  => 'array',
                                    'items' => ['$ref' => '#/components/schemas/LibisInvoiceSeries'],
                                ],
                            ],
                            'required' => ['total', 'page', 'per_page', 'series'],
                        ],
                    ],
                    'required' => ['status', 'success', 'data'],
                ],

                'LibisAccountableFormTagStatusResponse' => [
                    'type' => 'object',
                    'description' => 'Envelope returned by POST /LIBIS/api/v1/invoice-inventory/tag-status (API 5, FR-TR-002). '
                                   . 'Echoes both the pre- and post-transition status so the caller can audit the change locally.',
                    'properties' => [
                        'status'  => ['type' => 'integer', 'example' => 200],
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data'    => [
                            'type' => 'object',
                            'properties' => [
                                'series_id'       => ['type' => 'integer', 'example' => 15],
                                'series_code'     => ['type' => 'string',  'example' => 'OR-2026-NCR-01'],
                                'series_from'     => ['type' => 'integer', 'example' => 100001],
                                'series_to'       => ['type' => 'integer', 'example' => 100500],
                                'previous_status' => ['type' => 'string',  'enum' => ['ACTIVE', 'DEPLETED'], 'example' => 'ACTIVE', 'description' => 'Status before this call.'],
                                'new_status'      => ['type' => 'string',  'enum' => ['ACTIVE', 'DEPLETED'], 'example' => 'DEPLETED', 'description' => 'Status after this call.'],
                                'changed_at'      => ['type' => 'string',  'format' => 'date-time', 'example' => '2026-08-03T09:00:00'],
                            ],
                            'required' => ['series_id', 'series_code', 'series_from', 'series_to', 'previous_status', 'new_status', 'changed_at'],
                        ],
                        'message' => ['type' => 'string', 'example' => 'Invoice series status updated successfully.'],
                    ],
                    'required' => ['status', 'success', 'data', 'message'],
                ],

                // ── Authentication ─────────────────────────────────────────────
                'LoginResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'JWT' => [
                            'type'        => 'string',
                            'description' => 'JWT bearer token. Use as: X-Authorization: Bearer <token>',
                            'example'     => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9...'
                        ]
                    ]
                ],

                // ── UAC schemas ────────────────────────────────────────────────
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id'       => ['type' => 'integer'],
                        'username'      => ['type' => 'string'],
                        'name'          => ['type' => 'string'],
                        'email'         => ['type' => 'string', 'format' => 'email'],
                        'department_id' => ['type' => 'integer'],
                        'office_id'     => ['type' => 'integer'],
                        'is_active'     => ['type' => 'boolean'],
                        'last_login'    => ['type' => 'string', 'format' => 'date-time'],
                        'created_at'    => ['type' => 'string', 'format' => 'date-time']
                    ]
                ],
                'UserLevel' => [
                    'type' => 'object',
                    'properties' => [
                        'user_level_id' => ['type' => 'integer'],
                        'name'          => ['type' => 'string'],
                        'description'   => ['type' => 'string'],
                        'system_id'     => ['type' => 'integer']
                    ]
                ],
                'Department' => [
                    'type' => 'object',
                    'properties' => [
                        'department_id'        => ['type' => 'integer'],
                        'department_name'      => ['type' => 'string'],
                        'description'          => ['type' => 'string'],
                        'parent_department_id' => ['type' => 'integer'],
                        'department_head_id'   => ['type' => 'integer']
                    ]
                ],
                'AuditLog' => [
                    'type' => 'object',
                    'properties' => [
                        'id'        => ['type' => 'integer'],
                        'date_time' => ['type' => 'string', 'format' => 'date-time'],
                        'script'    => ['type' => 'string'],
                        'user'      => ['type' => 'string'],
                        'action'    => ['type' => 'string', 'enum' => ['A', 'U', 'D', 'login', 'logout']],
                        'table'     => ['type' => 'string'],
                        'field'     => ['type' => 'string'],
                        'key_value' => ['type' => 'string'],
                        'old_value' => ['type' => 'string'],
                        'new_value' => ['type' => 'string']
                    ]
                ],

                // ── Notification schema ────────────────────────────────────────
                'Notification' => [
                    'type' => 'object',
                    'properties' => [
                        'id'          => ['type' => 'string'],
                        'timestamp'   => ['type' => 'string', 'format' => 'date-time'],
                        'type'        => ['type' => 'string'],
                        'target'      => ['type' => 'string'],
                        'user_id'     => ['type' => 'integer'],
                        'subject'     => ['type' => 'string'],
                        'body'        => ['type' => 'string'],
                        'link'        => ['type' => 'string'],
                        'from_system' => ['type' => 'string', 'enum' => ['UAC', 'FMD', 'Accounting', 'Treasury', 'AFIMS']],
                        'is_read'     => ['type' => 'boolean'],
                        'created_at'  => ['type' => 'string', 'format' => 'date-time']
                    ]
                ],

                // ── FMD schemas ────────────────────────────────────────────────
                'FundTracking' => [
                    'type' => 'object',
                    'properties' => [
                        'fund_tracking_id'  => ['type' => 'integer'],
                        'fiscal_year'       => ['type' => 'integer', 'example' => 2025],
                        'tracking_type'     => ['type' => 'string',  'enum' => ['GAAAO', 'SARO', 'NCA', 'TRUST_DOWNLOAD', 'SUPPLEMENTAL', 'COLLECTION']],
                        'reference_id'      => ['type' => 'string',  'example' => 'SARO-2025-000001', 'description' => 'UNIQUE — format SARO-YYYY-NNNNNN / NCA-YYYY-NNNNNN'],
                        'reference_number'  => ['type' => 'string'],
                        'reference_date'    => ['type' => 'string',  'format' => 'date'],
                        'fund_source_id'    => ['type' => 'integer'],
                        'program_id'        => ['type' => 'integer'],
                        'project_id'        => ['type' => 'integer'],
                        'linked_saro_id'    => ['type' => 'integer', 'description' => 'Parent SARO — required for NCA records'],
                        'moa_id'            => ['type' => 'integer'],
                        'allocated_amount'  => ['type' => 'number',  'format' => 'double'],
                        'utilized_amount'   => ['type' => 'number',  'format' => 'double'],
                        'available_balance' => ['type' => 'number',  'format' => 'double', 'description' => 'GENERATED ALWAYS AS (allocated_amount - utilized_amount) STORED'],
                        'status'            => ['type' => 'string',  'enum' => ['ACTIVE', 'EXHAUSTED', 'CANCELLED']],
                        'created_at'        => ['type' => 'string',  'format' => 'date-time'],
                        'created_by'        => ['type' => 'integer']
                    ]
                ],
                'BudgetVersion' => [
                    'type' => 'object',
                    'properties' => [
                        'budget_version_id'        => ['type' => 'integer'],
                        'fiscal_year'              => ['type' => 'integer'],
                        'version_number'           => ['type' => 'integer'],
                        'version_name'             => ['type' => 'string'],
                        'budget_type'              => ['type' => 'string', 'example' => 'COB'],
                        'ng_subsidy_total'         => ['type' => 'number', 'format' => 'double'],
                        'trust_fund_total'         => ['type' => 'number', 'format' => 'double'],
                        'supplemental_total'       => ['type' => 'number', 'format' => 'double'],
                        'corporate_receipts_total' => ['type' => 'number', 'format' => 'double'],
                        'total_budget'             => ['type' => 'number', 'format' => 'double'],
                        'status'                   => ['type' => 'string', 'enum' => ['DRAFT', 'FOR_GM_APPROVAL', 'GM_APPROVED', 'SUBMITTED_TO_DBM', 'DBM_APPROVED']],
                        'dbm_submission_date'      => ['type' => 'string', 'format' => 'date'],
                        'dbm_approved_date'        => ['type' => 'string', 'format' => 'date'],
                        'created_at'               => ['type' => 'string', 'format' => 'date-time']
                    ]
                ],
                'BudgetDetail' => [
                    'type' => 'object',
                    'properties' => [
                        'budget_detail_id'  => ['type' => 'integer'],
                        'budget_version_id' => ['type' => 'integer'],
                        'program_id'        => ['type' => 'integer'],
                        'project_id'        => ['type' => 'integer'],
                        'account_id'        => ['type' => 'integer'],
                        'fund_source_id'    => ['type' => 'integer'],
                        'department_id'     => ['type' => 'integer'],
                        'budget_category'   => ['type' => 'string', 'enum' => ['PS', 'MOOE', 'CO', 'EQUIPMENT_OUTLAY']],
                        'budgeted_amount'   => ['type' => 'number', 'format' => 'double'],
                        'realigned_amount'  => ['type' => 'number', 'format' => 'double'],
                        'final_budget'      => ['type' => 'number', 'format' => 'double', 'description' => 'GENERATED ALWAYS AS (budgeted_amount + realigned_amount) STORED']
                    ]
                ],
                'Obligation' => [
                    'type' => 'object',
                    'properties' => [
                        'obligation_id'     => ['type' => 'integer'],
                        'fiscal_year'       => ['type' => 'integer'],
                        'fund_tracking_id'  => ['type' => 'integer'],
                        'allocation_id'     => ['type' => 'integer'],
                        'program_id'        => ['type' => 'integer'],
                        'project_id'        => ['type' => 'integer'],
                        'vendor_id'         => ['type' => 'integer'],
                        'obligation_type'   => ['type' => 'string', 'enum' => ['PURCHASE_ORDER', 'CONTRACT', 'CASH_ADVANCE', 'PAYROLL', 'OTHER']],
                        'obligation_number' => ['type' => 'string'],
                        'obligation_date'   => ['type' => 'string', 'format' => 'date'],
                        'amount'            => ['type' => 'number', 'format' => 'double'],
                        'status'            => ['type' => 'string', 'enum' => ['OPEN', 'PARTIALLY_DISBURSED', 'FULLY_DISBURSED', 'CANCELLED']]
                    ]
                ],
                'CrossAccess' => [
                    'type' => 'object',
                    'properties' => [
                        'cross_access_id'     => ['type' => 'integer'],
                        'fiscal_year'         => ['type' => 'integer'],
                        'from_fund_source_id' => ['type' => 'integer'],
                        'to_fund_source_id'   => ['type' => 'integer'],
                        'amount'              => ['type' => 'number', 'format' => 'double'],
                        'purpose'             => ['type' => 'string'],
                        'is_temporary'        => ['type' => 'boolean'],
                        'repayment_due_date'  => ['type' => 'string', 'format' => 'date'],
                        'is_repaid'           => ['type' => 'boolean'],
                        'repayment_date'      => ['type' => 'string', 'format' => 'date'],
                        'status'              => ['type' => 'string', 'enum' => ['ACTIVE', 'REPAID', 'DEFAULTED']]
                    ]
                ],

                // ── Accounting schemas ─────────────────────────────────────────
                'Voucher' => [
                    'type' => 'object',
                    'properties' => [
                        'voucher_id'       => ['type' => 'integer'],
                        'voucher_number'   => ['type' => 'string',  'example' => '25-01-0001'],
                        'voucher_type'     => ['type' => 'string',  'enum' => ['DV', 'LV', 'PCV', 'JEV']],
                        'voucher_category' => ['type' => 'string',  'enum' => ['CA', 'PS', 'MOOE', 'CO', 'EHAP', 'Trust', 'Subsidy', 'Others']],
                        'voucher_date'     => ['type' => 'string',  'format' => 'date'],
                        'fiscal_year'      => ['type' => 'integer'],
                        'fiscal_month'     => ['type' => 'integer'],
                        'payee_name'       => ['type' => 'string'],
                        'particulars'      => ['type' => 'string'],
                        'gross_amount'     => ['type' => 'number',  'format' => 'double'],
                        'withholding_tax'  => ['type' => 'number',  'format' => 'double'],
                        'net_amount'       => ['type' => 'number',  'format' => 'double', 'description' => 'GENERATED ALWAYS AS (gross_amount - withholding_tax) STORED'],
                        'payment_method'   => ['type' => 'string',  'enum' => ['CHECK', 'FTLA', 'EPAYMENT', 'CASH']],
                        'book_of_accounts' => ['type' => 'string'],
                        'posted_to_gl'     => ['type' => 'boolean'],
                        'posted_to_ckdj'   => ['type' => 'boolean'],
                        'status'           => ['type' => 'string',  'enum' => ['DRAFT', 'FOR_APPROVAL', 'APPROVED', 'POSTED', 'PAID', 'CANCELLED']]
                    ]
                ],
                'JEV' => [
                    'type' => 'object',
                    'properties' => [
                        'jev_id'          => ['type' => 'integer'],
                        'jev_number'      => ['type' => 'string'],
                        'jev_date'        => ['type' => 'string', 'format' => 'date'],
                        'jev_type_id'     => ['type' => 'integer'],
                        'particulars'     => ['type' => 'string'],
                        'fiscal_year'     => ['type' => 'integer'],
                        'fiscal_month'    => ['type' => 'integer'],
                        'total_debit'     => ['type' => 'number', 'format' => 'double'],
                        'total_credit'    => ['type' => 'number', 'format' => 'double'],
                        'is_posted'       => ['type' => 'boolean'],
                        'is_reversed'     => ['type' => 'boolean'],
                        'reversal_jev_id' => ['type' => 'integer'],
                        'created_by'      => ['type' => 'integer']
                    ]
                ],
                'PayrollBatch' => [
                    'type' => 'object',
                    'properties' => [
                        'batch_id'         => ['type' => 'integer'],
                        'payroll_period'   => ['type' => 'string',  'example' => '2025-01'],
                        'payroll_type'     => ['type' => 'string',  'enum' => ['REGULAR', 'OFF_CYCLE', 'MONETISATION', 'LABOR']],
                        'cutoff_date'      => ['type' => 'string',  'format' => 'date'],
                        'payroll_date'     => ['type' => 'string',  'format' => 'date'],
                        'payment_date'     => ['type' => 'string',  'format' => 'date'],
                        'total_gross_pay'  => ['type' => 'number',  'format' => 'double'],
                        'total_deductions' => ['type' => 'number',  'format' => 'double'],
                        'total_net_pay'    => ['type' => 'number',  'format' => 'double'],
                        'jev_id'           => ['type' => 'integer'],
                        'status'           => ['type' => 'string',  'enum' => ['DRAFT', 'JEV_GENERATED', 'APPROVED', 'DISBURSED']]
                    ]
                ],

                // ── Treasury schemas ───────────────────────────────────────────
                'TreasuryCheck' => [
                    'type' => 'object',
                    'properties' => [
                        'check_id'                 => ['type' => 'integer'],
                        'check_number'             => ['type' => 'string'],
                        'check_date'               => ['type' => 'string', 'format' => 'date'],
                        'bank_account_id'          => ['type' => 'integer'],
                        'voucher_id'               => ['type' => 'integer'],
                        'payee_name'               => ['type' => 'string'],
                        'amount'                   => ['type' => 'number', 'format' => 'double'],
                        'check_status'             => ['type' => 'string', 'enum' => ['PREPARED', 'RELEASED', 'STALE', 'CANCELLED', 'REPLACEMENT']],
                        'release_date'             => ['type' => 'string', 'format' => 'date'],
                        'stale_date'               => ['type' => 'string', 'format' => 'date'],
                        'replacement_check_id'     => ['type' => 'integer'],
                        'coa_transmittal_batch_id' => ['type' => 'integer']
                    ]
                ],
                'BankReconciliation' => [
                    'type' => 'object',
                    'properties' => [
                        'reconciliation_id'               => ['type' => 'integer'],
                        'bank_account_id'                 => ['type' => 'integer'],
                        'reconciliation_date'             => ['type' => 'string', 'format' => 'date'],
                        'statement_date'                  => ['type' => 'string', 'format' => 'date'],
                        'book_balance_per_bank'           => ['type' => 'number', 'format' => 'double'],
                        'book_balance_per_cash_position'  => ['type' => 'number', 'format' => 'double'],
                        'deposits_in_transit'             => ['type' => 'number', 'format' => 'double'],
                        'outstanding_checks'              => ['type' => 'number', 'format' => 'double'],
                        'bank_charges'                    => ['type' => 'number', 'format' => 'double'],
                        'bank_credits'                    => ['type' => 'number', 'format' => 'double'],
                        'adjusted_bank_balance'           => ['type' => 'number', 'format' => 'double'],
                        'adjusted_book_balance'           => ['type' => 'number', 'format' => 'double'],
                        'variance'                        => ['type' => 'number', 'format' => 'double', 'description' => 'GENERATED ALWAYS AS (adjusted_bank_balance - adjusted_book_balance) STORED'],
                        'is_reconciled'                   => ['type' => 'boolean'],
                        'prepared_by'                     => ['type' => 'integer'],
                        'approved_by'                     => ['type' => 'integer']
                    ]
                ],
                'CashPosition' => [
                    'type' => 'object',
                    'properties' => [
                        'position_id'         => ['type' => 'integer'],
                        'position_date'       => ['type' => 'string', 'format' => 'date'],
                        'bank_account_id'     => ['type' => 'integer'],
                        'fund_source_id'      => ['type' => 'integer'],
                        'opening_balance'     => ['type' => 'number', 'format' => 'double'],
                        'total_receipts'      => ['type' => 'number', 'format' => 'double'],
                        'total_disbursements' => ['type' => 'number', 'format' => 'double'],
                        'closing_balance'     => ['type' => 'number', 'format' => 'double'],
                        'restricted_amount'   => ['type' => 'number', 'format' => 'double'],
                        'available_balance'   => ['type' => 'number', 'format' => 'double']
                    ]
                ],

                // ── Shared lookup schemas ──────────────────────────────────────
                'FundSource' => [
                    'type' => 'object',
                    'properties' => [
                        'fund_source_id' => ['type' => 'integer'],
                        'source_code'    => ['type' => 'string'],
                        'source_name'    => ['type' => 'string'],
                        'source_type'    => ['type' => 'string', 'enum' => ['NG_SUBSIDY', 'TRUST_FUND', 'SUPPLEMENTAL', 'CORPORATE_RECEIPTS', 'NDRRMC', 'GAA']],
                        'is_active'      => ['type' => 'boolean']
                    ]
                ],
                'ChartOfAccounts' => [
                    'type' => 'object',
                    'properties' => [
                        'account_id'   => ['type' => 'integer'],
                        'account_code' => ['type' => 'string', 'example' => '1-01-01-010'],
                        'account_name' => ['type' => 'string'],
                        'account_type' => ['type' => 'string', 'enum' => ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE']],
                        'is_active'    => ['type' => 'boolean']
                    ]
                ],
                'FiscalPeriod' => [
                    'type' => 'object',
                    'properties' => [
                        'fiscal_period_id' => ['type' => 'integer'],
                        'fiscal_year'      => ['type' => 'integer'],
                        'period_number'    => ['type' => 'integer'],
                        'period_type'      => ['type' => 'string', 'example' => 'MONTHLY'],
                        'period_name'      => ['type' => 'string'],
                        'start_date'       => ['type' => 'string', 'format' => 'date'],
                        'end_date'         => ['type' => 'string', 'format' => 'date'],
                        'status'           => ['type' => 'string', 'enum' => ['OPEN', 'CLOSED', 'LOCKED']]
                    ]
                ],
                'FileInfo' => [
                    'type' => 'object',
                    'properties' => [
                        'name'     => ['type' => 'string'],
                        'path'     => ['type' => 'string'],
                        'size'     => ['type' => 'integer'],
                        'type'     => ['type' => 'string'],
                        'modified' => ['type' => 'string', 'format' => 'date-time']
                    ]
                ]
            ],
            'securitySchemes' => [
                'Bearer' => [
                    'type'        => 'apiKey',
                    'description' => '*Note: Login to get your JWT token first, then enter "Bearer <JWT Token>" below, e.g.<br><em>Bearer 123456abcdef</em>',
                    'name'        => 'X-Authorization',
                    'in'          => 'header'
                ]
            ]
        ];
    }

    /**
     * Load service classes using reflection
     */
    private function loadServiceClasses() {
        foreach ($this->systems as $system) {
            $servicesPath = __DIR__ . "/../{$system}/app/services/";

            if (!is_dir($servicesPath)) {
                continue;
            }

            $serviceFiles = glob($servicesPath . "*.php");

            // Pre-load trait/interface definitions first. A service class may
            // `use` a trait (e.g. SnapshotWorkflowTrait) defined in a sibling
            // file; glob() returns files alphabetically, so the consuming class
            // can be included before its trait. With no autoloader active here,
            // that triggers a fatal "Trait not found" error which aborts the
            // whole spec (and surfaces as a bogus Swagger UI YAML parse error).
            $classFiles = [];
            foreach ($serviceFiles as $file) {
                $content = file_get_contents($file);
                $definesTrait = preg_match('/^\s*(trait|interface)\s+\w+/m', $content);
                $definesClass = preg_match('/^\s*(abstract\s+|final\s+)*class\s+\w+/m', $content);
                if ($definesTrait && !$definesClass) {
                    try {
                        include_once $file;
                    } catch (\Throwable $e) {
                        // Skip files that can't be pre-loaded
                    }
                } else {
                    $classFiles[] = $file;
                }
            }

            foreach ($classFiles as $file) {
                $this->loadServiceClass($file, $system);
            }
        }
    }

    /**
     * Load individual service class using reflection
     */
    private function loadServiceClass($file, $system) {
        $content = file_get_contents($file);

        // Extract class name from file
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];

            // Prefer the namespace declared in the file itself (e.g.
            // PHPMaker2024\ACCOUNTING, ...\TREASURY), then fall back to the
            // legacy AFIMS/UAC namespaces.
            $namespaces = [];
            if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $nsMatch)) {
                $namespaces[] = trim($nsMatch[1]) . "\\{$className}";
            }
            $namespaces[] = "PHPMaker2024\\AFIMS\\Service\\{$className}";
            $namespaces[] = "PHPMaker2024\\AFIMS\\{$className}";
            $namespaces[] = "PHPMaker2024\\UAC\\{$className}";

            try {
                // Guard against redeclaration: different systems occasionally
                // reuse the same namespace + class name (e.g. two
                // SubsidiaryLedgerService files under PHPMaker2024\ACCOUNTING).
                // Re-declaring a class is an uncatchable compile-time fatal, so
                // only include the file if none of its candidate classes exist.
                $alreadyLoaded = false;
                foreach ($namespaces as $fullClassName) {
                    if (class_exists($fullClassName, false)) {
                        $alreadyLoaded = true;
                        break;
                    }
                }
                if (!$alreadyLoaded) {
                    include_once $file;
                }

                foreach ($namespaces as $fullClassName) {
                    if (class_exists($fullClassName)) {
                        $reflection = new ReflectionClass($fullClassName);
                        $this->serviceClasses[$className] = [
                            'reflection' => $reflection,
                            'system'     => $system,
                            'methods'    => $this->analyzeServiceMethods($reflection)
                        ];
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Skip if class can't be loaded (fatal Errors included)
            }
        }
    }

    /**
     * Analyze service class methods using reflection
     */
    private function analyzeServiceMethods($reflection) {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $methodInfo = [
                'name'        => $method->getName(),
                'parameters'  => [],
                'returnType'  => $this->getReturnType($method),
                'docComment'  => $method->getDocComment(),
                'description' => $this->extractDescriptionFromDoc($method->getDocComment())
            ];

            // Analyze parameters
            foreach ($method->getParameters() as $param) {
                $paramInfo = [
                    'name'         => $param->getName(),
                    'type'         => $this->getParameterType($param),
                    'required'     => !$param->isOptional(),
                    'defaultValue' => $param->isOptional() ? $param->getDefaultValue() : null
                ];

                $methodInfo['parameters'][] = $paramInfo;
            }

            $methods[$method->getName()] = $methodInfo;
        }

        return $methods;
    }

    /**
     * Parse route files to extract route definitions
     */
    private function parseRouteFiles() {
        foreach ($this->systems as $system) {
            // Try app/routes first, then app/api/routes
            $routesPath = __DIR__ . "/../{$system}/app/routes/";
            if (!is_dir($routesPath)) {
                $routesPath = __DIR__ . "/../{$system}/app/api/routes/";
                if (!is_dir($routesPath)) {
                    continue;
                }
            }

            // Recursively find PHP route files so subfolders are included
            $routeFiles = [];
            try {
                $dirIter = new \RecursiveDirectoryIterator($routesPath, \RecursiveDirectoryIterator::SKIP_DOTS);
                $iter = new \RecursiveIteratorIterator($dirIter);
                foreach ($iter as $fileInfo) {
                    if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
                        $routeFiles[] = $fileInfo->getPathname();
                    }
                }
            } catch (Exception $e) {
                // Fallback to non-recursive glob if iterator fails
                $routeFiles = glob($routesPath . "*.php");
            }

            foreach ($routeFiles as $file) {
                $this->parseRouteFile($file, $system);
            }
        }
    }

    /**
     * Parse individual route file
     */
    private function parseRouteFile($file, $system) {
        $content  = file_get_contents($file);
        $filename = basename($file, '.php');

        // Extract routes using improved regex
        $this->extractRoutes($content, $filename, $system);
    }

    /**
     * Extract routes from file content
     */
    private function extractRoutes($content, $filename, $system) {
        // Pattern to match various function signatures including closures with use()
        $pattern = '/\$app->(get|post|put|delete|patch)\s*\(\s*["\']([^"\']+)["\']\s*,\s*function\s*\([^)]*\)(?:\s*use\s*\([^)]*\))?\s*\{/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $method   = strtoupper($match[1][0]);
            $path     = $match[2][0];
            $startPos = $match[0][1] + strlen($match[0][0]) - 1; // Position after opening brace

            // Find the matching closing brace
            $braceCount   = 1;
            $pos          = $startPos;
            $functionBody = '';

            while ($braceCount > 0 && $pos < strlen($content)) {
                $pos++;
                $char = $content[$pos];

                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                }

                if ($braceCount > 0) {
                    $functionBody .= $char;
                }
            }

            // Now find the end of the route definition including middleware
            $routeEnd = $pos + 1;

            // Look for middleware chains after the function
            while ($routeEnd < strlen($content)) {
                $char = $content[$routeEnd];

                // Stop at semicolon, newline, or next route definition
                if ($char === ';' || $char === "\n" || $char === "\r") {
                    break;
                }

                // Stop if we hit another route definition
                if (preg_match('/\$app->(get|post|put|delete|patch)/', substr($content, $routeEnd, 20))) {
                    break;
                }

                $routeEnd++;
            }

            // Extract the full route definition including middleware
            $routeStart          = $match[0][1];
            $fullRouteDefinition = substr($content, $routeStart, $routeEnd - $routeStart);

            $this->addPath($path, $method, $filename, $system, $functionBody, $fullRouteDefinition);
        }
    }

    /**
     * Add path to OpenAPI spec
     */
    private function addPath($path, $method, $filename, $system, $functionBody, $fullRouteDefinition = '') {
        // Clean path - remove optional parameters notation
        $cleanPath = preg_replace('/\[[^\]]+\]/', '', $path);
        $cleanPath = str_replace('//', '/', $cleanPath);

        // Add system prefix to path.
        $fullPath = "/{$system}/api{$cleanPath}";

        if (!isset($this->paths[$fullPath])) {
            $this->paths[$fullPath] = [];
        }

        // Extract path parameters
        $pathParams = $this->extractPathParameters($path);

        // Extract query parameters from function body
        $queryParams = $this->extractQueryParameters($functionBody, $path);

        // Extract request body schema from service method
        $requestBody = $this->extractRequestBodyFromService($functionBody, $path, $method);

        // Generate operation using reflection data
        $operation = $this->generateOperation(
            $path, $method, $filename, $system,
            $pathParams, $queryParams, $requestBody,
            $functionBody, $fullRouteDefinition
        );

        $this->paths[$fullPath][strtolower($method)] = $operation;
    }

    /**
     * Extract path parameters from route
     */
    private function extractPathParameters($path) {
        $params = [];
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        foreach ($matches[1] as $param) {
            $type     = (substr($param, -3) === '_id' || $param === 'id') ? 'integer' : 'string';
            $params[] = [
                'name'     => $param,
                'in'       => 'path',
                'required' => true,
                'schema'   => ['type' => $type]
            ];
        }

        return $params;
    }

    /**
     * Extract query parameters from function body
     */
    private function extractQueryParameters($functionBody, $path) {
        $params = [];
        $seen   = [];

        // Look for getQueryParams() usage patterns
        $patterns = [
            '/\$params\[[\'"]([^\'"]+)[\'"]\]/',
            '/getQueryParam\([\'"]([^\'"]+)[\'"]\)/',
            '/\$request->getQueryParams\(\)\[[\'"]([^\'"]+)[\'"]\]/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $functionBody, $matches)) {
                foreach ($matches[1] as $paramName) {
                    if (!isset($seen[$paramName])) {
                        $seen[$paramName] = true;
                        if (substr($paramName, -3) === '_id'
                            || in_array($paramName, ['page', 'per_page', 'limit', 'offset', 'fiscal_year', 'year'])) {
                            $type = 'integer';
                        } elseif (in_array($paramName, ['selectable', 'is_active', 'active'])) {
                            $type = 'boolean';
                        } else {
                            $type = 'string';
                        }
                        $params[] = [
                            'name'     => $paramName,
                            'in'       => 'query',
                            'required' => false,
                            'schema'   => ['type' => $type]
                        ];
                    }
                }
            }
        }

        return $params;
    }

    /**
     * Extract request body schema from service method
     */
    private function extractRequestBodyFromService($functionBody, $path, $method) {
        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        // Look for getParsedBody() usage
        if (strpos($functionBody, 'getParsedBody()') !== false) {
            // Find the service method being called
            $serviceMethod = $this->findServiceMethodFromRoute($functionBody);

            if ($serviceMethod && isset($this->serviceClasses[$serviceMethod['class']])) {
                $serviceInfo = $this->serviceClasses[$serviceMethod['class']];
                $methodInfo  = $serviceInfo['methods'][$serviceMethod['method']] ?? null;

                if ($methodInfo) {
                    return $this->buildRequestBodyFromMethod($methodInfo);
                }
            }

            // Fallback to generic schema
            return [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            'type'       => 'object',
                            'properties' => $this->inferRequestBodyProperties($functionBody, $path)
                        ]
                    ]
                ]
            ];
        }

        return null;
    }

    /**
     * Find service method from route function body
     */
    private function findServiceMethodFromRoute($functionBody) {
        // e.g. $result = $fmdService->saveSaro($data);
        if (preg_match('/\$(\w+Service)->(\w+)\(\s*\$(?:data|request)\s*\)/', $functionBody, $matches)) {
            return [
                'class'  => ucfirst($matches[1]),
                'method' => $matches[2]
            ];
        }

        // Older pattern: new Service(); $service->method()
        if (preg_match('/new\s+(\w+Service)\(\)[^;]*;\s*[^;]*\$service->(\w+)\(/', $functionBody, $matches)) {
            return [
                'class'  => $matches[1],
                'method' => $matches[2]
            ];
        }

        return null;
    }

    /**
     * Build request body schema from service method reflection
     */
    private function buildRequestBodyFromMethod($methodInfo) {
        $properties = [];

        foreach ($methodInfo['parameters'] as $param) {
            if (!in_array($param['name'], ['data', 'request', 'id'])) {
                $properties[$param['name']] = [
                    'type'        => $this->mapPhpTypeToOpenAPI($param['type']),
                    'description' => $this->extractParameterDescription($param['name'], $methodInfo['docComment'])
                ];
            }
        }

        return [
            'required' => true,
            'content'  => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => $properties ?: ['data' => ['type' => 'object', 'description' => 'Request data']]
                    ]
                ]
            ]
        ];
    }

    /**
     * Infer request body properties from $data[] access patterns
     */
    private function inferRequestBodyProperties($functionBody, $path) {
        $properties = [];

        // Look for data array access patterns
        if (preg_match_all('/\$data\[[\'"]([^\'"]+)[\'"]\]/', $functionBody, $matches)) {
            foreach ($matches[1] as $field) {
                $properties[$field] = ['type' => $this->guessFieldType($field)];
            }
        }

        if (empty($properties)) {
            $properties = [
                'data' => ['type' => 'object', 'description' => 'Request data']
            ];
        }

        return $properties;
    }

    /**
     * Generate operation object
     */
    private function generateOperation($path, $method, $filename, $system, $pathParams, $queryParams, $requestBody, $functionBody, $fullRouteDefinition = '') {
        $operation = [
            'tags'        => [$this->getTagName($filename, $system)],
            'summary'     => $this->generateSummary($path, $method, $filename),
            'description' => $this->generateDescription($path, $method, $filename, $functionBody),
            'operationId' => $this->generateOperationId($path, $method, $system)
        ];

        // Add parameters
        $allParams = array_merge($pathParams, $queryParams);
        if (!empty($allParams)) {
            $operation['parameters'] = $allParams;
        }

        // Add request body
        if ($requestBody) {
            $operation['requestBody'] = $requestBody;
        }

        // Add responses
        $operation['responses'] = $this->generateResponses($path, $method, $filename, $functionBody);

        // Add security
        if ($this->requiresAuth($path, $filename, $functionBody, $fullRouteDefinition)) {
            $operation['security'] = [['Bearer' => []]];
        }

        return $operation;
    }

    /**
     * Get tag name for operation
     * Maps filename + system to one of the five system tag groups
     */
    private function getTagName($filename, $system) {
        // System-level routing
        if ($system === 'UAC') {
            return 'UAC';
        }

        if ($system === 'Notification') {
            return 'Notification';
        }

        // FMD sub-tags
        $fmdMap = [
            'fmd_fund_sourcing'     => 'FMD - Fund Sourcing',
            'fmd_budget_planning'   => 'FMD - Budget Planning',
            'fmd_budget_allocation' => 'FMD - Budget Allocation',
            'fmd_obligation'        => 'FMD - Obligations',
            'fmd_budget_monitoring' => 'FMD - Monitoring',
            'fmd_cross_access'      => 'FMD - Cross Access',
            'fmd_trust_fund'        => 'FMD - Trust Fund'
        ];

        if (isset($fmdMap[$filename])) {
            return $fmdMap[$filename];
        }

        // Accounting sub-tags
        $accountingMap = [
            'accounting_voucher'      => 'Accounting - Vouchers',
            'accounting_jev'          => 'Accounting - JEV',
            'accounting_ledger'       => 'Accounting - Ledger',
            'accounting_payroll'      => 'Accounting - Payroll',
            'accounting_journal'      => 'Accounting - Journals',
            'accounting_disbursement' => 'Accounting - Disbursement',
            'accounting_report'       => 'Accounting - Reports',
            'accounting_tax'          => 'Accounting - Tax & BIR'
        ];

        if (isset($accountingMap[$filename])) {
            return $accountingMap[$filename];
        }

        // Treasury sub-tags
        $treasuryMap = [
            'treasury_bank'        => 'Treasury - Bank Master',
            'treasury_bank_master' => 'Treasury - Bank Master',
            'treasury_receiving'   => 'Treasury - Receiving',
            'treasury_disbursing'  => 'Treasury - Disbursing',
            'treasury_cash'        => 'Treasury - Cash Position',
            'treasury_bank_recon'  => 'Treasury - Reconciliation',
            'treasury_fund_sweep'  => 'Treasury - Fund Sweeping',
            'treasury_fund_mgmt'   => 'Treasury - Fund Management',
            'treasury_check'       => 'Treasury - Disbursing',
            'treasury_ftla'        => 'Treasury - Disbursing',
            'treasury_report'      => 'Treasury - Reports'
        ];

        if (isset($treasuryMap[$filename])) {
            return $treasuryMap[$filename];
        }

        // Fall back to title-cased filename
        return ucwords(str_replace(['_', '-'], ' ', $filename));
    }

    /**
     * Generate operation summary
     */
    private function generateSummary($path, $method, $filename) {
        $action   = $this->getActionFromMethod($method);
        $resource = $this->getResourceFromPath($path);

        // Override for known action suffixes
        $actionSuffixes = [
            'submit', 'approve', 'cancel', 'post', 'activate', 'repay',
            'release', 'stale', 'liquidate', 'generate-id', 'mark-deposited',
            'validate', 'reverse', 'certify', 'read', 'unread', 'read-all'
        ];

        foreach ($actionSuffixes as $suffix) {
            if (preg_match('/' . preg_quote($suffix, '/') . '$/', $path)) {
                $parentPath = preg_replace('/' . preg_quote($suffix, '/') . '$/', '', rtrim($path, '/'));
                return ucwords(str_replace('-', ' ', $suffix)) . ' ' . $this->getResourceFromPath($parentPath);
            }
        }

        return "{$action} {$resource}";
    }

    /**
     * Generate operation description
     */
    private function generateDescription($path, $method, $filename, $functionBody) {
        // Try to get description from service method
        $serviceMethod = $this->findServiceMethodFromRoute($functionBody);
        if ($serviceMethod && isset($this->serviceClasses[$serviceMethod['class']])) {
            $serviceInfo = $this->serviceClasses[$serviceMethod['class']];
            $methodInfo  = $serviceInfo['methods'][$serviceMethod['method']] ?? null;

            if ($methodInfo && $methodInfo['description']) {
                return $methodInfo['description'];
            }
        }

        // Inline descriptions for key NHA-FIMS routes
        $descriptions = [
            // UAC
            '/users'                                => 'List all system users with their department and user-level assignments.',
            '/users/{id}'                           => 'Get, update, or deactivate a specific user record.',
            '/user-levels'                          => 'List all user levels grouped by system (UAC, FMD, Accounting, Treasury).',
            '/departments'                          => 'List all departments including the FMD / AD / TD hierarchy.',
            '/audit-logs'                           => 'Query the PHPMaker-compatible audit trail (uac.audit_logs).',
            '/permissions'                          => 'Get or update table-level bitmask permissions for a user level.',

            // Notification
            '/notification'                         => 'List notifications for the authenticated user. Supports is_read and from_system filters.',
            '/notification/{id}/read'               => 'Mark a single notification as read (is_read = true).',
            '/notification/read-all'                => 'Mark all unread notifications for the current user as read.',
            '/notification/send'                    => 'Create and dispatch a new system notification to one or more users.',

            // FMD — Fund Sourcing
            '/fmd/saro/generate-id'                 => 'Auto-generate the next unique SARO reference ID (format SARO-YYYY-NNNNNN). Stored as UNIQUE in fmd.fund_tracking.',
            '/fmd/nca/generate-id'                  => 'Auto-generate the next unique NCA reference ID (format NCA-YYYY-NNNNNN). NCA must reference a valid parent SARO via linked_saro_id.',
            '/fmd/cross-access/{id}/repay'          => 'Record repayment of a cross-access borrowing. Updates status to REPAID and sets repayment_date.',
            '/fmd/fund-tracking/{id}/activate'      => 'Activate a SARO or NCA record. Validates linked_saro_id chain for NCA activations.',

            // FMD — Budget
            '/fmd/cob/{id}/approve-dbm'             => 'Record DBM approval of the Corporate Operating Budget. Sets status to DBM_APPROVED and stores dbm_approved_date.',
            '/fmd/cob/{id}/submit-dbm'              => 'Submit the COB version to DBM for approval. Sets status to SUBMITTED_TO_DBM.',
            '/fmd/obligation/{id}/cancel'           => 'Cancel an open obligation. Only allowed when no disbursement has been posted against it.',

            // Accounting
            '/accounting/vouchers/{id}/post-gl'     => 'Post an approved voucher to the General Ledger. Sets posted_to_gl = true and creates the corresponding JEV.',
            '/accounting/jev/{id}/reverse'          => 'Create a reversing JEV. Both entries are linked via reversal_jev_id.',
            '/accounting/payroll/batch/{id}/generate-jev' => 'Generate a draft JEV for the payroll batch. Debits PS accounts and credits payable accounts.',
            '/accounting/payroll/batch/{id}/disburse' => 'Mark a payroll batch as disbursed. Triggers CKDJ / ADADJ entries based on payment_method.',

            // Treasury
            '/treasury/bank-recon/{id}/approve'     => 'Approve a bank reconciliation statement. Requires variance = 0; sets is_reconciled = true.',
            '/treasury/soa/{id}/validate'           => 'Validate an uploaded SOA against book records. Feeds validated data to the reconciliation module.',
            '/treasury/check/{id}/release'          => 'Release a prepared check to the payee. Updates check_status to RELEASED and sets release_date.',
            '/treasury/check/{id}/stale'            => 'Mark a check as stale. Sets check_status to STALE and stale_date = today.',
            '/treasury/fund-transfer/{id}/complete' => 'Mark a fund transfer (FTLA) as completed after bank confirmation. Triggers ADADJ entry creation.'
        ];

        foreach ($descriptions as $suffix => $desc) {
            if ($path === $suffix || substr($path, -strlen($suffix)) === $suffix) {
                return $desc;
            }
        }

        return "Operations for {$filename}";
    }

    /**
     * Generate operation ID
     */
    private function generateOperationId($path, $method, $system) {
        $cleanPath = preg_replace('/[^a-zA-Z0-9]/', '_', $path);
        $cleanPath = preg_replace('/_+/', '_', $cleanPath);
        $cleanPath = trim($cleanPath, '_');

        return strtolower($method) . '_' . $cleanPath;
    }

    /**
     * Generate responses
     */
    private function generateResponses($path, $method, $filename, $functionBody) {
        $successSchema = $this->resolveSuccessSchema($path, $method);

        return [
            '200' => [
                'description' => 'Successful operation',
                'content'     => [
                    'application/json' => [
                        'schema' => $successSchema
                    ]
                ]
            ],
            '400' => [
                'description' => 'Bad request',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ],
            '401' => [
                'description' => 'Unauthorized',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ],
            '403' => [
                'description' => 'Forbidden',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ],
            '404' => [
                'description' => 'Not found',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ],
            '422' => [
                'description' => 'Unprocessable — business rule violation',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ],
            '500' => [
                'description' => 'Internal server error',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ]
        ];
    }

    /**
     * Resolve typed 200 schema from path keywords
     */
    private function resolveSuccessSchema($path, $method) {
        // Endpoints whose response envelope is fully typed by a single schema
        // (no generic Success / PaginatedList wrapper). Keep this list small —
        // it's for integration endpoints whose payload doesn't fit the FIMS
        // house response conventions.
        $directRefs = [
            // Order matters — longer / more-specific segments must appear
            // before shorter ones. `strpos` matches substrings.
            '/invoice-inventory/register'   => 'LibisAccountableFormRegisterResponse',
            '/invoice-inventory/deliver'    => 'LibisInvoiceDeliveryRecordResponse',
            '/invoice-inventory/list'       => 'LibisAccountableFormsListResponse',
            '/invoice-inventory/tag-status' => 'LibisAccountableFormTagStatusResponse',
            '/chart-of-accounts'            => 'LibisChartOfAccountsResponse',
            // Scoped to /v1/ so it can't hijack the FIMS lookup route
            // GET /lookups/bank-accounts, which returns a different shape.
            '/v1/bank-accounts'             => 'LibisBankAccountsResponse',
        ];
        foreach ($directRefs as $segment => $schemaName) {
            if (strpos($path, $segment) !== false) {
                return ['$ref' => "#/components/schemas/{$schemaName}"];
            }
        }

        $schemaMap = [
            '/saro'              => 'FundTracking',
            '/nca'               => 'FundTracking',
            '/fund-tracking'     => 'FundTracking',
            '/cob'               => 'BudgetVersion',
            '/budget-detail'     => 'BudgetDetail',
            '/budget-allocation' => 'BudgetDetail',
            '/obligation'        => 'Obligation',
            '/cross-access'      => 'CrossAccess',
            '/voucher'           => 'Voucher',
            '/jev'               => 'JEV',
            '/payroll/batch'     => 'PayrollBatch',
            '/check'             => 'TreasuryCheck',
            '/bank-recon'        => 'BankReconciliation',
            '/cash-position'     => 'CashPosition',
            '/fund-source'       => 'FundSource',
            '/chart-of-accounts' => 'ChartOfAccounts',
            '/fiscal-period'     => 'FiscalPeriod',
            '/notification'      => 'Notification',
            '/users'             => 'User',
            '/user-levels'       => 'UserLevel',
            '/departments'       => 'Department',
            '/audit-logs'        => 'AuditLog'
        ];

        foreach ($schemaMap as $segment => $schemaName) {
            if (strpos($path, $segment) !== false) {
                // Lists: GET without a trailing path param → PaginatedList wrapper
                $isList = ($method === 'GET' && !preg_match('/\{[^}]+\}$/', rtrim($path, '/')));
                if ($isList) {
                    return [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/PaginatedList'],
                            ['properties' => ['data' => ['items' => ['$ref' => "#/components/schemas/{$schemaName}"]]]]
                        ]
                    ];
                }
                return [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/Success'],
                        ['properties' => ['data' => ['$ref' => "#/components/schemas/{$schemaName}"]]]
                    ]
                ];
            }
        }

        return ['$ref' => '#/components/schemas/Success'];
    }

    /**
     * Check if route requires authentication based on middleware
     */
    private function requiresAuth($path, $filename, $functionBody, $fullRouteDefinition = '') {
        // Check if the route has middleware added
        if (strpos($fullRouteDefinition, '->add(') !== false) {
            return true;
        }

        // Also check the function body as a fallback
        if (strpos($functionBody, '->add(') !== false || strpos($functionBody, '$jwtMiddleware') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get action from HTTP method
     */
    private function getActionFromMethod($method) {
        $actions = [
            'GET'    => 'Get',
            'POST'   => 'Create',
            'PUT'    => 'Update',
            'PATCH'  => 'Update',
            'DELETE' => 'Delete'
        ];

        return $actions[$method] ?? $method;
    }

    /**
     * Get resource from path
     */
    private function getResourceFromPath($path) {
        $segments = explode('/', trim($path, '/'));
        $resource = end($segments);

        // Clean up resource name
        $resource = str_replace(['{', '}', '[/', ']'], '', $resource);
        $resource = ucwords(str_replace(['-', '_'], ' ', $resource));

        return $resource ?: 'Resource';
    }

    /**
     * Guess OpenAPI field type from FIMS naming conventions
     */
    private function guessFieldType($field) {
        if (substr($field, -3) === '_id' || in_array($field, ['fiscal_year', 'period_number', 'page', 'limit'])) {
            return 'integer';
        }
        if (substr($field, -7) === '_amount' || substr($field, -8) === '_balance' || substr($field, -6) === '_total') {
            return 'number';
        }
        if (substr($field, -5) === '_date') {
            return 'string';
        }
        if (substr($field, 0, 3) === 'is_' || substr($field, 0, 4) === 'has_') {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * Build complete OpenAPI specification
     */
    private function buildOpenAPISpec() {
        // Add login endpoint for each system
        $this->addLoginEndpoints();

        // Prune schemas if requested — must run after addLoginEndpoints() so
        // lazily-added login response $refs (e.g. LoginResponse) survive.
        if ($this->pruneUnusedSchemas) {
            $this->applySchemaPrune();
        }

        // Generate tags from all paths
        $this->generateTagsFromPaths();

        $spec = [
            'openapi'    => '3.0.0',
            'info'       => [
                'title'       => $this->title,
                'description' => $this->description,
                'version'     => $this->version,
                'contact'     => [
                    'name'  => 'NHA-FIMS Development Team',
                    'email' => 'dev@nha.gov.ph'
                ]
            ],
            'servers'    => $this->servers ?: [
                ['url' => 'http://localhost/AFIMS',         'description' => 'Local development'],
                ['url' => 'https://fims.nha.gov.ph/AFIMS', 'description' => 'Production']
            ],
            'tags'       => $this->tags,
            'paths'      => $this->paths,
            'components' => $this->components
        ];

        return $spec;
    }

    /**
     * Generate tags from all paths
     */
    private function generateTagsFromPaths() {
        $usedTags = [];

        foreach ($this->paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (isset($operation['tags'])) {
                    foreach ($operation['tags'] as $tag) {
                        $usedTags[$tag] = true;
                    }
                }
            }
        }

        // Add tags that are actually used
        foreach ($usedTags as $tagName => $used) {
            $tagExists = false;
            foreach ($this->tags as $existingTag) {
                if ($existingTag['name'] === $tagName) {
                    $tagExists = true;
                    break;
                }
            }

            if (!$tagExists) {
                $this->addTag($tagName, $this->getTagDescription($tagName));
            }
        }
    }

    /**
     * Get description for a tag
     */
    private function getTagDescription($tagName) {
        $descriptions = [
            // System-level
            'Authentication'             => 'JWT login / logout shared across all NHA-FIMS subsystems',
            'UAC'                        => 'User Access Control — users, roles, departments, permissions, audit trail',
            'Notification'               => 'System-wide notifications (uac.notifications) — create, list, mark as read',

            // FMD
            'FMD - Fund Sourcing'        => 'GAAAO, SARO, NCA processing; Cross-Access borrowings; Trust Fund MOAs; Supplemental / NDRRMC funds',
            'FMD - Budget Planning'      => 'Corporate Operating Budget (COB) preparation, Work Programs, budget versions, DBM submission',
            'FMD - Budget Allocation'    => 'CBA / RFA / LAA issuance, Certificate of Availability of Funds (CAF)',
            'FMD - Obligations'          => 'Obligation recording, SARO-Obligation-NCA chain validation, budget availability checks',
            'FMD - Monitoring'           => 'Financial Monitoring Report (FMR), SARO/NCA registry, utilisation summary, DBM reports',
            'FMD - Cross Access'         => 'Cross-access borrowings between fund sources, repayment tracking',
            'FMD - Trust Fund'           => 'Trust Fund MOAs, trust fund downloads, balance monitoring',

            // Accounting
            'Accounting - Vouchers'      => 'Disbursement Vouchers (DV), Liquidation Vouchers (LV), Petty Cash Vouchers (PCV)',
            'Accounting - JEV'           => 'Journal Entry Vouchers, GL posting, reversals',
            'Accounting - Ledger'        => 'General Ledger, Trial Balance, Financial Statements (SFP / SFPerf / SCF)',
            'Accounting - Payroll'       => 'Payroll batches, employee deductions, remittance schedules (GSIS / PhilHealth / Pag-IBIG / BIR)',
            'Accounting - Journals'      => 'CRDJ, CKDJ, ADADJ journal register views',
            'Accounting - Disbursement'  => 'Checks, FTLA / ADADJ, cash advances, COA transmittal batches',
            'Accounting - Reports'       => 'FAR reports, financial statements, DBM / COA submission reports',
            'Accounting - Tax & BIR'     => 'Withholding tax computation, BIR Form 2307, alphalist',

            // Treasury
            'Treasury - Bank Master'     => 'Bank account master file, authorised signatories, SOA upload, debit/credit memos',
            'Treasury - Receiving'       => 'Collections, deposit slips, NHA invoice inventory, application of payment',
            'Treasury - Disbursing'      => 'Check preparation/release, FTLA processing, remittances, disbursement reports',
            'Treasury - Cash Position'   => 'Daily cash position computation, available balance by fund source',
            'Treasury - Reconciliation'  => 'Bank reconciliation statements, variance analysis, COA audit',
            'Treasury - Fund Sweeping'   => 'Inward sweep schedule, sweep transactions, NOCA subsidiary ledger',
            'Treasury - Fund Management' => 'Subsidy balance, Trust Fund accounts, MOA contracts',
            'Treasury - Reports'         => 'Cash position reports, disbursement summaries, treasury dashboards'
        ];

        return $descriptions[$tagName] ?? "Operations for {$tagName}";
    }

    /**
     * Add login endpoints for each system
     *
     * LIBIS uses a service-account credential (see
     * `_assets/sql/…/04 - libis_integration_service_account.sql`) and only
     * needs `username` + `password` — the FIMS-only fields (`securitycode`,
     * `expire`, `permission`) don't apply. All other systems keep the full
     * FIMS login schema.
     */
    private function addLoginEndpoints() {
        foreach ($this->systems as $system) {
            $isLibis   = ($system === 'LIBIS');
            // LIBIS integration endpoints live under /LIBIS/api/v1/*; all other
            // FIMS modules use the legacy /{system}/api/login path.
            $loginPath = $isLibis ? "/{$system}/api/v1/login" : "/{$system}/api/login";

            if ($isLibis) {
                $description  = 'Authenticate the LIBIS service account against FIMS UAC and receive a JWT bearer token. '
                              . 'Pass the token as <strong>X-Authorization: Bearer &lt;token&gt;</strong> on subsequent LIBIS integration calls '
                              . '(e.g. <code>GET /LIBIS/api/v1/chart-of-accounts</code>).';
                $properties   = [
                    'username' => ['type' => 'string', 'example' => 'libis_service'],
                    'password' => ['type' => 'string', 'example' => 'your_password', 'format' => 'password'],
                ];
            } else {
                $description  = 'Authenticate against UAC and receive a JWT bearer token. '
                              . 'The same token is valid across all NHA-FIMS subsystems (UAC, FMD, Accounting, Treasury, Notification). '
                              . 'Pass the token as <strong>X-Authorization: Bearer &lt;token&gt;</strong> on all protected routes.';
                $properties   = [
                    'username'     => ['type' => 'string', 'example' => 'fmd_officer'],
                    'password'     => ['type' => 'string', 'example' => 'your_password', 'format' => 'password'],
                    'securitycode' => ['type' => 'string', 'example' => ''],
                    'expire'       => ['type' => 'string', 'example' => ''],
                    'permission'   => ['type' => 'string', 'example' => ''],
                ];
            }

            $this->paths[$loginPath] = [
                'post' => [
                    'tags'        => ['Authentication'],
                    'summary'     => 'User Login',
                    'description' => $description,
                    'operationId' => 'login_' . strtolower($system),
                    'requestBody' => [
                        'required' => true,
                        'content'  => [
                            'application/x-www-form-urlencoded' => [
                                'schema' => [
                                    'type'       => 'object',
                                    'required'   => ['username', 'password'],
                                    'properties' => $properties
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful',
                            'content'     => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/LoginResponse']
                                ]
                            ]
                        ],
                        '400' => [
                            'description' => 'Missing username or password',
                            'content'     => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Error']
                                ]
                            ]
                        ],
                        '401' => [
                            'description' => 'Invalid credentials',
                            'content'     => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Error']
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }
    }

    /**
     * Opt in to pruning component schemas that no path (transitively)
     * references. Deferred — the actual prune runs inside buildOpenAPISpec()
     * after addLoginEndpoints() so lazily-added login paths and their $refs
     * are included in the reachability walk.
     *
     * `initializeComponents()` seeds every schema the toolkit knows about, so
     * a single-system spec still ships the full sidebar (Error, Success,
     * User, FundTracking, ...). Callers on multi-system specs typically skip
     * this — it's for narrow integration doc pages like api-docs-libis.php
     * where the sidebar should be minimal.
     */
    public function pruneUnusedSchemas() {
        $this->pruneUnusedSchemas = true;
        return $this;
    }

    /**
     * Actual prune pass — called from buildOpenAPISpec() when the flag is set.
     * Walks $this->paths for "#/components/schemas/X" $refs and follows refs
     * inside those schemas (breadth-first), then drops anything not reached.
     */
    private function applySchemaPrune() {
        if (empty($this->components['schemas']) || !is_array($this->components['schemas'])) {
            return;
        }

        $schemas   = $this->components['schemas'];
        $reachable = [];
        $frontier  = $this->collectSchemaRefs($this->paths);

        while (!empty($frontier)) {
            $name = array_pop($frontier);
            if (isset($reachable[$name]) || !isset($schemas[$name])) {
                continue;
            }
            $reachable[$name] = true;
            foreach ($this->collectSchemaRefs($schemas[$name]) as $child) {
                if (!isset($reachable[$child])) {
                    $frontier[] = $child;
                }
            }
        }

        $this->components['schemas'] = array_intersect_key($schemas, $reachable);
    }

    /**
     * Recursively collect referenced schema names from an OpenAPI node.
     * Only follows refs of the form "#/components/schemas/<Name>".
     */
    private function collectSchemaRefs($node) {
        $refs = [];
        if (!is_array($node)) {
            return $refs;
        }
        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value)
                && preg_match('#^\#/components/schemas/(.+)$#', $value, $m)) {
                $refs[] = $m[1];
            } elseif (is_array($value)) {
                foreach ($this->collectSchemaRefs($value) as $r) {
                    $refs[] = $r;
                }
            }
        }
        return $refs;
    }

    /**
     * Generate OpenAPI specification
     */
    public function generate() {
        return $this->buildOpenAPISpec();
    }

    /**
     * Render OpenAPI specification
     */
    public function render($format = 'json') {
        $spec = $this->generate();

        switch (strtolower($format)) {
            case 'json':
                return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            case 'yaml':
            case 'yml':
                return $this->arrayToYaml($spec);
            default:
                return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Convert array to YAML (basic implementation)
     */
    private function arrayToYaml($array, $indent = 0) {
        $yaml   = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Numeric array
                    $yaml .= $spaces . $key . ":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $spaces . "  -\n" . $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= $spaces . '  - ' . $this->yamlScalar($item) . "\n";
                        }
                    }
                } else {
                    // Associative array
                    $yaml .= $spaces . $key . ":\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $spaces . $key . ': ' . $this->yamlScalar($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Serialize a scalar value for YAML output
     */
    private function yamlScalar($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_null($value)) {
            return 'null';
        }
        // Quote strings containing YAML special characters
        if (preg_match('/[:#\[\]{}&*!|>\'"%@`]/', (string) $value) || strpos((string) $value, "\n") !== false) {
            return '"' . addcslashes((string) $value, '"\\') . '"';
        }

        return (string) $value;
    }

    /**
     * Map PHP type to OpenAPI type
     */
    private function mapPhpTypeToOpenAPI($phpType) {
        $typeMap = [
            'string'  => 'string',
            'int'     => 'integer',
            'integer' => 'integer',
            'float'   => 'number',
            'double'  => 'number',
            'bool'    => 'boolean',
            'boolean' => 'boolean',
            'array'   => 'array',
            'object'  => 'object',
            'mixed'   => 'string'
        ];

        return $typeMap[$phpType] ?? 'string';
    }

    /**
     * Get return type from reflection method
     */
    private function getReturnType($method) {
        if ($method->hasReturnType()) {
            $returnType = $method->getReturnType();
            return $returnType->getName();
        }
        return 'mixed';
    }

    /**
     * Get parameter type from reflection parameter
     */
    private function getParameterType($param) {
        if ($param->hasType()) {
            $type = $param->getType();
            return $type->getName();
        }
        return 'mixed';
    }

    /**
     * Extract description from doc comment
     */
    private function extractDescriptionFromDoc($docComment) {
        if (!$docComment) {
            return '';
        }

        // Extract description from PHPDoc
        if (preg_match('/\/\*\*\s*\n\s*\*\s*([^\n@]+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract parameter description from doc comment
     */
    private function extractParameterDescription($paramName, $docComment) {
        if (!$docComment) {
            return '';
        }

        // Look for @param documentation
        if (preg_match('/@param\s+\S+\s+\$' . preg_quote($paramName, '/') . '\s+([^\n]+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}