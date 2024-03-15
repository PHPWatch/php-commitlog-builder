<?php

namespace PHPWatch\PHPCommitBuilder;

use Ayesh\CurlFetcher\CurlFetcher;

use function sprintf;

class NewsFetcher {
    private const string RAW_CONTENT_URL = 'https://raw.githubusercontent.com/php/php-src/%tag/NEWS';

    private const string REGEX_PIPE_HEADER = '/^\|+$/';
    private const string REGEX_RELEASE_HEADER = '/^(?<date>(?<day>\d\d?|\?\?) (?<month>Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|\?\?\?) (?<year>\?\?\?\?|20\d\d)), (PHP|php) (?<release_id>\d\.\d\.(?:\d\d?|0(?:alpha\d|beta\d|rc\d|RC\d)?))$/';
    private const string REGEX_EXT_HEADER = '/^- ?(?<ext_name>[A-Za-z][A-Za-z _\/\d]+):? ?$/';

    private const string REGEX_CHANGE_RECORD_START = '/^  ? ?(\.|-) (?<change_record>.*)$/';
    private const string REGEX_CHANGE_RECORD_CONTINUATION = '/^(    ?|\t)(?<change_record_cont>.*)$/';

    private ?string $apiKey;
    private CurlFetcher $curlFetcher;

    private array $staticReplacements = [];

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey;
        $this->curlFetcher = new CurlFetcher();
    }

	public function setReplacements(array $replacements): void {
		$this->staticReplacements = $replacements;
	}

    public function fetchAllForVersion(int|string $version): array {
        if (is_string($version)) {
            if ($version !== 'master') {
                throw new \InvalidArgumentException('String arguments must be "master"');
            }
            $baseUrl = strtr(static::RAW_CONTENT_URL, [
                '%tag' => 'master',
            ]);
        } else {
            preg_match('/^(?<major>^\d)0(?<minor>\d)\d\d?$/', (string)$version, $matches);

            if (empty($matches)) {
                throw new \InvalidArgumentException('Invalid $version: Must be in integer "XYYZZ" format');
            }

            $baseUrl = strtr(static::RAW_CONTENT_URL, [
                '%tag' => "PHP-{$matches['major']}.{$matches['minor']}",
            ]);
        }

        $headers = [];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $contents = $this->curlFetcher->get($baseUrl, $headers);

        return $this->parseNewsPage($contents);
    }

    private function parseNewsPage(string $contents): array {
        $contents = preg_split('/\R/', $contents);

        $releases = [];
        $accumulatedChanges = [];
        $cursorVersion = null;
        $cursorExt = null;
        $lastLine = null;

        foreach ($contents as $lineNo => $line) {
            $lineNo = (int)$lineNo;
            ++$lineNo; // Line numbers start from 1, although the array index starts at 0

            if (isset($this->staticReplacements[$line])) {
                $line = $this->staticReplacements[$line];
            }

            // Should skip line?
            if ($this->skipLine($line, $lineNo)) {
                continue;
            }

            // Is this a new release header?
            if ($release = $this->releaseHeader($line)) {
                $releases[$release['version']] = $release;

                if ($cursorVersion) {
                    $release[$cursorVersion]['changes'] = $accumulatedChanges;
                }
                $cursorVersion = $release['version'];
                $cursorExt = null;
                $lastLine = null;

                continue;
            }

            if (empty($cursorVersion)) {
                throw new \RuntimeException(
                    'Cursor version should not be empty when detecting a new extension change set'
                );
            }

            // Is this a new ext changeset header?
            if ($extName = $this->extHeader($line)) {
                $cursorExt = $extName;
                $lastLine = null;
                continue;
            }

            if (empty($cursorExt)) {
                throw new \RuntimeException('Cursor ext name should not be empty when detecting a new change');
            }

            // is this new change record?
            if ($changeRecord = $this->changeRecord($line)) {
                $lastLine = $lineNo;
                $releases[$cursorVersion]['changes'][$cursorExt][$lastLine] = $changeRecord;
                continue;
            }

            if ($lastLine === null) {
                throw new \RuntimeException(
                    sprintf(
                        "Cursor last line number should not be empty when detecting a continuation of a change record on line %d:\r\n%s",
                        $lineNo,
                        $line
                    )
                );
            }

            // is this continuation of a line?
            if ($changeRecordCont = $this->lineWrapped($line)) {
                $releases[$cursorVersion]['changes'][$cursorExt][$lastLine] .= ' ' . $changeRecordCont;
                continue;
            }

            throw new \Exception(sprintf("Unknown line format at line %d:\r\n%s", $lineNo, $line));
        }

        return $releases;
    }

    private function skipLine(mixed $line, int $lineNo): bool {
        if ($line === '' || trim($line) === '') {
            return true;
        }

        if ($lineNo === 1 && str_starts_with($line, 'PHP     ')) {
            return true;
        }

        if (preg_match(self::REGEX_PIPE_HEADER, $line)) {
            return true;
        }

        if (str_starts_with($line, '<<< NOTE: Insert NEWS')) {
            return true;
        }

        return false;
    }

    private function releaseHeader(string $line): array|false {
        if (preg_match(self::REGEX_RELEASE_HEADER, $line, $matches)) {
            return [
                'version' => $matches['release_id'],
                'date' => str_contains(
                    $matches['date'],
                    '?'
                ) ? null : "{$matches['year']} {$matches['month']} {$matches['day']}",
                'changes' => [],
            ];
        }

        return false;
    }

    private function extHeader(string $line): string|false {
        if (preg_match(self::REGEX_EXT_HEADER, $line, $matches)) {
            return $matches['ext_name'];
        }

        return false;
    }

    private function changeRecord(string $line): string|false {
        if (preg_match(self::REGEX_CHANGE_RECORD_START, $line, $matches)) {
            return $matches['change_record'];
        }

        return false;
    }

    private function lineWrapped(string $line): string|false {
        if (preg_match(self::REGEX_CHANGE_RECORD_CONTINUATION, $line, $matches)) {
            return $matches['change_record_cont'];
        }

        return false;
    }
}
