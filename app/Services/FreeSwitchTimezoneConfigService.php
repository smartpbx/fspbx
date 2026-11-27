<?php

namespace App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SimpleXMLElement;

class FreeSwitchTimezoneConfigService
{
    public const DEFAULT_ZONEINFO_DIRECTORY = '/usr/share/zoneinfo';

    public const DEFAULT_DESTINATION = '/etc/freeswitch/autoload_configs/timezones.conf.xml';

    private const REQUIRED_FALLBACKS = [
        'America/Edmonton' => '<-06>6',
        'America/Winnipeg' => '<-05>5',
    ];

    /**
     * Build a FreeSWITCH timezone table from the POSIX rule stored at the end
     * of each TZif file. Zone names come from the installed tzdata tree so
     * aliases supported by the host remain available to FreeSWITCH.
     */
    public function build(string $zoneinfoDirectory = self::DEFAULT_ZONEINFO_DIRECTORY): string
    {
        $rules = $this->readRules($zoneinfoDirectory);

        // Keep the two 2026 Canadian corrections safe even if a minimal
        // tzdata package omits an alias, ships an unreadable TZif file, or
        // still exposes the superseded seasonal rule.
        foreach (self::REQUIRED_FALLBACKS as $zone => $fallback) {
            if (! isset($rules[$zone]) || $rules[$zone] === '' || str_contains($rules[$zone], ',')) {
                $rules[$zone] = $fallback;
            }
        }

        foreach ($rules as $zone => $rule) {
            $rules[$zone] = $this->normalizeForFreeSwitch($rule, $zone);
        }

        ksort($rules, SORT_STRING);

        $lines = [
            '<configuration name="timezones.conf" description="Timezones">',
            "\t<!-- Generated from the host tzdata POSIX rules. Do not edit by hand. -->",
            "\t<timezones>",
        ];

        foreach ($rules as $zone => $rule) {
            $lines[] = sprintf(
                "\t\t<zone name=\"%s\" value=\"%s\" />",
                $this->xml($zone),
                $this->xml($rule)
            );
        }

        $lines[] = "\t</timezones>";
        $lines[] = '</configuration>';

        $xml = implode("\n", $lines)."\n";
        $this->validate($xml);

        return $xml;
    }

    /**
     * Atomically replace the live table. The optional backup is written only
     * once so it remains a stable rollback point across future deployments.
     */
    public function sync(
        string $destination = self::DEFAULT_DESTINATION,
        string $zoneinfoDirectory = self::DEFAULT_ZONEINFO_DIRECTORY,
        ?string $backup = null
    ): bool {
        $xml = $this->build($zoneinfoDirectory);
        $current = is_file($destination) ? file_get_contents($destination) : false;

        if ($current === $xml) {
            return false;
        }

        $writeDestination = is_link($destination) ? realpath($destination) : $destination;
        if ($writeDestination === false) {
            throw new RuntimeException("The timezone table symlink {$destination} is broken.");
        }

        $directory = dirname($writeDestination);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create {$directory}.");
        }

        if ($current !== false && $backup !== null && ! file_exists($backup)) {
            if (! copy($destination, $backup)) {
                throw new RuntimeException("Unable to back up {$destination} to {$backup}.");
            }
            chmod($backup, 0644);
        }

        $temporary = tempnam($directory, '.timezones.');
        if ($temporary === false) {
            throw new RuntimeException("Unable to create a temporary file in {$directory}.");
        }

        try {
            $bytes = file_put_contents($temporary, $xml, LOCK_EX);
            if ($bytes !== strlen($xml)) {
                throw new RuntimeException("Unable to write the complete timezone table to {$temporary}.");
            }

            chmod($temporary, 0644);

            if (! rename($temporary, $writeDestination)) {
                throw new RuntimeException("Unable to replace {$writeDestination}.");
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }

        return true;
    }

    public function countZones(string $xml): int
    {
        return substr_count($xml, '<zone name=');
    }

    public function extractPosixRule(string $zoneinfoFile): ?string
    {
        $contents = @file_get_contents($zoneinfoFile);
        if ($contents === false || ! str_starts_with($contents, 'TZif')) {
            return null;
        }

        if (! preg_match('/\n([^\n]+)\n\z/s', $contents, $matches)) {
            return null;
        }

        $rule = trim($matches[1]);

        return $rule !== '' && ! preg_match('/[\x00-\x1F\x7F]/', $rule)
            ? $rule
            : null;
    }

    /**
     * FreeSWITCH's switch_time.c cannot parse POSIX's quoted timezone names
     * (for example <-05>5) and silently falls back to UTC. This mirrors the
     * normalization in FreeSWITCH's scripts/perl/timezones/fix-tzstr.pl.
     */
    public function normalizeForFreeSwitch(string $rule, string $zone): string
    {
        if (! str_starts_with($rule, '<')) {
            return $rule;
        }

        if (! preg_match(
            '/^<(?<standard>[^>]+)>[^<]+(?:(?<daylight><[^>]+>)(?<rest>.*))?$/',
            $rule,
            $matches
        )) {
            throw new RuntimeException("Unable to normalize the quoted POSIX rule for {$zone}: {$rule}");
        }

        $standard = $matches['standard'];
        if (! preg_match('/^(?<sign>[+-]?)(?<digits>\d{1,4})$/', $standard, $offset)) {
            throw new RuntimeException("Unable to normalize the quoted POSIX offset for {$zone}: {$rule}");
        }

        $digits = ltrim($offset['digits'], '0');
        $digits = $digits === '' ? '0' : $digits;
        if (strlen($digits) > 2) {
            $digits = substr($digits, 0, -2).':'.substr($digits, -2);
        }

        // POSIX offsets have the opposite sign from their UTC displacement.
        $offsetPrefix = $offset['sign'] === '+' ? '-' : '';
        $prefix = stripos($zone, 'gmt') !== false ? 'GMT' : 'STD';
        $daylight = isset($matches['daylight']) && $matches['daylight'] !== '' ? 'DST' : '';
        $rest = $matches['rest'] ?? '';
        $normalized = $prefix.$offsetPrefix.$digits.$daylight.$rest;

        if (str_contains($normalized, '<') || str_contains($normalized, '>')) {
            throw new RuntimeException("The POSIX rule for {$zone} remains incompatible with FreeSWITCH: {$normalized}");
        }

        return $normalized;
    }

    /** @return array<string, string> */
    private function readRules(string $zoneinfoDirectory): array
    {
        $root = realpath($zoneinfoDirectory);
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException("The tzdata directory {$zoneinfoDirectory} was not found.");
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        $rules = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $zone = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));

            if ($this->excluded($zone)) {
                continue;
            }

            $rule = $this->extractPosixRule($path);
            if ($rule !== null) {
                $rules[$zone] = $rule;
            }
        }

        if ($rules === []) {
            throw new RuntimeException("No TZif POSIX rules were found under {$root}.");
        }

        return $rules;
    }

    private function excluded(string $zone): bool
    {
        return str_starts_with($zone, 'posix/')
            || str_starts_with($zone, 'right/')
            || in_array($zone, ['localtime', 'posixrules'], true);
    }

    private function validate(string $xml): void
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            if ($document === false
                || $document->getName() !== 'configuration'
                || (string) $document['name'] !== 'timezones.conf') {
                throw new RuntimeException('The generated FreeSWITCH timezone table is invalid.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
