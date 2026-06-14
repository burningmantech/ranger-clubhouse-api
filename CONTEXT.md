# Rangers Clubhouse

Domain language for the Black Rock Rangers Clubhouse — personnel, scheduling, timesheets, and credentialing for a volunteer organization.

## Authorization

**Role**:
A capability flag held by a Person, resolved (`Person::retrieveRoles()`) from direct grants plus roles carried by held positions and team memberships. Some roles are *massaged* — granted indirectly under settings (e.g. `TRAINER_SEASONAL`→`TRAINER`, on-playa management).
_Avoid_: permission, ability

**ADMIN**:
The general super-user role (`Role::ADMIN` = 1). Broad write access, but not godlike — it does **not** imply TECH_NINJA.
_Avoid_: superuser, root

**TECH_NINJA**:
The godlike maintenance role (`Role::TECH_NINJA` = 1000): raw database access and dangerous functions. Independent of ADMIN and strictly more privileged for reserved operations. Two intentional patterns: it may *stand in for* ADMIN where a check admits `[ADMIN, TECH_NINJA]`, but dangerous operations (credential settings, role/oauth/telescope management, granting TN-protected positions or teams) admit **TECH_NINJA only** — ADMIN is insufficient. Collapsing a TN-only check down to ADMIN is privilege escalation.
_Avoid_: admin
