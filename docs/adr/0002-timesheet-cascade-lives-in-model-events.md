# The timesheet-change cascade lives in model events, not a recording service

When a timesheet changes, derived state is recomputed: slot association, `Pod`
notification, notes, and award/years rebuilds. This cascade is concentrated in
`Timesheet::boot()`'s saving/saved/deleted callbacks, and the recompute logic itself
lives in the reusable `AwardManagement` / `YearsManagement` modules — triggered the
same way from `PersonSlot`, `TrainerStatus`, `PersonTeam`, and `PersonAward` events.

We deliberately do **not** extract a `TimesheetRecording` service that callers would
invoke instead of `save()`. The Eloquent model events already guarantee the cascade
runs on every mutation path (controllers, bulk sign-in/out, jobs, seeders) and cannot
be bypassed. A service would either be a cosmetic wrapper around `boot()` or require
rerouting every mutation site — at the cost of that can't-bypass guarantee. The
recompute is already deep (`AwardManagement`/`YearsManagement`) and already local
(`boot()`).

Future reviews will see the cascade spread across `boot()` + two Management libs and
suggest a recording module; this is the reason the current shape is preferred.
