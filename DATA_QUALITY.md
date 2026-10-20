# Source data QA — October 2026

## Source counts

- `Regulatory Global RA Contact List (2).csv`: 50 data rows, 49 unique email addresses; 7 distinct BU codes; 2 blank position fields.
- `Regulatory Local RA Contacts List (4).csv`: 149 data rows, 145 distinct country labels, 10 distinct source-region labels, 39 missing email addresses. Duplicate country entries: Belgium, China, Panama and Syria.
- `MAT data base.xlsx`: 285 data rows, 48 original columns, 69 distinct nonblank SAP values, 11 country names, 14 rows with blank country; 252 active registration labels, 11 inactive labels, 22 blank/unknown; 224 active country commercial status labels, 39 explicitly discontinued-in-country labels, 8 literal `INACTIVE` values and 14 blank values.

## Noteworthy mapping issues

- 8 country names need manually approved ISO mappings: `Bonaire, Saint Eustatius and Saba`, `Burma`, `Dutch Antilles`, `East Timor`, `Kosovo (TW)`, `Lichtenstein`, `Montenegro (TW)`, `Serbia (TW)`. These entries are **not silently mapped**.
- `French Guyana` mapped to ISO `GF` (standard English name `French Guiana`); `Reunion` to `RE`; aliases retained as displayed in source. Review country naming before using in client-facing exports.
- Local RA region `US` is mapped to USA assignment; `EU` and `CEE (EU)` mapped to EU assignment. All other source regions default to OTHER (including `EFTA Non-EU`). United Kingdom assigned to `UK` canonical region by explicit name.
- Blank assessor email is staged as an exception, even where a human-readable name or `Business Unit RA` contact label exists. No dummy email/account is created.
- Both global and local contacts are imported as inactive accounts, with long random passwords; imported contacts must be provisioned/approved by an administrator.
- `COUNTRY_COMMERCIAL_STATUS` and `GLOBAL_COMMERCIAL_STATUS` are separate commercial attributes; `REGULATORY_CERT_STATUS` is the only basis for provisional active/inactive regulatory status. A regulatory `Active:` value with historical/expired certificate dates requires separate business-policy review before automating assignment.
- Some columns in the MAT source are not included in the compact normalized schema; full row is preserved as JSON in staging and registration records.

## Required client decisions before real operational use

1. Authoritative country ISO mapping and country aliases, especially deprecated/geopolitical label variants.
2. Which regional groupings should use creator assignment versus Local RA; validation of UK/USA overrides.
3. Whether repeated local country rows are backup assignments, joint coverage or BU-specific contacts.
4. Whether blank-email contact placeholders must be routed to Global RA / BU RA instead of blocking country assignment.
5. Regulatory eligibility policy considering active status, expiry, country commercial status, global discontinuation and NPD.
6. SAP identity/uniqueness and handling different revisions/registrations for same country.
