# CR-1 — docs/31 Backup & Recovery Coordinated Amendment Candidate

Status: CANDIDATE / NON-NORMATIVE / NOT PROMOTED
Target authority: docs/31_backup_recovery_foundation_contract.md
Basis: CD-V3-HTACCESS-20261026-01 and LCR-03/04/06/07/08.
Preserve immutable recovery manifests, original-byte restore guarantees for permitted domains, existing quiescence, and WU6. No new recovery engine or implicit operational authorization.

## Proposed addendum A — Protected deployment resource

Exact PUBLIC_ROOT/.htaccess is deployment-owned and absolutely immutable to COPOT. Recovery may inspect and verify routing compatibility read-only but cannot create, overwrite, rename, remove, restore, rollback or cleanup-mutate the protected physical resource. A recovery plan requiring that mutation fails closed before capture or side effects; no silent skip or false RESTORED/COMPLETE. Immutable historical recovery evidence referencing the file remains preserved, without becoming mutation permission. Existing permitted package-owned file/database original-byte recovery obligations remain enforceable.

## Proposed addendum B — Operation and execution-attempt recovery binding

A recovery identity, immutable manifest, artifact evidence, and mutable recovery lifecycle must bind the authoritative operation and immutable execution attempt when that operation is attempt-aware. Lookups must distinguish same-operation different attempts, reject stale captures and ambiguous candidates, and never attribute an earlier attempt's evidence to current authorization.

Recovery artifact enumeration must reject unexpected, unreadable, orphan, manifestless, cross-namespace or ambiguous residue rather than treat its absence from matching lookup as an empty safe store. Manifest storage namespace, recovery-set identity, operation and attempt, installation/database namespace and physical tableset must be integrity-verified. A historical null-attempt record remains historical; no synthetic normalization.

First-capture must use persisted lifecycle operation/attempt authority and independently verified installation, namespace, physical tableset and DB state. When pre/post consistency evidence differs, fail before mutation. Maintenance Guard evidence does not establish database snapshot consistency; authorized DatabaseQuiescenceCapability/transitional alternatives remain distinct.

READY resume only reconstitutes an existing valid, captured recovery lifecycle, with fresh scoped protection, exclusive ownership and independent database consistency. It must not create a synthetic recovery state, skip immutable manifest verification or waive confirmation/permit requirements.

Session cleanup and Guard release cannot erase outstanding RESTORE_REQUIRED, post-mutation uncertainty, durable capture, verification or cleanup obligations. RESTORED/CLOSED states require existing canonical proof. The recovery journal and Package Lifecycle operation record cross-reference exact operation/attempt/plan/recovery identities.

## Required verification

Manifest/capture integrity and orphan/namespace negatives, same-operation cross-attempt isolation, interruption across capture and mutate boundaries, first-capture DB state comparison, READY rehydration, release against outstanding obligations, original-byte round trip, unrelated installation isolation and WU6. NO TEST EXECUTION CLAIMED.
