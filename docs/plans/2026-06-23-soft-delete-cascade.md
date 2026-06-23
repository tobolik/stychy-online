# Finish + harden soft-delete (audit trail, no-cascade) safely against production — phased

**Date:** 2026-06-23
**Owner:** Honza
**Status:** Draft v2 (post Miro-replanning x2 + Miro-council crossplanning)
**Planner:** BaC-planner (voiced by Kochanski)

## Current state (CORRECTED — council finding, verified in code)
This is NOT greenfield. As of now:
- `games.php` `delete` **already soft-deletes the game** (`UPDATE games SET valid_to=NOW()`, :290-292) and read paths **already filter `valid_to IS NULL` at game level** (:82, :106, :256, :282).
- The 6 FKs are **still `ON DELETE CASCADE`**.
- `rounds.php` `delete` **still hard-deletes** round_results + rounds and renumbers (:387-406).
- **Live bugs today:** (a) deleting a game does NOT stamp its subtree → game_players/rounds/round_results stay `valid_to IS NULL` (only hidden because reads go via the game); (b) `rounds.php` `verifyGameOwnership` (:27) lacks a `valid_to` filter → a soft-deleted game is **still editable** through rounds.php (create/save/update).
So this work = **finish the half-done soft-delete + close the live bugs + remove cascade as defense-in-depth**, not a big new feature.

## Goal
Make soft-delete consistent across the game subtree with an audit stamp, close the two live bugs, and remove `ON DELETE CASCADE` as a safety net — delivered in safe phases, verified in Docker, with backups in place first, and no rollout step that depends on human discipline to avoid a 500.

## Guiding principles (council)
- **Backups before surgery.** A soft-delete with no prod backup is theater. Phase 0.
- **Phase by risk.** Additive column work (safe) is separated from FK DDL (risky).
- **Engineer out the ordering risk** (feature flag / defensive reads), don't rely on "don't merge early" discipline.
- **Right-size the ceremony.** Showcase app with offline card-game records — avoid over-engineering; the 80/20 is backups + subtree propagation + delete-last-round.

## Stakeholders
- Honza (runs prod backup + manual migration; provides prod `SHOW CREATE TABLE` + engine/version; approves PRs)
- Juli (end user; must keep being able to fix a mis-entered round)

## Constraints
- Never touch prod DB directly (I write SQL + runbook; Honza runs it). [[stychy-produkce-db-vypadek]]
- No hard delete anywhere. [[nikdy-hard-delete-vzdy-soft]]
- Deploy upload-only, `--exclude config/`, no `--delete`, does NOT run migrations (manual). [[deploy-nikdy-nemazat-server]]
- Prod DB drifts (002 never applied) → verify actual prod state, don't derive from schema.sql.
- Vanilla PHP 8 + **MariaDB (hosting confirmed MariaDB 11.8.6, NOT MySQL 8)** (PDO), no build step, merge=live, no staging. Feature branch → PR (HITL). Verify via `php -l`, `node --check`, curl vs Docker. Footer + CHANGELOG. No em dash.
  - **MariaDB impact (council/Critic confirmed):** `ALGORITHM=INSTANT` for ADD COLUMN is supported in MariaDB 10.3+ (11.8 OK); FK re-add INPLACE behaves like MariaDB, verify. Multiple-TIMESTAMP per-table needs `explicit_defaults_for_timestamp` awareness. Docker dev should use a matching MariaDB 11.x image (not mysql:8) for a faithful test — currently docker-compose uses mysql:8.0, so add a MariaDB test pass or switch the dev image.

