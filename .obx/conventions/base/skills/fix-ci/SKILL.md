---
claude:
  argument-hint: <pr-number>
  disable-model-invocation: 'true'
description: Diagnose and fix CI failures on a pull request. Fetch the CI logs, classify
  the failure (lint, type, test, build), and apply a targeted fix. Use when CI is
  failing on a PR, or when asked about a CI or test failure.
export: redistributable
tier: portable-generic
---

# Fix CI — Diagnose and Fix CI Failures

You are helping fix CI failures on a pull request in this repository. This skill
is a **procedure**, not a set of facts about any one project — learn the facts
you need from this repository's own documentation before you touch anything.{skill_repo_overlay}

## Step 0: Orient yourself in this repository

Do this first, every time — the layout, commands, and conventions below are
whatever *this* repository declares, not what any other project uses.

1. Read this repository's agent documentation if it exists — look for
   `CLAUDE.md`, `AGENTS.md`, or a `docs/` directory. These are the source of
   truth for architecture, package layout, and the exact check commands.
2. **If there is no agent documentation at all**, self-orient before editing:
   read the `README`, then list the top-level directory tree to understand where
   source and tests live. Do not assume a layout — confirm it.
3. Note the project's package layout: {stack_package_layout}. Each unit may have
   its own tests and its own check command.

## Step 1: Get PR context

Use the `{vcs_cli}` CLI to read the pull request — its branch, base branch, and the
status of each check:

```bash
{vcs_cmd_pr_status}
{vcs_cmd_pr_checks}
```

If every check is already passing, stop and report that — there is nothing to fix.

## Step 2: Fetch the failure logs

For each failing check, read the logs and focus on the failed steps:

```bash
{vcs_cmd_build_log}
```

Read the error output carefully. Do **not** re-run the same command after you
have already seen its output.

## Step 3: Classify the failure

Sort the failure into one category and fix only that:

| Category | Typical signal | Fix approach |
|----------|----------------|--------------|
| **Lint** | style/rule violations reported by the linter | Apply the auto-fixer first, then fix the rest by hand. |
| **Format** | a formatting diff | Apply the project's formatter — usually fully auto-fixable. |
| **Type errors** | type-checker diagnostics | Correct the annotation or the value; do not silence the checker. |
| **Test failures** | a failing assertion or a missing fixture | Read the failing test and the code under test; fix whichever is actually wrong. |
| **Build/dependency errors** | install or compile failure | Check the manifest and lockfile for a conflict. |

## Step 4: Fix the root cause

- Fix the root cause, not the symptom — do not suppress a rule or annotation
  just to make the check pass.
- Keep the change minimal and scoped to the CI failure. Do not refactor
  unrelated code.
- Stage specific files. Never stage everything blindly.

## Step 5: Verify before pushing

Run the project's own checks locally before you push — this prevents a second
round-trip.

**Lint:**

```
{stack_lint_check_cmd}
```

{stack_lint_fix_hint}

**Targeted tests** — group related test files into ONE {stack_test_runner} call
so startup is amortized and the runner can parallelize:

```
{stack_targeted_test_cmd_files}
```

When you changed a base class or a shared utility used across a test module, run
the whole module instead:

```
{stack_targeted_test_cmd_dir}
```

{stack_targeted_test_timeout_note_short}

Do not shape your run around bad patterns such as {stack_test_bad_examples} — a
whole-repository run is slow and drowns the signal you need.

If you are unsure which single command reproduces the failure, fall back to the
project's own check entry point ({stack_ci_check_hint}).

## Step 6: Commit and push

```bash
git add <specific-files>
git commit -m "Fix CI: <concise description of what was fixed>"
git push origin <branch>
```

The commit message should describe the fix, not the failure.

## Step 7: Confirm CI recovers

```bash
{vcs_cmd_pr_checks_watch}
```

If the checks fail again, return to Step 2 with the new logs.

## Important rules

- **Learn this repository's specifics from its own docs** — never assume another
  project's layout, commands, or conventions.
- **Commit early if turns are limited** — a partial, pushed fix beats spending
  every turn on verification.
- **One failure at a time** — fix the reported CI failure and nothing else.
