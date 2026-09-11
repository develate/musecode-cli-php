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
        if (preg_match_all('/You\s+are\s+currently\s+subscribed\s+to\s+the\s+(.+?)\s+usage\s+plan\./is', $text, $plans, PREG_OFFSET_CAPTURE) === 0) {
            return $this->usageLimit($text);
        }
        for ($index = count($plans[0]) - 1; $index >= 0; $index--) {
            $planName = trim((string) preg_replace('/\s+/', ' ', $plans[1][$index][0]));
            $start = $plans[0][$index][1];
            $end = $plans[0][$index + 1][1] ?? strlen($text);
            $quota = $this->windows(substr($text, $start, $end - $start), $planName);
            if ($quota !== null) {
                return $quota;
            }
        }

        return $this->usageLimit($text);
    }

    private function windows(string $text, string $planName): ?Quota
    {
        $time = '(\d{1,2}:\d{2}\s*[AP]M)';
        if (preg_match('/\bCurrent\s+(\d+(?:\.\d+)?)%\s+used\s*·\s*Resets\s+at\s+'.$time.'/i', $text, $current) !== 1
            || preg_match('/\bWeekly\s+(\d+(?:\.\d+)?)%\s+used\s*·\s*Resets\s+([A-Z][a-z]{2}\s+\d{1,2})\s+at\s+'.$time.'/i', $text, $weekly) !== 1
            || (float) $current[1] > 100 || (float) $weekly[1] > 100) {
            return null;
        }

        return new Quota((float) $current[1], (float) $weekly[1], trim($current[2]), trim($weekly[2]).' '.trim($weekly[3]), $planName);
    }

    private function usageLimit(string $text): ?Quota
    {
        $time = '(\d{1,2}:\d{2}\s*[AP]M)';
        if (preg_match('/\bUsage\s+limit\s+reached\b.*?\b(?:usage\s+to\s+)?reset\s+at\s+([A-Z][a-z]{2}\s+\d{1,2})\s+at\s+'.$time.'/is', $text, $weekly) !== 1) {
            return null;
        }

        return new Quota(null, 100.0, null, trim($weekly[1]).' '.trim($weekly[2]));
    }
}