## Decisions (locked / updated by council)
- Audit columns: `valid_to` (TIMESTAMP NULL, matches games) + `valid_to_user_id` (INT UNSIGNED NULL, no FK). `valid_to_user_id` is optional/YAGNI (no reader UI yet) but cheap — keep.
- Scope: game subtree (games, game_players, rounds, round_results). users/user_stats stay on `is_active`. user_stats unchanged on delete (documented).
- **Round delete: keep, but LAST round only** (council "third option" — supersedes the earlier "forbid"). Deleting only `MAX(round_number)` avoids the `UNIQUE(game_id, round_number)` + renumbering conflict entirely, preserves the feature, and stays soft (stamp valid_to on that round + its results, reverse the score). Corrections still go via `update_results`. **Needs Honza/Juli OK** (product call).
- Propagation is application-level (no triggers).

---

## PHASE 0 — Backups + prod reconnaissance [HITL, do first]
- **0.1 Prod backup:** Honza runs `mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 <db> > stychy-YYYYMMDD.sql`. Verify file ends with `-- Dump completed` and size is non-trivial. **Restore-test = import this dump into Docker** (this doubles as Step in Phase-test); do NOT rely on `mysql --force` syntax check or on CREATE DATABASE rights on shared hosting.
- **0.2 Recon paste (read-only):** Honza pastes from prod: `SELECT VERSION();` (MySQL vs MariaDB + version → decides INSTANT/INPLACE + multiple-TIMESTAMP behaviour), `SHOW CREATE TABLE games; game_players; rounds; round_results; user_stats;` (real FK names + existing columns), and `SELECT COUNT(*) FROM games WHERE valid_to IS NOT NULL;` (how many already-soft-deleted games the backfill will touch).
- **Gate:** Phases 1-2 finalize only after 0.1 + 0.2. **Blocks everything.**

## PHASE 1 — Finish soft-delete (SAFE: additive columns + app logic; NO FK changes)
Deliverable: one code PR + one manual ADD-COLUMN migration. No cascade touched. This carries ~80% of the value at ~20% of the risk.

### 1a. Migration 003a (additive only)
- `ALTER ... ADD COLUMN valid_to TIMESTAMP NULL` + `valid_to_user_id INT UNSIGNED NULL` + `valid_to_idx` on game_players, rounds, round_results; `valid_to_user_id` on games. `ALGORITHM=INSTANT` (INPLACE fallback per 0.2 engine).
- **Backfill** already-soft-deleted subtrees: `UPDATE <sub> JOIN games g ... SET sub.valid_to = g.valid_to WHERE g.valid_to IS NOT NULL AND sub.valid_to IS NULL` (valid_to_user_id NULL; documented audit limitation; run as a separate, repeatable last step).
- Idempotency: run statement-by-statement, no `--force`; precede each with a `SELECT` existence check (INFORMATION_SCHEMA) so a half-applied retry is diagnosable, not a panic.

### 1b. Propagation + bug fixes (games.php / rounds.php)
- `games.php` delete: in a transaction, stamp `valid_to=NOW()`+`valid_to_user_id=$userId` on game + game_players/rounds (by game_id) + round_results (round_id IN subquery), each `WHERE valid_to IS NULL`.
- `rounds.php` `verifyGameOwnership` (:27): add `AND g.valid_to IS NULL` — closes the "edit a deleted game" bug.
- `rounds.php` delete: change to **last-round-only soft-delete** (stamp valid_to on `MAX(round_number)` round + its results, reverse the player scores in the same transaction); reject if not the last round, pointing to `update_results`. (Pending Juli OK; fallback = forbid entirely.)

### 1c. Read filters (defensive — must not 500 on a missing column)
- Add `AND valid_to IS NULL` at the verified locations: games.php list rounds_played subquery (:80), get game_players (:115)/rounds (:120)/round_results JOIN (:134-141); rounds.php save_bids/save_results/get/update_results/create result+player+round reads + create MAX(round_number).
- **Ordering safety (council):** gate the subtable filters behind a config constant `SOFT_DELETE_SUBTREE := true/false` (or a column-exists check) so the code is safe to deploy in ANY order vs the migration. Deploy code with flag OFF → run 1a on prod → flip flag ON (trivial PR). No 500 window, no discipline dependency.

