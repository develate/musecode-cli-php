<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

/**
 * Sandbox network access for a session, mapped onto `muse --sandbox-network`.
 */
enum SandboxNetwork: string
{
    case Restricted = 'restricted';
    case Enabled = 'enabled';
    case ProxyOnly = 'proxy-only';
}
