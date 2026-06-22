# Reduce perceived load latency on stychy.cz (non-blocking Font Awesome + remove server-side N+1)

**Date:** 2026-06-22
**Owner:** Honza
**Status:** Draft
**Planner:** BaC-planner (voiced by Kochanski)

## Goal
Cut the intermittent "sometimes slow to load" feeling by removing the render-blocking Font Awesome CDN dependency (the main win for perceived latency) and, as a code-quality cleanup, collapsing the N+1 query in the game-detail endpoint, without introducing a build step.

> **Framing note (replanning finding #4):** the FA non-blocking fix is the real perceived-latency win. The N+1 collapse is a correctness/cleanliness cleanup — at this data scale (few games × ~14 rounds) it saves only ~5-50 ms and is NOT the headline perf gain. Treat it as good practice, not a critical optimization.

## Success criteria
- Font Awesome no longer blocks first paint on any of the 4 pages (the `<link>` uses a non-blocking pattern; icons still render correctly).
- `api/games.php?action=get` issues a constant ~3 queries regardless of round count (was 3 + N, N≈14).
- Response shape of `get` is byte-identical for the frontend (no UI regression).
- Deploy stays green; production API still returns JSON (config untouched); icons visible on production.
- Version bumped to v1.5.9 (footer index + recorder) + CHANGELOG entry.

## Stakeholders
- Honza (owner, decides done, performs DB-touching ops if needed)
- Juli (co-author, end user of recorder)

## Constraints
- No build step (vanilla HTML + inline CSS/JS, PHP 8 + PDO/MySQL). Source: CLAUDE.md.
- Deploy = merge to `master` → live immediately, no staging. PRs always via HITL. Source: CLAUDE.md + global `~/.claude/CLAUDE.md`.
- Deploy must stay upload-only; never `--delete`/remote rm. Source: memory `deploy-nikdy-nemazat-server`.
- Font Awesome `<link>` must keep its SRI `integrity` + `crossorigin` (added v1.5.7).
- No test framework: verify via `php -l`, `node --check`/`vm.Script` for JS, real `curl` timing from production. Source: CLAUDE.md.
- Czech UI + commit messages, short dash only (no em dash). Source: CLAUDE.md.

## Scope
**In:**
- FA render-blocking fix on all 4 pages (index, login, register, offline-recorder) via async load.
- Collapse N+1 in `api/games.php` `get` into a single grouped query.
- Verify + release v1.5.9.

**Out:**
- Self-hosting / subsetting the 51 used icons (deferred; needs external subset tooling + binary asset in repo; decided async-CDN is enough for now).
- Shared-host PHP/DB cold-start (not fixable from code).
- Touching other endpoints' query patterns (list/get only; list is already single-query).

## Plan

### Step 1: Non-blocking Font Awesome on all 4 pages
- **Produces:** FA loaded without blocking first paint on index.html, login.html, register.html, offline-recorder.html.
- **Proves success:** each page's `<head>` uses the preload→stylesheet swap pattern with a `<noscript>` fallback; SRI + crossorigin retained; icons still render (visual check on production after deploy).
- **Touches:** `<head>` line ~7 of the 4 HTML files only.
- **Constraints:** keep `integrity="sha384-iw3OoTEr…"` + `crossorigin`; add `<noscript>` fallback so no-JS clients still get icons; no build step.
- **Pattern:**
  ```html
  <link rel="preload" as="style"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0"
        crossorigin="anonymous" referrerpolicy="no-referrer"
        onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="…all.min.css" integrity="…" crossorigin="anonymous"></noscript>
  ```
- **HITL gate:** no (reversible edit). Deploy itself is gated in Step 3.
- **Executor:** BaC-builder (Claude, direct edits).
- **Depends on:** nothing.
- **Parallel-safe with:** Step 2 (different files).
- **Rollback:** revert the 4 `<link>` lines to the plain stylesheet form.
- **Known risk:** minor FOUC (icons appear a beat after text). Acceptable for a showcase; layout does not depend on icon glyph size.

### Step 2: Collapse N+1 in `api/games.php` `get`
- **Produces:** all round results for a game fetched in ONE query, then grouped in PHP by round id; total ~3 queries per game-open.
- **Proves success:** `php -l` clean; response JSON shape identical (`rounds[i].results[]` carries `rr.*` + `player_name` + `position`); local docker test if daemon available, otherwise close code review + production smoke test.
- **Touches:** `api/games.php` `case 'get'` (the `foreach ($rounds as &$round)` results loop, ~lines 124-136).
- **Constraints:** response shape must stay identical (frontend reads `round.results`); ownership check unchanged; PDO prepared statement; ORDER BY round_number then position preserved.
- **Approach:** one query `SELECT rr.*, gp.name AS player_name, gp.position FROM round_results rr JOIN game_players gp ON gp.id = rr.player_id JOIN rounds r ON r.id = rr.round_id WHERE r.game_id = ? ORDER BY r.round_number, gp.position`. Group rows by `rr['round_id']` (already present in `rr.*` — no helper alias, nothing to strip).
- **DEFENSIVE GROUPING (Critical finding #1):** the INNER JOIN returns only rows that have results, so a round with zero result rows would vanish. The original loop guarantees `results=[]` for every round. Therefore: first initialize `$round['results'] = []` for every round (keep refs in a `$byId` map), THEN fill from the grouped query. Never derive the rounds list from the join output. (In practice `rounds.php` create seeds empty `round_results` per player, so zero-result rounds shouldn't occur — but build it defensively regardless.)
  ```php
  $byId = [];
  foreach ($rounds as &$r) { $r['results'] = []; $byId[$r['id']] = &$r; }
  unset($r);
  // ...single query...
  while ($row = $stmt->fetch()) {
      if (isset($byId[$row['round_id']])) $byId[$row['round_id']]['results'][] = $row;
  }
  ```
- **HITL gate:** no (reversible edit). Deploy gated in Step 3.
- **Executor:** BaC-builder.
- **Depends on:** nothing.
- **Parallel-safe with:** Step 1.
- **Rollback:** revert `api/games.php`.

### Step 3: Verify + release v1.5.9 [HITL]
- **Produces:** v1.5.9 live on production with both optimizations.
- **Proves success:** `php -l api/games.php` clean; footer shows v1.5.9 (index + recorder); CHANGELOG entry; post-deploy `curl` timing on the 4 pages + API health (`auth.php`/`games.php` return JSON = config intact); icons visibly render on production.
- **MANDATORY post-deploy smoke test (finding #3):** `get` is auth-only — it cannot be curl-tested anonymously and there are no prod test creds, so the refactored query is otherwise untested before going live. Before declaring done, **Honza logs in on production and opens an existing multi-round game**, confirming it loads with correct bids/tricks/scores per round (this exercises the regrouped `get` response end-to-end). Attempt a local docker test first if the daemon is up. If a regression shows, roll back immediately (revert merge commit).
- **Touches:** footer version in index.html + offline-recorder.html, CHANGELOG.md, branch `perf-optimizations`, PR to master.
- **Constraints:** PR shown to Honza before creation (HITL); merge = live deploy; verify push reached origin before merge (lesson from PR #14/#15).
- **HITL gate:** YES — present PR (title/description) and wait for OK before creating + merging. Per CLAUDE.md "Pull requesty jen přes HITL".
- **Executor:** Claude (build + verify), Honza (approves PR + merge).
- **Depends on:** Step 1 + Step 2 complete and locally verified.
- **Parallel-safe with:** none (final gate).
- **Rollback:** if production regresses (icons missing / API 500 / deploy red), revert the merge commit on master → redeploy previous green state.

## Critical path
Step 1 ∥ Step 2 (independent files, done together) → Step 3 (verify, HITL release). Critical path length: 2 (parallel build, then release).

## Definition of done
- Deploy green; index + recorder show v1.5.9 on production.
- FA `<link>` on production uses the preload-swap pattern; icons render.
- `api/games.php?get` is constant-query (verified by code) and API returns JSON (config intact).
- CHANGELOG [1.5.9] written.
- Retro: Honza eyeballs perceived load on first visit (cold cache) after deploy; if still sluggish, escalate to follow-up cycle (subset self-host + hosting cold-start review).

## Replanning log (2026-06-23)
**Reviewed by:** Fresh Perspective, Codebase Alignment, Best Practices, CI & Workflow Safety (4 reviewers).
**Findings:** 1 Critical, 3 Important, several Minor. Verdicts: 1 PASS, 3 ISSUES FOUND.
**Changes applied:**
- Critical #1: Step 2 now uses defensive grouping (init `results=[]` per round via `$byId` map, then fill) so rounds with zero results can't vanish from the response.
- Important #2: dropped the redundant `rr_round_id` alias; group by `rr['round_id']` (already in `rr.*`) — no helper-key leak.
- Important #3: added a MANDATORY post-deploy manual smoke test (Honza logs in + opens a multi-round game) to cover the auth-only `get` path that can't be curl-tested.
- Important #4: reframed N+1 as a code-quality cleanup, not the headline perf win (FA is the real perceived-latency gain).
**Verified non-issues (no change needed):** CSP (`.htaccess` has only `frame-ancestors`) does NOT block the inline `onload` or cdnjs → FA pattern works; rollback reverts the whole merge commit atomically (no partial-revert risk); SRI hash in plan confirmed identical to the live files; total_score recompute still works with regrouped data; version/CHANGELOG locations confirmed accurate.
**Not applied (out of scope):** full CSP hardening (`default-src`/`style-src`) — security debt, deliberate non-fix per prior audit; lftp net:timeout — separate CI concern.

### Round 2 (2026-06-23)
**Reviewed by:** Fresh Perspective, Best Practices, CI & Workflow Safety (3 reviewers).
**Findings:** 0 Critical, 0 Important. Verdicts: 3 PASS — plan converged.
**Outcome:** Round-1 fixes verified correct: defensive-grouping reference pattern is sound PHP (no dangling-ref footgun, `unset($r)` sufficient, `rr.*` carries `round_id`, field parity + ORDER BY equivalence confirmed); mandatory smoke test is a real end-to-end check of the auth-only `get` path; rollback atomic; config-deletion risk absent; HITL gate correctly placed. No changes applied (no findings to apply).

## Krok 0 — Found assets
- Search executed: 2026-06-22, repo `c:\weby\stychy`.
- Locations searched: `docs/plans/`, `docs/audits/`, FA icon usage across 4 pages, local asset dirs.
- Results: no prior perf plan (3 unrelated plans exist); `docs/audits/` empty; no local `assets/`/`fonts/`/`webfonts/`; **51 unique FA icons** in use (corrects earlier "handful" claim — informs decision to async-load rather than hand-subset now).
- Decision: greenfield perf plan; async-CDN over subset for this cycle (Honza confirmed); subset deferred to follow-up.
