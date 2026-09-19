# HR performance implementation plan
> Use superpowers:executing-plans inline. No commits or deployment are authorized.

**Goal:** Implement all recommendations in docs/audits/2026-09-19/performance-review.md, preserving behavior.
**Architecture:** Explicit CLI schema preparation; request-scoped shared datasets; scoped browser dependencies; optional paginated APIs; streaming imports.
**Tech Stack:** PHP 8, mysqli/MariaDB, JavaScript, Bootstrap, DataTables.
**Spec:** docs/audits/2026-09-19/performance-review.md

## Global constraints
Preserve role/HR scope, date/calculation/history, UI text, exports, bulk selection and legacy API responses. No production writes, migrations, commits or pushes. Base f17e889; branch codex/performance-review-20260919.

## Review focus
- Bulk failure rolls back all changes without implicit commits.
- Partial leave, cancellation stages, overnight and historical shift precedence.
- Stale asynchronous responses cannot replace new report/selection state.
- Paged search/count/order enforce HR scope and preserve legacy callers.
- CSV duplicate merging/counters and atomicity across chunk boundaries.

## Task 1: Schema preparation (A01-03,A13)
Files: includes/*_helpers.php, api/attendance_api.php, scripts/prepare_schema.php, tests/runtime_schema_test.php.
Interface: existing Ensure helpers run only in explicit CLI migration mode; new employee updates retain targeted bootstrap.
- [x] RED no runtime DDL/backfill/seed writes.
- [x] Implement migration mode, correct enum comparison, one-time schema/seed/backfill under migration lock.
- [x] GREEN no-write tests; migration idempotence and rollback fixture where available.

## Task 2: Query/data algorithms (A05-07,A12)
Files: attendance/leave APIs, warning helpers, approval badges, tests.
Interface: range data loader feeds existing evaluators; warning request-local cache; grouped swaps and joined work days.
- [x] RED query-budget/equivalence tests.
- [x] Implement batching, reusable statements, append rows, range loaders and context reuse.
- [x] GREEN month/range equivalence and authorization/status cases.

## Task 3: Browser modules/request ordering (A04,A09,A11,A15)
Files: includes/footer.php, includes/page_assets.php, assets/js/*, index.php, tests.
Interface: explicit page assets, unambiguous shared helpers, latest-request controller and login script.
- [x] RED collision/race/assets; repair three existing harness failures.
- [x] Implement dependencies, request ordering and remove verified dead code.
- [x] GREEN all JS tests; projection/null behavior and syntax.

## Task 4: Sessions/pagination (A08,A10)
Files: API entrypoints, shared session/list helpers, employee/approval/history JS.
Interface: release session after mandatory updates; opt-in paged response preserves legacy arrays.
- [x] RED paging/count/scope/locking cases.
- [x] Implement scoped paging and release locks on read/long flows; wire list pages.
- [x] GREEN legacy and paged workflows, bulk/export semantics.

## Task 5: CSV streaming (A14)
Files: includes/attendance_helpers.php, api/attendance_api.php, tests.
Interface: CSV iterator and bounded batch writes within one transaction, same merge rules/counts.
- [x] RED cross-chunk duplicates and failure.
- [x] Implement bounded streaming parser/merge/write.
- [x] GREEN atomicity/counters/memory fixtures.

## Task 6: Verification/handoff
- [x] PHP/JS full suite, syntax, whitespace, after-probes.
- [x] One final fresh reviewer, substantive fixes and retest.
- [x] Document migration, measurements and limitations.

## Execution ledger
Ruling: Use a dedicated branch in the supplied workspace and keep the ledger with uncommitted files. No commits or cleanup because neither was requested.
Pre-flight: Tasks 2/4/5 consume Task 1 no-runtime-DDL; Task 4 consumes Task 3 dependencies/request coordinator.

Task progress: Schema guards/CLI implemented; runtime no-SQL RED->GREEN. Range loader uses 9 SELECTs for one or twelve months; grouped badges 3. Grouped swaps and request-scoped employee/month warning cache RED->GREEN. Page assets, shared display helpers, dedicated login and stale-report guards implemented; original three JS harness failures repaired. Session release tests RED->GREEN. Scoped optional paginated APIs and browser adapter implemented; comprehensive integration pending. Streaming CSV and bulk adjustment statement reuse implemented; fixture testing pending.
Ruling: Keep legacy unpaged history limits (50 time requests / 100 training) while paged mode exposes the complete authorized history. Legacy callers retain their response contract.

## Completion evidence
All six tasks completed locally. Regression: 76/76 files, zero skipped on the isolated MariaDB fixture. Syntax: 134 PHP, 67 JS/CJS files. Runtime query probe: 9 dataset SELECTs for one or twelve months (excludes bootstrap). CSV parser: 100,000 identical rows, 138 MiB materialized versus 2 MiB streamed.

Final reviewer: three P2 findings corrected with targeted RED/GREEN verification (report DataTables dependency, training status sorting, token/Thai-date search). Removed unused DataTables initializers after reference search; retained adapters for legacy contracts. Timeline now receives existing workday/holiday context; API equivalence and query-budget tests pass. Full browser checks and limitations recorded in docs/audits/2026-09-19/implementation-results.md and implementation-verification.json.

No production migration, deployment, commit or push. Production latency/EXPLAIN and real authenticated write-flow acceptance remain release-environment verification, not a local completion claim. Runtime schema readiness requires scripts/prepare_schema.php before serving this release.
