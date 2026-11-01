# CR-1 — docs/32 Webcore Deployment & Portability Amendment Candidate

Status: REFINED CANDIDATE / NON-NORMATIVE / NOT PROMOTED
Technical disposition: CR-1 SLICE 3 SEMANTICALLY RECONCILED / MATERIALIZED; IMPLEMENTATION VALIDATION PENDING
Target authority: docs/32_webcore_deployment_portability_foundation_contract.md
Basis: CD-V3-HTACCESS-20261026-01, Codex Slice 3 technical proposal, GPT semantic refinements, and refined CR-1 Slice 1–2 candidates.
Historical WU1–WU6 portability and layout acceptance is preserved.
Authority boundary: This candidate does not authorize implementation, server configuration changes, runtime or database mutation, contract promotion, Alternative C continuation, or Slice 4.

## 1. Deployment ownership and absolute immutability

Webcore owns the declaration and verification of application routing requirements. The deployment environment exclusively owns physical provisioning and correction of deployment-owned resources, including exact physical `PUBLIC_ROOT/.htaccess`.

COPOT may read, inspect, fingerprint, verify and report that resource, but must never generate, create, edit, replace, rename, remove, restore, rollback, or cleanup-mutate it, including through package building, installation, lifecycle, recovery, cleanup or finalization. Missing, unreadable, incompatible or changed deployment resources are readiness/blocker evidence, never authorization for COPOT autocorrection. The protected file is neither runtime-generated nor package-owned, nor part of V3 package-owned inventory/fingerprint.

## 2. Canonical deployment scope

Routing evidence binds independently verifiable applicable deployment scope: verified installation identity for an existing runtime, or **verified target-installation binding based on installation intent** for fresh installation; canonical `APP_ROOT`, `PUBLIC_ROOT`, public bootstrap entrypoint, `BASE_PATH`, applicable operation/attempt where relevant, and observed provider/server routing context.

Fresh installation does not require a committed installation identity before installation. At commitment, final installation identity must be consistent with the previously verified target-installation binding. Opaque scope hashes cannot replace independently reconstructable verification.

`APP_ROOT` retains private application/configuration/storage/database/recovery/lifecycle boundaries; `PUBLIC_ROOT` is the directly served boundary. Same-root and split-root configurations preserve canonical, verified absolute roots and private-root non-exposure. `BASE_PATH` consistently governs applicable routes, redirects, generated URLs, installer/Admin paths, forms/CSRF targets, and assets.

## 3. Provider-neutral, read-only routing compatibility evidence

Before mutation-bearing fresh install or package update admission, authoritative scoped evidence must establish actual deployed-provider behavior, not just existence of a routing file or successful in-process route dispatch. Applicable checks cover public bootstrap reachability, application route resolution at configured base path, required redirect destinations and status behavior, Admin/login routing, required public static assets, private `APP_ROOT` non-exposure, and identity of the responding route/deployment scope.

Verification must be **read-only and non-mutating**: no form submission, mutation endpoint execution, installation write, or other application-state modification. HTTP 200 alone is not proof of correct routing. Correct expected redirects, including an Admin endpoint redirecting to its proper login path, may be accepted as route evidence without authenticating or mutating Admin state.

A provider-specific read-only probe, deployment-supplied proof, or equivalent verified mechanism may supply evidence. No particular server directive, file format, class, storage layout or universal configuration language is mandated. Apache `.htaccess` is provider-specific and cannot define routing identity universally.

Evidence must provide verifiable scope/provenance, observed behavior, validity/freshness basis, and relevant deployment-configuration identity. A timestamp alone is insufficient. Admission must reject or reverify when relevant deployment configuration changes between verification and the mutation boundary, preventing stale evidence or time-of-check/time-of-use acceptance. This does not mandate a watcher, monitoring service or specific implementation technique. Missing, stale, contradictory, foreign-scope or ambiguous routing evidence fails closed.

## 4. Fresh-install admission

Before filesystem or database mutation, fresh-install admission independently verifies target-installation intent/binding, deployment roots and public/private containment, applicable read-only routing compatibility evidence, base path, required public/Admin routes, assets and relevant provider behavior.

Fresh-install readiness is distinct from database namespace intent, package integrity, manifest correctness, recovery readiness and eventual committed installation identity. A failed routing proof blocks deployment readiness; it does not declare package/database corruption or permit COPOT to repair deployment-owned resources.

