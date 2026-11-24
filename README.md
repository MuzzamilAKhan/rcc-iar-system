# CMSoftware — Change Control & Regulatory Intelligence Platform

> **Current CCR5A development checkpoint (25 November 2026):** verified GitHub baseline `88a1341` (CCR4C; 149 tests / 690 assertions). CCR5A is a proposed-action register being installed/tested **locally**, not deployed to Contabo. See later sections for historical planning notes; some older milestone statements are superseded by this checkpoint.

### CCR5A scope

- CCR owner / Super Admin records **proposed** actions, authorized owners, due dates, optional selected impact area and dependency on an earlier action in the same CCR.
- Assigned owners have a read-only **My CCR actions** queue. All action creation is audited. No automatic email or change approval.
- Action completion, evidence verification, reassignment, editing, QA/CRB approval and implementation are **not yet enabled**.

> **Project status (23 November 2026): Active development / local UAT.**  
> **Last verified Git baseline before CCR4B:** `1336dbf` (`main`, CCR4A). **CCR4B is local-only and not yet deployed.**
> **Legacy application branding:** RegAtlas / RCC-IAR. **Target branding:** CMSoftware (client-requested; global rebrand pending).  
> **Not a validated production medical-device quality or regulatory decision system.** All regulatory conclusions and approvals require qualified human review.

## 1. Project overview

CMSoftware is a proposed, configurable medical-device **Change Control Management System** with product traceability, cross-functional impact assessment, market-specific regulatory assessment, evidence management, human review, controlled actions, and eventual implementation and closure. It is being developed by extending an existing Laravel application that already provides a Product Registry and Regulatory Impact Assessment (IAR) workflow.

The **working application today** is still branded **RegAtlas** in most screens. The original repository and deployment path retain the name `rcc-iar-system`; do not rename directories, Git remotes or production URLs as part of a cosmetic rebrand.

### Product principles

- **Human accountability:** AI may suggest and draft; authorized SMEs, RA, QA and approvers own decisions.
- **Traceability:** device/SAP identity, impacted markets, rationale, document revisions, assignments, decisions and audit events should be linked.
- **Controlled workflows:** states, guards, independent reviews and explicit approvals must be enforced on the server.
- **Company configurability:** SOPs, questionnaires, role mappings and approval workflows may differ by company; avoid treating example SOPs as universally binding.
- **Regulatory versioning:** standards and guidance are centrally catalogued only where licensing permits, but versions, dates and regional applicability change over time.
- **Privacy and least privilege:** sensitive source documents and company data must not be publicly exposed or indiscriminately sent to third-party AI systems.

## 2. Technology and working environments

| Item | Current configuration |
| --- | --- |
| Backend | PHP / Laravel 12 |
| Interactive UI | Livewire 4, Blade, Vite, CSS/JS |
| Authorization | Laravel auth, active-user middleware, Spatie roles/permissions |
| Local development | WAMP on Windows, PHP 8.3 |
| Local project directory | `C:\wamp64\www\rcc-iar-system` |
| Local app | `http://localhost/rcc-iar-system/public/` |
| Git repository | `https://github.com/MuzzamilAKhan/rcc-iar-system.git` |
| Main branch | `main` |
| Contabo server | PHP 8.4.x; UAT environment, **not a deployment target for untested changes** |
| Existing Contabo URL | `http://194.163.144.10/rcc-iar-system/public/` — **must remain unchanged unless explicitly approved** |

**Deployment warning:** The VPS has historically lagged behind local/GitHub. Its latest verified deployed baseline before this CCR work was `d4f0b1b`; **do not assume any CCR migrations or screens exist on VPS**. Check actual server state before planning any future deployment. HTTP without TLS is unsuitable for real confidential client data.

## 3. Functional architecture

