# `_shared/branding/` — published document branding assets

Runtime state. **Do not edit these files by hand and do not commit them** — they
are overwritten every time someone activates an asset in UAC.

## What is in here

| File | Written by | Read by |
|---|---|---|
| `manifest.json` | `DocumentHeaderService::syncBrandingFiles()` | `_shared/docx-branding.js` |
| `header-<system>-<document>.png` | same | same |
| `footer-<system>-<document>.png` | same | same |

`<system>` and `<document>` are lowercased scope slugs, or `all` when the asset
applies everywhere — so the global letterhead is `header-all-all.png` and a
REGIONAL-only CBA letterhead is `header-regional-cba.png`.

## Why it exists

The source of truth is the database table `uac.document_header_assets`, managed
through **UAC → Header Management**. But the DOCX generators are short-lived
`node gen_x_docx.js data.json out.docx` processes with no database credentials,
so the active assets are mirrored here and the generators resolve them off disk.

## Resolution order

`_shared/docx-branding.js` picks the most specific active asset:

1. base64 handed in by the caller (`data._branding.header`)
2. manifest entry for system **and** document
3. manifest entry for the document
4. manifest entry for the system
5. the global default
6. the generator's own embedded base64 literal

Step 6 is the safety net: with this directory empty, every generator prints
exactly as it did before header management existed.

## Deployment notes

- The directory must exist and be **writable by the PHP process**. UAC creates
  it on first sync (mode `0775`), but on a read-only or differently-owned
  deployment you need to create it yourself.
- Nothing needs to be seeded. An empty directory is a valid state.
- If an activation seems not to take effect, check
  `GET /UAC/api/doc-headers/sync-status`, which reports whether the directory
  exists, whether it is writable, and what the manifest currently claims.
