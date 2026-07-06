# Fix the Štychy recorder UX batch: critical duplicate-round bug + layout maximization + voice hint

**Date:** 2026-07-06
**Owner:** Honza
**Status:** Active (autonomous execution; user asleep; merge/deploy held for HITL)
**Planner:** BaC-planner (voiced by Kochanski)

## Goal
Fix the batch of hands-on UX complaints from a real game: kill the double-submit duplicate-round bug (and make dup rounds removable), fix autocomplete keyboard nav, and maximize the recorder's use of screen (bigger type, full width, compact modals) plus a voice hint for the dealer's forbidden bid — all verified E2E in the local MariaDB docker, nothing deployed without Honza.

## Success criteria
- Double-click "Pokračovat k hlášení" creates exactly ONE round (client guard + server idempotence), proven by concurrent-request E2E in docker.
- An empty last round can be soft-deleted again (cleanup path); a round with bids/results cannot.
- Player-name autocomplete is keyboard-navigable (↓/↑ + Enter + Esc).
- Trump, bidding, results modals + running-ranking read bigger and use full width; nothing important below the fold on desktop; mobile (430×932) still one screen.
- Dealer's forbidden bid is announced by voice when it's the dealer's turn.
- Every item verified E2E in docker (localhost:8088, real data). Version bumped + CHANGELOG. PR(s) ready; NOT merged.

## Stakeholders
- Honza (owner; approves merge/deploy in the morning; played the real game that surfaced these)
- Juli (end user)