### 1d. Frontend
- `offline-recorder.html`: keep delete-round button only for the last round (or adjust per 1b decision); error message points to "opravit výsledky" (update_results) first.

## PHASE 2 — Remove ON DELETE CASCADE (RISKY DDL, isolated, separate run)
Defense-in-depth only — after Phase 1 no code hard-deletes, so this is a safety net, not a functional need. Ship separately.
- Migration 003b: per FK, **first** run a read-only orphan anti-join SELECT (prove 0 orphans), **then** `DROP FOREIGN KEY <real name from 0.2>` + `ADD CONSTRAINT <name> FOREIGN KEY ... REFERENCES ...` (default RESTRICT). Statement-by-statement, no --force; `SHOW CREATE TABLE` cascade-free is the verification gate.
- Consider a brief read-only / maintenance window: between DROP and ADD a table has no FK; on a live single-user app the window is tiny but note it.
- Reverse SQL (re-add cascade) included as a commented block — last-resort only; real rollback is `git revert` (nullable columns + RESTRICT are backward-compatible with old code).

## Docker verification (both phases)
Fresh schema; 001-only drift schema; **prod dump import** (= the Phase 0.1 restore-test) with row-count + per-game score-sum equality before/after; soft-delete a game → subtree stamped, physical counts unchanged; delete last round → stamped + score reversed, non-last rejected; live game unaffected (create/bid/results/get, rounds_played correct); Phase 2: hard DELETE on each table rejected; orphan-checks = 0; reverse SQL clean; `php -l` + `node --check`.

## Production rollout (per phase) [HITL]
- **Phase 1:** backup (0.1) done → deploy code (flag OFF, safe in any order) → Honza runs 003a + backfill on prod → confirm `SHOW COLUMNS` → flip flag ON (small PR) → post-deploy smoke (login, open real game, delete last round, deleted game not editable).
- **Phase 2 (later):** backup → orphan-checks = 0 on prod → run 003b → `SHOW CREATE TABLE` cascade-free → hard-DELETE rejected. No code deploy needed (DDL only).
- Claude never runs prod SQL. Engine/version from 0.2 dictates INSTANT vs INPLACE.

## Definition of done
- Phase 1: prod backed up + restore-tested in Docker; subtree soft-delete consistent; deleted game not editable; delete-last-round works + reverses score; v1.6.0 live; API JSON OK.
- Phase 2: cascade removed on prod; hard DELETE rejected; orphan-free.
- CHANGELOG entries; footer bumped.

## Replanning log
**Round 1 (5 reviewers)** + **Round 2 (3 reviewers)**: applied scope/read-filter/runbook hardening; converged.
**Miro-council crossplanning (4 members: Advocate/Critic/Wildcard/Pragmatist, 2026-06-23):** REVISED the plan substantially:
- Corrected the current-state model (soft-delete already live; only rounds.php hard-deletes; 2 live bugs) — the convergent reviewers had validated a wrong premise.
- Phased the work (1 = safe additive, 2 = risky FK) — Pragmatist.
- Replaced ordering-discipline with a feature flag / defensive reads — Wildcard + Pragmatist.
- Added Phase 0 backups + restore-test in Docker + mysqldump flags — Wildcard + Critic (GitLab 2017 precedent).
- Step 0 now captures engine/version (MySQL vs MariaDB) + already-deleted count, not just FK names — Critic.
- Round-delete changed from "forbid" to "delete LAST round only" (preserves feature, no UNIQUE conflict) — Wildcard's third option; pending Juli OK.
- Orphan anti-join checks before each re-add FK — Pragmatist.
- Noted valid_to_user_id is YAGNI (kept, cheap) and backfill audit limitation — Wildcard/Critic.
**Advocate dissent:** judged the (original) plan high-confidence-safe on the grounds that the migration is purely additive and no code path deletes data — valid for data-loss, but rested on the wrong starting-state premise the others corrected.
