# Changelog

## 2026-09-08

- Add `Muse::mcp()` to list, add, remove, enable, and disable native stdio and HTTP MCP server configurations with atomic settings writes.

- Read subscription quota and plan name from `/upgrade`, and available models with descriptions from `/model`, using PTY/Expect.

## 2026-09-07

- Add `Muse::quota()` using PTY/Expect to read current and weekly subscription usage with reset labels, bounded retries, and child-process cleanup.

## 1.0.0 - 2026-09-07

- Initial release: headless sessions over `muse exec --json`, resume by `--session-id`, MSP event parsing, version and authentication probes.
