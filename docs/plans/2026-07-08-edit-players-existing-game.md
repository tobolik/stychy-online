# Let the user reorder / rename players of an existing game (0-rounds only)

**Date:** 2026-07-08
**Owner:** Honza (jan.tobolik@sensio.cz)
**Status:** Draft
**Planner:** BaC-planner (voiced by Kochanski)
**model_gate:** {model: claude-opus-4-8, verdict: T1-ok, operator_confirmed_at: null, recorded_by: BaC-planner}

## Goal
Add an "Upravit hru" action to an existing game's detail that reopens the (reused) new-game dialog pre-filled, so the user can fix player **order** and **names** they got wrong at setup - available only while the game has **0 played rounds**.

## Success criteria
- A game with **0 rounds** shows an **"Upravit hru"** button in the detail action bar; a game with >=1 round does not.
- The button opens the existing create-game modal **pre-filled** (game name, player count, decks, max cards, player names in current order), with **player count + max cards + decks disabled** (read-only) and the primary button relabelled to save edits.
- Reordering (arrows) and/or renaming, then saving, **updates the same game** (no new game created); reopening the game shows the new column order and dealer rotation.
- Server rejects the setup-edit when the game has any round (HTTP 400) and when the caller is not the owner - data integrity preserved.
- No hard-delete anywhere; `game_players` updated **in place**; PDO prepared statements throughout.
- Shipped to stychy.cz only after explicit HITL approval; verified live.

## Stakeholders
- Honza (operator, decides "done", does the HITL deploy approval).
- Juli (co-author / end user of the recorder) - benefits from the fix.

## Constraints
- Vanilla PHP 8 + MariaDB (PDO), inline JS/CSS, **no build step**. (project CLAUDE.md)
- Deploy = merge to `master` = live via GitHub Actions (lftp SFTP). **No staging.** (project CLAUDE.md)
- **Soft-delete only, never hard-delete** (games=`valid_to`, players=`valid_to`). (memory: nikdy-hard-delete-vzdy-soft)
- Never scramble/lose production data ("jak mi zamotáš data na produkci, tak tě zaškrtím").
- `game_players` has `UNIQUE(game_id, position)` that ignores `valid_to` -> cannot soft-delete + reinsert same positions; must update in place with a two-phase position write.
- `rounds.dealer_position` stores a **position int** (not player_id) -> reordering a game **with** rounds would corrupt historical dealer references. Eliminated by the 0-rounds guard.
- `round_results.player_id` -> `game_players.id` (stable); with 0 rounds there are no results anyway.
- PRs / merges to master via **HITL** (global + project CLAUDE.md).
- UI Czech, no em-dash; footer version bump + CHANGELOG on release.

## Scope
**In:**
- Reuse the new-game modal in an "edit" mode (pre-fill + lock count/cards/decks).
- New "Upravit hru" button in game detail, shown only at 0 rounds.
- New `games.php` action to persist game-name + player names/order for a 0-round game (ownership + 0-rounds guarded, transactional, in-place position update).
- Dev verification + HITL release.

**Out (rationale):**
- Add / remove players (changes round math + unique-position edge cases) - follow-up.
- Changing `max_cards` / decks / player count on an existing game - locked in MVP.
- Reorder or rename for a game **with** rounds (>0) - would corrupt `dealer_position`; user chose 0-rounds-only. Mid-game the pencil keeps renaming only the game.
- Remapping historical `dealer_position` - not needed under the 0-rounds guard.

## Plan

### Step 1: Server action `update_setup` in api/games.php
- **Produces:** A new POST action that, for a game **owned by the caller** with **0 rounds** (`SELECT COUNT(*) FROM rounds WHERE game_id=? AND valid_to IS NULL` = 0), updates `games.name` and updates each `game_players` row's `name` + `position` (same count, matched by ordinal slot), returning success/updated game.
- **Proves success:** `php -l` clean; dev test via app `api()`/curl: (a) reorder+rename on a 0-round game persists and `get` returns new order; (b) game with a round -> 400 "nelze upravit hráče u rozehrané hry"; (c) other user's game -> rejected.
- **Touches:** `api/games.php` (+ PDO). Reads `rounds` count (valid_to IS NULL). Writes `games`, `game_players`.
- **Constraints:** No hard-delete. Avoid `UNIQUE(game_id, position)` collision with a **two-phase update inside a transaction**: first offset all this game's active players to a non-colliding range (e.g. `position = position + 100`), then set final `position` + `name` per row; commit. Require incoming player count == existing active count (no add/remove). Reject empty names. Ownership check like other actions.
- **HITL gate:** No (code only).
- **Executor:** BaC-go (builder).
- **Depends on:** prior-art (schema) - done.
- **Parallel-safe with:** Step 2 UI shell (but contract must match).
- **Rollback:** revert file edit; runtime errors roll back the transaction (no partial write).

