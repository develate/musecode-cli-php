# Changelog

## 2026-09-15

- Report per-run token usage and the effective model: results now carry the run id from the terminal record, and usage is read from the session log's `model_completed` records for that run, since the `exec --json` stream never reports token counts. `Usage` keeps uncached input beside the cache counters (Meta reports the total input with the cache inside it) and gains the cache-write counter.

## 2026-09-11

- Recognize Muse Code's `Usage limit reached` notice as an exhausted weekly quota window, including its reset time, instead of waiting for a full subscription panel to time out.

## 2026-09-08

- Add `Muse::mcp()` to list, add, remove, enable, and disable native stdio and HTTP MCP server configurations with atomic settings writes.

- Read subscription quota and plan name from `/upgrade`, and available models with descriptions from `/model`, using PTY/Expect.

## 2026-09-07

- Add `Muse::quota()` using PTY/Expect to read current and weekly subscription usage with reset labels, bounded retries, and child-process cleanup.

## 1.0.0 - 2026-09-07

- Initial release: headless sessions over `muse exec --json`, resume by `--session-id`, MSP event parsing, version and authentication probes.