## Constraints
- **Autonomous run, user asleep:** do everything up to production. NEVER merge to master / deploy (deploy = live, unsupervised = forbidden). Source: CLAUDE.md (deploy=live, PR via HITL) + [[deploy-nikdy-nemazat-server]].
- **No hard delete** — round cleanup is soft (valid_to). Source: [[nikdy-hard-delete-vzdy-soft]]. Re-enabling round delete must stay soft + only last+empty.
- Vanilla inline CSS/JS in offline-recorder.html (~4700 lines), no build step. Shared CSS vars (--accent #00ffcc …). No em dash.
- Verify: node --check/vm.Script on JS, php -l, E2E in dev docker (MariaDB 11.8, real prod data). 
- Mobile target iPhone 14 Pro Max 430×932 (one screen) AND desktop (use width). Don't regress mobile while fixing desktop.
- Game rule: dealer must NOT bid so that sum(bids) == cards_count.

## Scope
**In:** items #1-#6 below. **Out:** #7 (data check — already done, clean); Model B versioning (unrelated); anything touching prod DB (docker only).

## Plan (sequenced; #1 first)

### Step 1: [CRITICAL] Duplicate-round bug — client guard + server idempotence + re-enable delete of last empty round
- **Produces:**
  - Client: `createRound()` guarded by an in-flight flag + disables the button while awaiting (no double-submit). Same pattern for any other create entry point.
  - Server `rounds.php` create: before inserting, if the current last round of the game is EMPTY (no bids and no results) and status not finished, return that existing round instead of creating a new one (idempotent against concurrency).
  - Server `rounds.php` delete: re-enabled but ONLY for the LAST round (MAX(round_number)) AND only if EMPTY (no bids/results) — soft-delete (valid_to + valid_to_user_id), no score reversal needed (empty). Otherwise reject.
  - Frontend: bring back a "smazat kolo" control, shown only for the last round when it is empty; calls the delete endpoint.
- **Proves success (docker E2E):** fire 3 concurrent `create` -> exactly 1 round exists; delete that empty last round -> gone; a round with bids -> delete rejected; normal create/bid/results flow still works; UNIQUE(game_id, round_number) never violated; `php -l` + JS check clean.
- **Touches:** `api/rounds.php` (create, delete), `offline-recorder.html` (createRound + round-action UI).
- **Constraints:** soft-delete only; last+empty only; must not create round_number gaps (deleting the last round keeps sequence contiguous); respect the flag/`valid_to` model already live.
- **HITL gate:** deploy only (held). Build+test autonomous.
- **Executor:** Claude. **Depends on:** nothing. **Rollback:** revert the two files.

### Step 2: Autocomplete keyboard navigation (player names)
- **Produces:** the name-suggestion dropdown supports ArrowDown/ArrowUp to move a highlighted item, Enter to pick, Esc to close; mouse still works; picking fills the input and moves on.
- **Proves success:** docker/manual DOM check — open new-game, type a known player, ArrowDown highlights first suggestion, Enter fills it; Esc closes; no regression to existing filtering.
- **Touches:** `offline-recorder.html` (autocomplete render + keydown handler).
- **Constraints:** keep existing suggestion source (localStorage known players) + already-chosen filtering; JS check clean.
- **HITL gate:** deploy only. **Depends on:** nothing. **Rollback:** revert.

### Step 3: Layout maximization — global type scale + full width, and specific modals
- **3a Trump modal:** dealer name large, cards-count as a big number; collapse the current 4 lines (Rozdává / name / card graphic / "8 karet") to ~2 lines, larger type.
- **3b New-game / new-round:** use width (wider modal, multi-column where sensible), larger controls.
- **3 global:** raise base type ~+50% in the recorder views/modals; widen modal max-width on desktop; keep mobile one-screen.
- **Produces:** CSS/markup changes achieving the above via shared vars.
- **Proves success:** DevTools at 430×932 (mobile still one screen, nothing clipped) AND desktop (wider use, bigger type, no side waste); screenshots.
- **Touches:** `offline-recorder.html` (CSS + a little markup).
- **Constraints:** shared CSS vars, no hardcoded colors; don't break mobile compact modals from earlier work; card shapes keep aspect ratio.
- **HITL gate:** deploy only. **Depends on:** none (parallel-safe with 4/5/6 but same file — sequence to avoid edit conflicts). **Rollback:** revert.

### Step 4: Bidding modal — compact layout + voice hint for dealer's forbidden bid
- **Produces:** bidding modal fits everything incl. the warning line without scroll on target screens; when it is the dealer's turn (last bidder), speak via the existing voice system how many the dealer cannot pick (the value that would make sum == cards_count), e.g. "Nahlášeno X ze Y, rozdávající nemůže zvolit Z".
- **Proves success:** docker E2E of a full round to the dealer; assert the forbidden value is computed correctly (cards_count - current_sum) and the voice call fires (verify the code path / speak function invoked with the right text); layout fits.
- **Touches:** `offline-recorder.html` (bidding render + voice call + the forbidden-bid calc that already disables the button at ~line 3570).
- **Constraints:** reuse existing voice/speak function; only when voice enabled (respect mute) but user said "force" style is acceptable for this hint — follow existing announce settings; rule: dealer can't make sum==cards_count.
- **HITL gate:** deploy only. **Depends on:** Step 3 (same file). **Rollback:** revert.

### Step 5: Results modal — compact header + Uložit button above the fold
- **Produces:** results modal header condensed; the Uložit (save) button visible without scrolling on target screens.
- **Proves success:** DevTools mobile + desktop — save button in view; save still works E2E in docker.
- **Touches:** `offline-recorder.html` (results modal CSS/markup).
- **Constraints:** keep the ✗/✓ big touch targets from earlier work; mobile one-screen.
- **HITL gate:** deploy only. **Depends on:** Step 3/4 (same file). **Rollback:** revert.

### Step 6: Running ranking — 2x type, scores next to names
- **Produces:** running-ranking rows with ~2x type and the score pulled left next to the name (not far right), easier to read.
- **Proves success:** DevTools screenshot mobile + desktop; readable, aligned.
- **Touches:** `offline-recorder.html` (ranking render CSS).
- **Constraints:** shared vars; keep medal/roast icons; mobile fit.
- **HITL gate:** deploy only. **Depends on:** Step 3-5 (same file). **Rollback:** revert.

### Step 7: Version bump + CHANGELOG + PR (held at merge)
- **Produces:** footer bump (v1.6.1 or v1.7.0), CHANGELOG entry, branch pushed, PR opened.
- **Proves success:** php -l + JS check clean; full E2E replay in docker green; PR shows the diff.
- **Touches:** offline-recorder.html footer + index.html footer, CHANGELOG.md.
- **HITL gate:** YES — Honza reviews + merges in the morning (merge = deploy). I do NOT merge.
- **Executor:** Claude (prep), Honza (merge). **Depends on:** 1-6. **Rollback:** n/a (not merged).

## Critical path
Step 1 (critical bug) -> Steps 2-6 (2 is independent; 3-6 share the file so sequential) -> Step 7 (bump + PR, held). All E2E-verified in docker before PR. Highest value: Step 1.

## Definition of done (autonomous portion)
- Steps 1-6 implemented, each E2E-verified in docker (concurrent-create=1 round; delete last-empty works; autocomplete keys; layouts fit mobile+desktop; voice hint fires; save above fold; ranking readable).
- php -l + JS check clean; full happy-path replay green in docker.
- Branch pushed, PR open with a clear description + this plan linked.
- Morning summary written. Merge/deploy awaits Honza.

## Notes on autonomy
User said "makej autonomně … jdu spát". Interactive AskUserQuestion phases skipped (no one to answer) — intake was fully specified in the brief. Miro-replanning/council ceremony skipped (needs user gating); replaced by rigorous per-item docker E2E as the quality gate. The single hard stop is production deploy.