## 5. Existing-runtime package-update admission

Before mutation, a package update must independently verify current installation/deployment scope and routing evidence. This admission remains separate from package archive/manifest validation, package-owned inventory/fingerprint, Package Lifecycle operation admission, Maintenance Guard protection and Backup & Recovery readiness.

Missing, stale, changed, incompatible or cross-installation routing proof blocks update before mutation or requires safe re-verification. Lifecycle cannot infer deployed-server compatibility from root resolution or logical route dispatch alone. Routing failure cannot silently change package selection or integrity classification, mutate `.htaccess`, or be misreported as completed recovery.

## 6. Historical deployment compatibility

Previously accepted WU1–WU6 layout and portability outcomes are preserved. An older installation is not automatically invalid solely for lacking the newest routing-evidence representation. Historic proof remains meaningful for its original scope and behavior, not a different installation, provider, base path or current operation.

Current mutation admission still requires fresh compatible evidence or an explicitly verified bounded-equivalent proof. Without it, the deployment may remain readable or diagnosable while mutation-bearing admission fails closed. Historical evidence is not silently normalized or rewritten.

## 7. Recovery and protected-resource coordination

Package Lifecycle (docs/28) owns package inventory, operation admission and mutation disposition; exact protected physical `.htaccess` is excluded from package-owned mutation and fingerprints. Backup & Recovery (docs/31) retains recovery evidence, protected-resource mutation blockers and recovery-specific settlement. Maintenance Guard, InstallationMutex and DatabaseQuiescenceCapability remain independent from deployment routing authority.

A routing incompatibility discovered **before mutation** is a deployment-readiness failure, not automatically a recovery failure. During recovery or cleanup, only a routing blocker relevant to that operation's applicable recovery/settlement obligation may prevent its owning lifecycle from reporting terminal success. Unrelated routing failures do not automatically prohibit recovery `CLEANED`. Unresolved prohibited mutation of protected `.htaccess` is never silently skipped or presented as successful completion.

## 8. Acceptance and validation boundaries

Normative requirements before promotion: physical deployment ownership and absolute protected-resource immutability; canonical existing/target-installation scope; provider-neutral non-mutating behavioral proof; freshness and configuration binding including TOCTOU rejection/reverification; fail-closed pre-mutation install/update admission; distinct deployment, package and recovery classifications; historical compatibility; no automatic server correction.

Implementation validation remains outstanding for provider-specific verifier/probe, installer/update admission wiring, actual redirects and Admin login routing, static assets and private-root non-exposure, Apache/subdirectory and split-root cases plus applicable other providers, negative stale/foreign/ambiguous evidence, historical fixtures, and exact `.htaccess` preservation across failed lifecycle and recovery paths. No runtime test result is implied by this candidate.

## 9. Explicit exclusions

No universal web-server configuration engine, automatic `.htaccess` generation/repair, COPOT-owned server configuration, new migration/portability framework, parallel recovery or lifecycle authority, Server-Empty Bootstrap, hosting-panel automation, or byte-identical provider directives. Contract promotion and implementation/deployment remain separate approval gates.

## 10. Controlled-deviation promotion prerequisite (pre-promotion clarification)

The documented `CD-V3-HTACCESS-20261026-01` remains **OPEN**. Its accepted direction overrides incompatible old-V3 expectations only within that recorded bounded deviation, not by silent reinterpretation of historical WU1–WU6 acceptance, package identities, or deployment evidence. The canonical Workplan states that normative contract reconciliation/promotion follows **verified implementation evidence** for this deviation. This candidate does not waive or replace that prerequisite.

Before any coordinated normative promotion, record a traceable disposition identifying the exact applicable evidence and whether that prerequisite is satisfied. If evidence is insufficient, promotion remains gated unless the user separately approves a legitimate change to that prerequisite and the authoritative planning/decision record is reconciled. Neither contract drafting nor source-only review is evidence of operational acceptance. Full deviation closure still requires its distinct implementation, SDR and acceptance conditions; promotion alone does not close it.

This amendment defines routing **requirements and admission evidence**, not a COPOT-owned server configuration authority. docs/28 consumes its scoped routing-readiness result and docs/31 retains recovery-specific status; neither old portability acceptance nor this proposal gives permission to mutate physical `PUBLIC_ROOT/.htaccess`.
