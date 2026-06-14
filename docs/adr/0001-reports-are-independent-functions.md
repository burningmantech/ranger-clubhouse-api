# Reports are independent functions, not a polymorphic interface

The ~63 classes in `app/Lib/Reports/` each expose a static `execute()`, but with
genuinely different signatures (`execute(int $year)`, `execute(Team $team, bool ...)`,
`execute(Carbon $shiftStart, int $shiftDuration)`,
`execute(array $positionIds, int $startYear, int $endYear, bool, bool)`, …). Each is
invoked directly, with typed arguments, from the controller that owns its domain
(`TrainingController` → training reports, `AccessDocumentController` → ticket reports,
etc.); there is no central dispatcher.

We deliberately do **not** give them a shared `Report` interface or a registry. A
uniform `execute(array $params): array` would erase the explicit, type-checked
signatures they have today, and a registry would add indirection over call sites that
don't need it — nothing is dispatched polymorphically, so nothing varies across a
common interface. (Contrast `app/Lib/BulkUpload/Handlers`, which *do* share a real
interface because `BulkUploader` selects them at runtime by action with one signature.)

Future architecture reviews will see the sibling-file sprawl and suggest consolidating
it; this is the reason not to.
