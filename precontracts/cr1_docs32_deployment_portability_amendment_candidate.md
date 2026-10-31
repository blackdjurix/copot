# CR-1 — docs/32 Webcore Deployment & Portability Amendment Candidate

Status: CANDIDATE / NON-NORMATIVE / NOT PROMOTED
Target authority: docs/32_webcore_deployment_portability_foundation_contract.md
Basis: CD-V3-HTACCESS-20261026-01.
Historical WU1–WU6 portability and layout acceptance is not rewritten.

## Proposed addendum — Deployment physical ownership vs routing requirements

Webcore declares and verifies routing requirements. DEPLOYMENT_ENVIRONMENT exclusively owns physical provisioning/correction of exact PUBLIC_ROOT/.htaccess. COPOT has absolute immutability across package builder, installer, lifecycle, recovery, cleanup and rollback mutation. It may read/inspect/verify/report, never generate, create, edit, replace, rename or restore that physical file. External deployment/cPanel/server administration remains outside COPOT authority.

PUBLIC_ROOT remains the deployment boundary; APP_ROOT/PUBLIC_ROOT roots, public entrypoint, base path, redirects, Admin and assets must be compatible with the deployed routing mechanism. Apache .htaccess is provider-specific and cannot be treated as universal routing identity for other server technologies. Verification must target required behavior and identity, not force byte-identical directives across deployment configurations.

Fresh installation and package updates require explicit fail-closed routing admission on missing, incompatible or unverifiable behavior before filesystem/database mutation. Routing failure is a deployment-readiness blocker distinct from package integrity or manifest syntax. A deployment-owned resource is never inserted into V3 package-owned inventory or fingerprint solely to make admission pass.

Existing same-root and split-root deployment constraints remain effective. No server-empty bootstrap, automatic server configuration, or generic policy framework is adopted by this amendment.

## Coordination and proof

docs/28 must exclude exact protected bytes from revised package inventory and reject prohibited mutation; docs/31 must forbid protected-file recovery mutation and truthfully report unresolved routing blockers. Confirm provider-specific routing, installer/update admission, historical artifact disposition, and exact SHA-256 preservation of PUBLIC_ROOT/.htaccess across failures. NO TEST EXECUTION CLAIMED.
