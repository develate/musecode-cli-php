<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Quota;

use Develate\MusecodeCli\Quota\UsagePanelParser;
use PHPUnit\Framework\TestCase;

final class UsagePanelParserTest extends TestCase
{
    public function test_reads_subscription_windows_from_cursor_positioned_output(): void
    {
        $output = "Session usage Input 0 Total 0\033[16;3HYou are currently subscribed to the\033[16;16HMuse Code Everyday Usage usage plan.\033[17;5HCurrent\033[17;20H31%\033[17;24Hused · Resets at 11:38 PM\033[18;5HWeekly\033[18;20H46% used · Resets Sep 14 at 2:00 AM";

        $quota = (new UsagePanelParser)->parse($output);

        self::assertNotNull($quota);
        self::assertSame(31.0, $quota->currentUsedPercent);
        self::assertSame('Muse Code Everyday Usage', $quota->planName);
        self::assertSame(46.0, $quota->weeklyUsedPercent);
        self::assertSame('11:38 PM', $quota->currentReset);
        self::assertSame('Sep 14 2:00 AM', $quota->weeklyReset);
    }

    public function test_uses_the_last_complete_panel_when_a_redraw_is_partial(): void
    {
        $header = 'You are currently subscribed to the Muse Code Everyday Usage usage plan.';
        $complete = $header.' Current 0% used · Resets at 3:14 PM Weekly 46% used · Resets Sep 14 at 2:00 AM';

        $quota = (new UsagePanelParser)->parse($complete."\n".$header.' Current 0% used');

        self::assertNotNull($quota);
        self::assertSame(0.0, $quota->currentUsedPercent);
        self::assertSame('Muse Code Everyday Usage', $quota->planName);
    }

    public function test_rejects_missing_incomplete_and_invalid_subscription_windows(): void
    {
        $parser = new UsagePanelParser;

        self::assertNull($parser->parse('Session usage Input 10 Total 20'));
        self::assertNull($parser->parse('You are currently subscribed to the Test usage plan. Current 0% used · Resets at 11:38 PM'));
        self::assertNull($parser->parse('You are currently subscribed to the Test usage plan. Current 101% used · Resets at 11:38 PM Weekly 2% used · Resets Sep 14 at 2:00 AM'));
    }
}