### Step 2: Frontend edit mode for the new-game modal
- **Produces:** `openEditGameModal(gameId)` that opens the existing create modal pre-filled from `currentGame` (name, count, decks, max cards, player names in position order), sets an `isEditGameMode` flag that **disables** the count buttons, decks, and max-cards controls and relabels the primary button to "Uložit úpravy" wired to `saveGameEdit()` (calls `games.php` `update_setup`, then closes + reopens the game). Reuses `movePlayer` arrows as-is.
- **Proves success:** dev Playwright - open on a 0-round game, reorder + rename a player, save, reopen game; columns + "Příští kolo" dealer reflect the new order; count/cards controls visibly disabled; the normal create flow still works unchanged.
- **Touches:** `offline-recorder.html` (modal open/prefill, mode flag, save handler, primary-button label swap).
- **Constraints:** Reuse existing modal + `movePlayer` (no duplicate UI); must not regress `createGame()`; keep name validation identical; lock count/cards/decks in edit mode.
- **HITL gate:** No.
- **Executor:** BaC-go.
- **Depends on:** Step 1 (contract).
- **Rollback:** revert file edit.

### Step 3: "Upravit hru" button in game detail (0-rounds gate)
- **Produces:** A `btn-secondary` "Upravit hru" (icon `fa-user-pen` or similar) in the detail action bar next to Statistiky/Kopírovat/Smazat/Zpět, rendered **only when the game has 0 rounds** (`currentGame.rounds.length === 0`), calling `openEditGameModal(currentGame.game.id)`.
- **Proves success:** button present on a fresh 0-round game, absent after the first round is created; click opens the pre-filled edit modal.
- **Touches:** `offline-recorder.html` (detail header actions render).
- **Constraints:** visibility strictly tied to 0 rounds; consistent styling; Czech label.
- **HITL gate:** No.
- **Executor:** BaC-go.
- **Depends on:** Step 2.
- **Parallel-safe with:** none (needs Step 2).
- **Rollback:** revert file edit.

### Step 4: Verification (dev docker)
- **Produces:** Green results for: JS `vm.Script` on the `<script>` blocks; `php -l api/games.php` via `docker exec`; Playwright E2E covering the happy path (reorder+rename persists across reopen), the guard (button hidden with rounds; forced `update_setup` on a game-with-round -> 400), and ownership rejection. A data-integrity check that no `game_players` rows were hard-deleted and positions are a clean `0..N-1` set.
- **Proves success:** all checks pass; DB shows updated names/positions, no orphaned/duplicate positions, no lost rows.
- **Touches:** dev docker (stychy-web-1 / stychy-db-1, localhost:8088), Playwright (C:\tmp\stychy-pw).
- **Constraints:** dev only; never touch prod DB.
- **HITL gate:** No.
- **Executor:** BaC-go.
- **Depends on:** Steps 1-3.
- **Rollback:** n/a (read-mostly verification).

### Step 5: Release + deploy [HITL]
- **Produces:** Footer version bump + CHANGELOG entry; feature branch -> (HITL) merge to `master` -> GitHub Actions deploy -> live verification via `curl` (new version + button/endpoint present) and a prod smoke test (open a 0-round game -> "Upravit hru" shows; edit a throwaway test game).
- **Proves success:** live stychy.cz serves the new version; smoke test confirms edit works and rounds-guard holds on prod.
- **Touches:** `offline-recorder.html` (footer), `CHANGELOG.md`, git, GitHub Actions.
- **Constraints:** **[HITL]** show branch name + intended change and wait for explicit OK before merging to master (new feature; even with standing merge authority this crosses into a new capability). Deploy excludes `config/`, never `--delete`. Never run destructive ops on prod data.
- **HITL gate:** **YES** - confirm before merge/deploy.
- **Executor:** BaC-go + human.
- **Depends on:** Step 4.
- **Rollback:** `git revert` the merge commit + redeploy previous master.

## Critical path
Step 1 -> Step 2 -> Step 3 -> Step 4 -> Step 5. Essentially sequential; the modal shell of Step 2 can begin against the agreed Step 1 contract but must not merge before Step 1 lands.

## Definition of done
On stychy.cz: opening a game with **0 rounds** shows "Upravit hru"; using it to reorder and/or rename persists to the same game and is visible after reopening (columns + dealer order); a game with any round does not show it and the server refuses setup edits; no data lost/hard-deleted; footer version bumped and CHANGELOG updated. Retro: Honza reviews on prod right after deploy.

## Replanning log
- Miro-replanning (Phase 6.5) **skipped**: exactly 5 steps and the only production-integrity risk (reorder corrupting `dealer_position`) is fully eliminated by the 0-rounds guard confirmed with the operator; prior-art search mapped the data model precisely. Available on request if the operator wants an adversarial pass before BaC-go.
