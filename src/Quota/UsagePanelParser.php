<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Quota;

final class UsagePanelParser
{
    public function plainText(string $output): string
    {
        $output = preg_replace('/\x1b\][^\x07]*(?:\x07|\x1b\\\\)/', '', $output) ?? $output;
        $output = preg_replace('/\x1b\[[0-?]*[ -\/]*[HfG]/', ' ', $output) ?? $output;

        return preg_replace('/\x1b(?:\[[0-?]*[ -\/]*[@-~]|[78M])/', '', $output) ?? $output;
    }

    public function parse(string $output): ?Quota
    {
        $text = $this->plainText($output);
        $start = strrpos($text, 'Subscription');
        if ($start === false) {
            return null;
        }
        $text = substr($text, $start);
        $time = '(\d{1,2}:\d{2}\s*[AP]M)';
        if (preg_match('/\bCurrent\s+(\d+(?:\.\d+)?)%\s+used\s*·\s*Resets\s+at\s+'.$time.'/i', $text, $current) !== 1
            || preg_match('/\bWeekly\s+(\d+(?:\.\d+)?)%\s+used\s*·\s*Resets\s+([A-Z][a-z]{2}\s+\d{1,2})\s+at\s+'.$time.'/i', $text, $weekly) !== 1
            || (float) $current[1] > 100 || (float) $weekly[1] > 100) {
            return null;
        }

        return new Quota((float) $current[1], (float) $weekly[1], trim($current[2]), trim($weekly[2]).' '.trim($weekly[3]));
    }
}
