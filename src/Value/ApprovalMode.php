<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Value;

/**
 * Tool approval policy for a session, mapped onto `muse --approval-mode`.
 */
enum ApprovalMode: string
{
    case Untrusted = 'untrusted';
    case OnRequest = 'on-request';
    case Never = 'never';
}