```text
CMSoftware (target product)
│
├── Identity, roles, access controls, audit / notification infrastructure
├── Product Registry
│   ├── SAP/device master data and business units
│   ├── Product-country regulatory registrations
│   ├── Controlled import preview, issues, approvals and export
│   └── Global RA / Local RA mapping
├── Existing IAR (Regulatory Impact Assessment) module
│   ├── Draft creation, product linkage and SAP resolution
│   ├── Country-specific assignments / justified exceptions
│   ├── Submission readiness, audited submission, assessor drafts
│   └── Reminder preview, in-app history, email preparation and outbox
└── Change Control Record (CCR) module
    ├── Change intake and device linkage                            [Implemented]
    ├── 16-area cross-functional applicability decisions            [Implemented]
    ├── SME assignments and starter screening questions             [Implemented]
    ├── SME draft responses / evidence references                   [Implemented]
    ├── SME submission, independent human review, correction loop   [Implemented]
    ├── Mandatory market-specific regulatory screening              [Planned]
    ├── Controlled cross-functional questionnaire library           [Planned]
    ├── Actions, owners, dependencies, deadlines                     [Planned]
    ├── QA/Change Review Board approvals                             [Planned]
    ├── Implementation controls / market release gates              [Planned]
    ├── Final QA review, closure, extensions and cancellations       [Planned]
    └── Controlled knowledge base and AI-assisted drafts            [Planned]
```

The existing IAR module and new CCR module are **currently separate**. They should be integrated through explicit, authorized references, not by assuming a CCR approval equals an IAR assessment or vice versa.

## 4. Implemented functionality — verified through CCR3B

### 4.1 Foundation and Product Registry

- User provisioning, active-account checks, roles and permissions.
- Product master data, SAP identifiers, registration-by-country records, import preview/issues, admin review/approval, export and audit logs.
- Local demo users for controlled synthetic UAT; imported client contacts are not to be activated or assigned without authorization.
- UI with responsive sidebar and light/dark theme (currently primarily **RegAtlas** branding).

### 4.2 Existing IAR module

- Creator-owned IAR drafts, linked products, SAP code resolution and validation.
- Submission readiness and auditable POST-only submission.
- **Partial-country strategy:** eligible/registered countries with an active assigned assessor proceed; unmatched/unmapped applicable countries remain auditable open assignment exceptions; zero ready countries cannot submit.
- Pending country exception resolution only after an authorized active primary Local RA mapping exists; original due date preserved.
- Assigned assessor draft workspace with scoped authorization.
- Reminder previews after 14 calendar days, then weekly cycles; in-app reminder staging/read-unread, scoped mail previews and renderable Mailable, idempotent email outbox.
- **No automatic SMTP dispatch or scheduler is enabled as a completed production service.** Outbox creation does not mean delivery.

### 4.3 New CCR implementation

| Phase | Status | Verified scope |
| --- | --- | --- |
| **CCR1** | Committed `5d185a6` | Create draft CCR, automatic CCR number, link Product Registry devices, change type, current/proposed state, reason, affected scope, duration/date, audit. |
| **CCR2** | Committed `e235c82` | Explicit Applicable / Not Applicable decisions for 16 areas, rule-based suggestions, exclusion rationale, audit and deduplication. |
| **CCR3A** | Committed `d02ba92` | One active RA SME assignment per selected area, starter screening question, audit, no silent reassignment. |
| **CCR3B** | Committed `9cb597c` | Assignee-only SME drafts with answer, provisional conclusion, rationale, evidence references, audit and repeat-save idempotency. |
| **CCR3C** | Committed `9fdb399` | SME submission and edit lock, independent Super Admin review, accept/return with required comment, correction and resubmission, review queue and sidebar link. |

**Latest observed local test run:** `137 passed (609 assertions)` on 22 November 2026 (Pakistan local early 23 November), including CCR3C. Local CCR3C migration `2026_11_23_000004_add_ccr_sme_review_fields` completed; Blade cache succeeded. **Passing tests does not imply a regulatory validation, production readiness or a completed Git commit.**

### 4.4 Current synthetic UAT example

- `CCR-2026-000001`: change of raw material supplier for a medical-device component; **draft**.
- Linked test product: SAP `1762879` (`GC AIR POCKET COUDE CH10 10PK EU`).
- Sixteen impact areas explicitly reviewed; eleven selected in the synthetic scenario.
- Initial SME assignments: Materials and Manufacturing → Demo Local RA; Risk Management and Regulatory → Demo Global RA.
- Example Materials response uses uncertain screening and further assessment required with **dummy** document identifiers (`TEST-*`).
- Independent review return/resubmission was tested; **SME review is not regulatory, QA/CRB or CCR approval**.

## 5. Sixteen cross-functional impact areas

