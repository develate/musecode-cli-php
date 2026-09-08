<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Models;

use Develate\MusecodeCli\Quota\UsagePanelParser;

final class ModelPanelParser
{
    /** @return list<ModelInfo> */
    public function parse(string $output): array
    {
        $row = null;
        $output = preg_replace_callback('/\x1b\[(\d+);(\d+)[Hf]/', static function (array $match) use (&$row): string {
            $separator = $row !== $match[1] ? "\n" : ' ';
            $row = $match[1];

            return $separator;
        }, $output) ?? $output;
        $text = (new UsagePanelParser)->plainText($output);
        $start = strrpos($text, 'Choose model');
        if ($start === false) {
            return [];
        }
        $text = substr($text, $start + strlen('Choose model'));
        $models = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (str_contains($line, '↑↓')) {
                break;
            }
            if (preg_match('/^\s*(?:⟩\s*)?([a-zA-Z0-9][a-zA-Z0-9._:\/-]*)(?:\s+(.*))?\s*$/u', $line, $match) === 1) {
                $models[$match[1]] = new ModelInfo($match[1], trim($match[2] ?? ''));
            }
        }

        return array_values($models);
    }
}
