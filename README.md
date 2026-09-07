# Muse Code CLI for PHP

A small, resilient PHP SDK for controlling the Muse Code CLI. The package follows the CLI's native model:

```text
Muse → Session → Run → Events → Result
```

It uses one `muse exec --json` process per turn. Follow-up turns resume the session by ID; no persistent child process is required.

## Requirements

- PHP 8.2 or newer
- Muse Code installed and authenticated
- `muse` available on `PATH`, or an absolute binary path

```bash
composer require develate/musecode-cli-php
```

## Query a session

A session is described by one `SessionOptions` object, a run by one `RunOptions` object. Session options are re-sent on every start and resume, because `muse exec` keeps no flags across processes.

```php
use Develate\MusecodeCli\Muse;
use Develate\MusecodeCli\RunOptions;
use Develate\MusecodeCli\SessionOptions;
use Develate\MusecodeCli\Value\ApprovalMode;
use Develate\MusecodeCli\Value\Effort;

$muse = new Muse();

$session = $muse->session(new SessionOptions(
    cwd: '/var/www/app',
    model: 'metamate',
    effort: Effort::Low,
    approvalMode: ApprovalMode::OnRequest,
    workspace: '/var/www/app',
));

$run = $session->stream('What does this project do?');

foreach ($run as $item) {
    // TextDelta, RunTerminal and MspEvent items, as they arrive.
}

$result = $run->result();
echo $result->text;
```

## Resume a session

```php
$session = $muse->resume($sessionId, new SessionOptions(cwd: '/var/www/app'));
$result = $session->query('Continue where you left off.');
```

The id is required: resuming "whatever ran last" is a TUI affordance that on a
shared machine is not necessarily this caller's conversation.

## Read-only helper runs

Titles, commit messages and similar helper work must never write to the
project. A read-only profile guarantees that:

```php
$options = (new SessionOptions(cwd: '/var/www/app'))
    ->withApprovalMode(ApprovalMode::Never)
    ->with(disableWrite: true, disableShell: true);

$text = $muse->query('Summarise these changes in one line.', $options)->text;
```

## Authentication

`muse login` writes `auth.json` under the configuration home and `META_API_KEY`
takes priority over it. The SDK never executes anything to answer this:

```php
$muse->isAuthenticated(); // true when either one is present
$muse->authFilePath();    // where this client keeps its credentials
```

Pointing `HOME` (or `XDG_CONFIG_HOME`) at another directory isolates one local
identity from another, which is how hosts keep several accounts apart.