1. Design & Performance
2. Materials & Biological Safety
3. Manufacturing
4. Sterilisation
5. Packaging
6. Shelf Life / Stability
7. Clinical
8. Risk Management
9. Software
10. Labelling & Artwork
11. Databases / Master Data
12. Regulatory
13. Supply Chain
14. Commercial
15. Quality Assurance
16. Supplier Quality / Supply Chain Quality

The list is currently a **starter fixed catalog** (`app/Services/Ccr/ImpactAreaCatalog.php`); it is not yet a configurable, validated company-controlled questionnaire. Rule-based suggestions are hints and are **not AI conclusions**. Regulatory market screening should remain mandatory even if a user marks the generic Regulatory area not applicable.

## 6. Target end-to-end business workflow (not fully implemented)

```text
Initiate change request
  → identify affected devices, parts, sites and documents
  → characterize change and attach reason/evidence
  → select applicable impact areas (with documented exclusions)
  → assign SME and regulatory assessments
  → obtain cross-functional assessments and human review
  → perform mandatory per-market regulatory pathway screening
  → generate and review implementation / documentation actions
  → conduct QA and CRB approval (independent roles, readiness gates)
  → implement with prerequisite checks and market-specific release blocks
  → verify evidence, documentation and completed actions
  → final QA/authorized review → close or follow an exception/extension route
```

The system must eventually support returned assessments, reassessment on material change, emergency change controls, cancelled changes, extensions and post-closure commitments. **Do not automatically approve or release devices on the basis of an AI answer or an SME review alone.**

## 7. Planned technical modules and key design decisions

### 7.1 Mandatory regulatory screening (CCR4 — next major milestone)

- Derive potentially applicable markets from linked products and product-country registrations; preserve source provenance and allow justified exceptions.
- Screen EU, Great Britain, Northern Ireland, United States and other registered countries according to validated applicability rules; do not assume EU and NI are identical, or that all non-EU markets follow one rule.
- Store per-market screening state, assessor, question answers, rationale, document citations/versions and human decisions.
- Prevent implementation/market release until relevant regulatory gates are satisfied or explicitly authorized exceptions exist.
- Reuse existing IAR services **only where semantics match**; preserve independent CCR versus IAR lifecycles.

### 7.2 Controlled questionnaires and document management

- Version questionnaires by company, assessment area and effective date.
- Distinguish **potentially affected** documents from **confirmed affected** documents; record explicit user confirmation.
- Track controlled document ID, revision, effective date, market, supersession and access permissions.
- Use **company-specific SOPs** as configuration/reference; do not hard-code the example company's SOP into all tenants.
- Maintain regulatory standards/guidance centrally where legally and contractually permissible; track changing revisions and applicability.
- Purchased standards are licensed copyright material. Validate storage, permitted users and AI/vendor processing rights before ingestion.

### 7.3 Action, approval, implementation and closure

- Action owners, due dates, dependencies, evidence, completion review and overdue reminders.
- Separate SME review, regulatory pathway decision, QA pre-approval, CRB approval, implementation authorization and final closure review.
- Record independent approvers, versioned decision snapshots and immutable audit history.
- Block implementation on incomplete actions, unresolved risk/regulatory gating or outstanding mandatory approvals.
- Design explicit workflows for rejection, reassessment, emergency change, cancellation, extension, rollback and post-closure commitments.

### 7.4 AI-assisted draft assessments (planned, not live)

- Retrieval from **approved** regulatory sources, company SOPs, device/technical documentation and controlled forms.
- Market-specific draft suggestions for EU/GB/US first; other jurisdictions require scoped manual workflows unless validated coverage exists.
- Every suggested answer should include source ID, section, revision, as-of date, uncertainty and a clear AI-generated label.
- Human confirm/edit/reject steps, audit record of AI suggestion and final reviewer responsibility.
- No automated filing/submission, autonomous regulatory approvals, unrestricted upload of licensed documents or hidden third-party data sharing.
- AI provider, budget, credentials, legal rights and validation plan are not finalized.

### 7.5 Multi-company architecture (target, not current isolation guarantee)

A future `companies` / `tenants` boundary may own SOPs, review rules, document libraries, roles and company data. Current application **must not be represented as fully tenant-isolated** without database scoping, authorization, automated cross-tenant tests and security review. Central standard metadata and company-specific licensed-document access need separate models.

