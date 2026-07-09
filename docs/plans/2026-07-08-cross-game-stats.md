# Cross-game summary statistics (holiday tournament leaderboard + awards)

**Date:** 2026-07-08
**Owner:** Honza (jan.tobolik@sensio.cz)
**Status:** Draft
**Planner:** BaC-planner (voiced by Kochanski)
**model_gate:** {model: claude-opus-4-8, verdict: T1-ok, operator_confirmed_at: null, recorded_by: BaC-planner}

## Goal
Add a "Souhrnná statistika" view that aggregates a user-selected set of games into a per-player leaderboard plus cross-game roast/positive awards, so a holiday tournament can be evaluated across all its games at once.

## Success criteria
- New **"Souhrnná statistika"** button on the games-list screen opens a view with a **checkbox game picker** (all checked by default, "Vybrat vše / Zrušit vše").
- Selecting/deselecting games recomputes a **leaderboard** (row per player) with: games played, **wins (1st place)** + win %, **average rank**, **bid success %** (made rounds / all rounds), total points, avg points per game.
- Players are grouped **by exact trimmed name** across the selected games ("Honza" != "Hony").
- A **cross-game awards** block shows fun titles (Věčný šampion, Věčná vrána, Nejpřesnější hlásič, Kamikaze, Stálice, …), each assigned to one player.
- All computation is **read-only** on the server; a data-integrity check confirms no writes. Only the caller's own, non-deleted games/players/results (`valid_to IS NULL`) are read.
- Numbers verified against a hand-computed fixture in dev; shipped only after HITL; verified live.

## Stakeholders
- Honza (operator; HITL deploy approval).
- Juli + the holiday group (end users reading the leaderboard).

## Constraints
- Vanilla PHP 8 + MariaDB (PDO), inline JS/CSS, **no build step**. (project CLAUDE.md)
- Deploy = merge to `master` = live (Actions/lftp). **No staging.** PRs/merge via **HITL**.
- **Read-only feature** - no INSERT/UPDATE/DELETE. Soft-delete model respected on reads (`valid_to IS NULL`); never touch prod data.
- Ownership: only aggregate games where `games.user_id` = the session user (like `list`/`get`).
- Production DB has **no `total_rounds`** column - derive round counts from data / `getGameTotalRounds`. (project CLAUDE.md)
- UI Czech, no em-dash; footer version bump + CHANGELOG on release.
- Reuse existing award engine + ranking (`getAwardPool`/`assignAwards`/`getRankedPlayers`) rather than reinventing.

## Scope
**In:**
- Read-only aggregation endpoint (`api/games.php action=overall`) taking `game_ids[]`, returning per-player cross-game aggregates + a computed leaderboard + awards payload.
- Games-list "Souhrnná statistika" button + new view (checkbox picker, leaderboard table, awards grid) reusing stats-modal styling.
- Cross-game award catalog (new titles) computed from the aggregate fields.
- Dev verification + HITL release.

**Out (rationale):**
- Manual alias merging of differently-spelled names - user chose exact-name matching for MVP.
- Head-to-head matrix, trend charts, named "series/tournament" entity - follow-ups.
- TV-fullscreen styling of this view - follow-up (can reuse the `data-tv` pattern later).
- Any write/persistence of aggregates - out by design (read-only).

## Plan

### Step 1: Read-only aggregation endpoint `games.php action=overall`
- **Produces:** POST action accepting `{game_ids: number[]}`; for each owned, non-deleted game in the list, load its players (`game_players` valid_to IS NULL) and results (`round_results` valid_to IS NULL joined to rounds valid_to IS NULL), compute per-game ranking from `total_score` (ties via the `getRankedPlayers` rule), then **group players by `trim(name)`** and accumulate cross-game fields: gamesPlayed, wins (rank==1 count), rankSum (for avg rank), roundsPlayed, roundsMade (bid==tricks_won), totalBid, totalWon, zeroBids, overbidSum (max(0, bid-tricks) summed), pointsTotal, bestRoundScore, worstRoundScore. Returns `{players:[...aggregates...], games:[{id,name}], meta}`.
- **Proves success:** `php -l` clean; dev test - call with 2-3 known games and check aggregates match a hand computation; unknown/other-user game id is silently ignored (not leaked); empty selection -> empty leaderboard, HTTP 200.
- **Touches:** `api/games.php` (+ PDO). Reads games/game_players/rounds/round_results. **No writes.**
- **Constraints:** PDO prepared statements; parameterized `IN (...)` for game_ids; ownership filter `user_id = :uid` on games; `valid_to IS NULL` everywhere; no `total_rounds` dependency.
- **HITL gate:** No.
- **Executor:** BaC-go (builder).
- **Depends on:** prior-art (schema, ranking rule) - done.
- **Parallel-safe with:** Step 2 shell against the agreed JSON contract.
- **Rollback:** revert file (read-only, no data risk).

