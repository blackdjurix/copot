# COPOT CR-1 S1 — .htaccess Coordinated Contract Delta Proposal

Status: REVIEWED PLANNING DELTA / NON-NORMATIVE / NOT PROMOTED
Controlled deviation: CD-V3-HTACCESS-20261026-01
Scope: docs/28, docs/31, docs/32; existing accepted v3 and recovery semantics preserved except the explicitly recorded controlled breach.
Authority: This file is not a contract amendment, code authorization, release, or E4 acceptance.

## 1. Authority and ownership

The exact physical resource PUBLIC_ROOT/.htaccess is owned by DEPLOYMENT_ENVIRONMENT. Webcore owns required routing behavior, not physical deployment bytes. COPOT-wide ABSOLUTE_IMMUTABILITY forbids create, edit, replace, rename source/destination, remove, restore and rollback mutation against that exact resource. Read, inspect, validate and report are allowed. External deployment administration and operating-system permissions are outside this restriction.

Do not generalize this rule to every file called .htaccess or automatically adopt a universal protection policy framework.

## 2. Proposed docs/28 changes — Package Lifecycle V3

- Both full and partial V3 packages exclude PUBLIC_ROOT/.htaccess from delivered package-owned inventory, resulting target inventory and fingerprint. Full continues to require all resulting *package-owned* bytes.
- Manifest admission rejects mutations to the protected target before any side effect. Inventory, rename/removal validation, planner, apply and final mutation guard enforce the same rule. Prohibited entries cannot be silently skipped.
- Historical manifest/ZIP/inventory identity and installed-state records remain immutable evidence. Historical PACKAGE_OWNED claims do not supply active mutation authority; old identity cannot be silently rewritten or equated with a new package identity.
- New revised-semantic package must have independently verified identity. Source-authority and committed inventory reconciliation must explicitly account for the ownership transition.
- Routing compatibility verification is a separate admission requirement; package validity and deployment readiness must not be conflated.

## 3. Proposed docs/31 changes — Backup & Recovery

- Recovery capture, restore, rollback, cleanup, path guards and action plans must not physically mutate deployment-owned PUBLIC_ROOT/.htaccess.
- Historical capture evidence can remain readable/immutable without becoming permission to restore the protected file.
- If a recovery plan requires protected resource mutation, reject before capture or filesystem mutation rather than masking required work as a successful skip.
- Route read-only compatibility checks separately. Recovery is not RESTORED/COMPLETE if applicable obligations are missing or unverified.
- Original-byte, immutable-manifest, cross-domain, retry and recovery-required guarantees for legitimate package-owned domains remain intact. No parallel recovery engine.

## 4. Proposed docs/32 changes — Deployment & Portability

- PUBLIC_ROOT is the routing/deployment boundary; physical .htaccess provisioning or correction belongs exclusively to deployment administration external to COPOT.
- Webcore declares routing requirements: valid root, base path, entrypoint, public URLs, redirects and applicable routing behavior.
- Fresh-install and update admission verify compatible routing and fail closed on missing/invalid/unverified behavior before mutation. COPOT may diagnose and report, not auto-generate or modify protected .htaccess.
- Apache .htaccess is a provider-specific routing mechanism, not a universal required file for every hosting provider.
- Existing same-root/split-root portability requirements are preserved.

## 5. Historical artifact disposition

Preserve historical ZIP, manifests, fingerprints, recovery records, and actual runtime evidence unchanged. They are valid historical records but not automatically eligible for revised-semantic operations. Neither delete the old evidence nor launder identity through silent reinterpretation. Any existing installation transition requires an explicit proven compatibility/reconciliation path.

## 6. Shared invariants

HTA-01 One effective physical owner; no layered ownership.
HTA-02 COPOT-scope absolute immutability across *all* mutation surfaces.
HTA-03 Package grammar/identity and routing readiness are independent checks.
HTA-04 Historical claims are evidence, not active mutation permissions.
HTA-05 Fail before side effects and reconfirm at final mutation boundary.
HTA-06 Recovery and health outcomes must reflect unresolved routing incompatibility truthfully.

## 7. Evidence and acceptance gate

Required focused checks: manifest add/replace/remove/rename rejection; full/partial inventory and fingerprint; planner/executor denial; failure/interruption/rollback byte preservation; fresh-install/update routing verification; historical package disposition; HTA-N01–HTA-N07 negative tests with protected-file SHA-256 equality. Verify a separately identified new package and disposable-runtime fresh-install/routing/full-partial/recovery/source-authority acceptance before claiming Alternative C eligibility. No such tests are claimed performed by CR-1.

## 8. Controlled deviation and promotion

The deviation recorded in workplan.md explicitly remains open until coordinated authoritative amendments, verified technical evidence and operational acceptance. Legacy planning text suggesting COPOT-managed directive editing is superseded by immutability; external deployment edits remain independent. Contract promotion is separate and not granted by this file.