## 8. Client source documents / requirements references

Client-supplied materials reviewed during planning include:

- **AI-automation for change control process_REVISED** — end-to-end process and assessment scope.
- **Standards.docx** — FDA, EU, UK regulatory resource references.
- **SOP-000016** — example company change-control SOP.
- **WI-0332** — example company work instruction for regulatory change determinations.
- **FORM-025699** — example FDA 510(k) Letter-to-File form.
- **Errors in Core Logic** — Copilot-generated design critique, for review rather than blind adoption.

The WI references **FORM-003481**, which has not been supplied in the reviewed set. Additional SOPs and purchased standards may be sent later. **These source files are not automatically public repository assets, nor does their presence imply a license to upload them to external AI services.**

## 9. Core database tables (selected; schema evolves)

- Product and reference tables: `products`, `product_registrations`, country and business unit mappings, import staging/issues.
- IAR: `impact_assessments`, IAR product associations, `iar_assignments`, `assessment_responses`, `assignment_exceptions`.
- Operations: `audit_logs`, `notification_logs`, `iar_email_outbox`.
- CCR: `change_control_records`, `ccr_products`, `ccr_impact_decisions`, `ccr_sme_assignments`, `ccr_sme_responses`.
- CCR3C migration extends `ccr_sme_assignments` with review metadata; it does **not** imply a new CCR approval state machine.

Use Laravel migrations as the definitive schema source. This README is a high-level map, not a substitute for inspecting current code.

## 10. Local setup and safe development workflow

Prerequisites: WAMP/PHP and Composer dependencies matching the project, Node.js/npm, a configured **local** `.env`, a local database and existing permissions. Never commit `.env` or private documents.

```cmd
cd /d C:\wamp64\www\rcc-iar-system

git status
git log -1 --oneline

composer install
npm install
npm run build

php artisan optimize:clear
php artisan migrate:status
php artisan test
php artisan view:cache
```

**Migration rule:** Run `php artisan migrate` **only after confirming the active `.env` points to the intended local database and reviewing pending migrations**. Test suites use their configured test DB. Never execute `migrate:fresh`, reset, destructive seeders or import seeders against client UAT data.

### Safe Git workflow

1. Confirm baseline and clean tree: `git status --short` and `git log -1 --oneline`.
2. Apply a narrowly scoped local patch / installer; installer backup folders are excluded in `.git/info/exclude` and not committed.
3. Run targeted feature tests and the full suite; conduct manual role-based UAT.
4. Review `git diff`, `git status --short`, and untracked files.
5. Stage **specific intended paths only**. **Do not use `git add .`**.
6. Run `git diff --cached --stat` and `git diff --cached --check`.
7. Commit and push only after successful checks and explicit validation.
8. No automatic Contabo deployment; separately plan backups, migrations, UAT and rollback.

### Useful local URLs

- Change controls: `http://localhost/rcc-iar-system/public/change-controls`
- Test CCR detail: `http://localhost/rcc-iar-system/public/change-controls/1` (only if local record ID is 1)
- Assigned SME queue: `http://localhost/rcc-iar-system/public/my/ccr-assessments`
- Independent Super Admin queue: `http://localhost/rcc-iar-system/public/ccr-sme-reviews`
- Existing IAR dashboard: `http://localhost/rcc-iar-system/public/iars`

The sidebar should expose the reviewer queue only to authorized reviewers; **backend permission checks must also block direct unauthorized URL access**.

## 11. Security, compliance and deployment guardrails

- Do not put real client personal/contact data, passwords, purchased standards or proprietary SOPs in public Git, screenshots or logs.
- Keep `.env`, database snapshots and private imported files out of Git. Rotate credentials if exposed.
- Authentication plus per-record authorization is required for every sensitive operation; hiding a navigation item is not security.
- Audit state changes with actor, timestamp, entity, previous/new state and relevant reason; avoid exposing restricted document content in shared audit views.
- Prefer idempotent POST operations, server-side transactions/locks and unique database constraints.
- Ensure same assessor cannot independently review their own submission; broader independent QA/CRB role matrices remain future work.
- Before external AI processing, confirm contractual rights, data residency/retention, licensed document terms and scope of disclosure.
- Do not claim ISO 13485, ISO 14971, EU MDR, UK MDR or FDA compliance merely because fields/workflows exist. Validation, documented procedures and qualified regulatory review are separate activities.
- Preserve Contabo URL `http://194.163.144.10/rcc-iar-system/public/` until explicitly approved; put TLS/privacy remediation on production-readiness checklist.
- **Never deploy local CCR changes or run new migrations on client VPS without deliberate authorization, backups and tested release plan.**