### Step 2: Cross-game award catalog + leaderboard computation
- **Produces:** A cross-game award pool (server-side or client-side over the Step-1 aggregates) with new titles mapped to aggregate metrics: Věčný šampion (most wins), Věčná vrána (most last-place finishes / lowest avg rank), Nejpřesnější hlásič (highest bid success %), Cihla (lowest success %), Kamikaze (highest overbidSum), Stálice (most gamesPlayed), Sběratel (highest pointsTotal), Sklízeč (most totalWon). One unique title per player via the existing greedy `assignAwards` approach adapted to these metrics. Plus a leaderboard sorter (default: wins desc, then avg rank asc).
- **Proves success:** dev unit check (node `new Function`) - given a fixed aggregate array, awards are unique and land on the expected players; leaderboard order matches expected.
- **Touches:** `offline-recorder.html` (JS) and/or `games.php` (whichever computes awards - prefer client-side reuse of `assignAwards` pattern for consistency).
- **Constraints:** reuse `assignAwards`/greedy-unique logic; handle ties with the "(děleno)" marker like per-game awards; deterministic.
- **HITL gate:** No.
- **Executor:** BaC-go.
- **Depends on:** Step 1 (aggregate fields).
- **Rollback:** revert.

### Step 3: UI - games-list button + summary view
- **Produces:** A "Souhrnná statistika" button on the games-list screen; a new view/modal with (a) a checkbox game picker (default all checked + Vybrat vše / Zrušit vše), (b) a leaderboard table (reusing `.stats-table` styling), (c) an awards grid (reusing `.stats-grid`/`.stat-card`), recomputing on selection change by calling `action=overall`.
- **Proves success:** dev Playwright - open view, all games checked, leaderboard + awards render; uncheck some games -> recompute; empty selection -> friendly empty state; visuals match the stats-modal look; no console/HTTP errors.
- **Touches:** `offline-recorder.html` (games-list actions, new view markup + render fns, api call).
- **Constraints:** reuse existing stats styling; Czech labels; no em-dash; don't regress per-game stats modal.
- **HITL gate:** No.
- **Executor:** BaC-go.
- **Depends on:** Steps 1-2.
- **Rollback:** revert.

### Step 4: Verification (dev docker)
- **Produces:** Green results: JS `vm.Script` on `<script>` blocks; `php -l api/games.php` via `docker exec` (PowerShell); Playwright E2E (create 2-3 games with known results -> open summary -> verify wins/success%/awards against hand calc; toggle games; ownership - another user's game id ignored). Confirm **zero writes** (compare row counts before/after, or code-review the endpoint for absence of write SQL).
- **Proves success:** all checks pass; aggregates correct; no data mutated.
- **Touches:** dev docker (localhost:8088, demo/demo1234), Playwright (C:\tmp\stychy-pw).
- **Constraints:** dev only; never touch prod DB.
- **HITL gate:** No.
- **Executor:** BaC-go.
- **Depends on:** Steps 1-3.
- **Rollback:** n/a.

### Step 5: Release + deploy [HITL]
- **Produces:** footer version bump + CHANGELOG entry; feature branch -> (HITL) merge to `master` -> Actions deploy -> live verify via `curl` (new version + endpoint) + prod smoke test (open summary over real holiday games, read-only).
- **Proves success:** live site serves the feature; smoke test shows a correct leaderboard; no errors.
- **Touches:** `offline-recorder.html` (footer), `CHANGELOG.md`, git, Actions.
- **Constraints:** **[HITL]** show branch + change and wait for explicit OK before merge. Deploy excludes `config/`, never `--delete`. Read-only endpoint - no prod-data risk, but still HITL for the ship.
- **HITL gate:** **YES** - confirm before merge/deploy.
- **Executor:** BaC-go + human.
- **Depends on:** Step 4.
- **Rollback:** `git revert` merge + redeploy previous master.

## Critical path
Step 1 -> Step 2 -> Step 3 -> Step 4 -> Step 5. Step 2 can begin once Step 1's aggregate-field contract is fixed; Step 3 needs both.

## Definition of done
On stychy.cz: the games list has "Souhrnná statistika"; opening it lets the user check the holiday's games and shows a correct per-player leaderboard (wins, success %, avg rank, points) plus cross-game awards; players are grouped by exact name; nothing is written to the DB; version bumped + CHANGELOG updated. Retro: Honza reviews on prod against the actual holiday games.

## Replanning log
- Miro-replanning (Phase 6.5) skipped: 5 steps, but the feature is **read-only** (the usual production risk - data mutation - is absent by design) and reuses existing, verified award/ranking logic. Prior-art search located every reusable piece. Available on request before BaC-go if an adversarial pass is wanted.