## 12. Release plan and current handoff

**Historical CCR3C handoff (completed at `9fdb399`):**

1. Confirm latest local Git tree still has the intended CCR3C modified/new files and nothing unrelated.
2. Confirm submitted/reviewed SME screen hides `Save SME draft` and returned/draft states can save; verify form opening tag and CSRF.
3. Confirm `CCR review queue` appears for Super Admin and is backend-protected from other roles.
4. Re-run `php artisan test --filter=CcrSmeReviewTest` and `php artisan test` (last observed: **137 tests, 609 assertions**).
5. Review and stage specific CCR3C files only, then commit/push; record resulting new Git commit here.

**Next development priorities:**

1. **CCR4:** mandatory per-market regulatory screening tied to linked products and registered markets, with human decisions and auditable exceptions.
2. **CCR5:** actions/dependencies and independent QA/CRB approval readiness.
3. **CCR6:** implementation release controls, verification and closure.
4. **AI/Knowledge Base:** controlled SOP/standard metadata, retrieval with citations, draft suggestions, data/licensing review and validation.
5. **UX/Branding:** global CMSoftware naming, logo and client palette (white, royal purple, lilac, gold, ivory); existing teal RegAtlas UI is interim.
6. **Operational hardening:** filters, due dates, reminders/controlled email sending, security review, tenant isolation (if multi-company confirmed), comprehensive UAT.

**Scheduling:** A 2–3-week end-to-end **working MVP** was discussed as an aggressive target, not a guarantee of a validated, fully compliant, multi-company, AI-automated production system. Requirements, budget, standards licensing and acceptance criteria require explicit agreement.

## 13. Documentation maintenance

Update this README at every verified Git checkpoint: current commit, test counts, schema changes, implemented features versus pending scope, and safe next steps. Keep architecture statements truthful to the source code. Never list an uncommitted local change as deployed or production-ready.

---

**Last updated:** 24 November 2026 (Pakistan local time).
**Repository:** `MuzzamilAKhan/rcc-iar-system`  
**Product label:** CMSoftware (planned) · RegAtlas (current UI / legacy identity)


### CCR4A local-only installation (24 November 2026)

CCR4A adds explicit mandatory per-country regulatory screening discovery from linked product registrations, a source-provenance snapshot, owner/admin access control, idempotent audit and an unresolved-coverage warning. **It does not decide regulatory pathways or grant approval.** Until committed, this subsection describes local intended work only. Run `php artisan test --filter=CcrRegulatoryMarketTest` and the full test suite before migrating on a verified local database.

### CCR5B — controlled document impacts and evidence references (local-only)

Based on committed CCR5A checkpoint `16d4808`, CCR5B adds `ccr_document_impacts` (potential vs explicitly confirmed document impact) and `ccr_evidence_references` (metadata-only source references). Selected impact area and proposed action links are constrained to the same CCR. Record creation and explicit impact confirmation are audited. These records do **not** approve document revisions, verify authenticity of source evidence, authorize implementation, or grant regulatory/QA/CRB approvals. Local Laravel tests and database migration must be run before the new files are committed. Contabo remains untouched.

### CCR5C — Action evidence preparation and independent review (local UAT)

- Base checkpoint: `7e2db23` (CCR5B), confirmed clean before patch.
- Action owners may draft and submit **preliminary evidence references** with explanations; Super Admin may review or return for correction.
- Review acceptance means the evidence *package was reviewed*, **not** verification that an action is complete, that a controlled document is approved, or that a change may be implemented. `ccr_actions.status` stays `proposed`.
- This phase does not implement QA/CRB approval, action execution authorization, regulatory clearance, market release or CCR closure.
- Migration: `2026_11_25_000003_create_ccr_action_evidence_reviews`; do not run on VPS without release approval.
- Targeted tests: `php artisan test --filter=CcrActionEvidenceTest`; full suite expected to add four tests to prior 157.
