<?php

namespace PHPWatch\PHPCommitBuilder;

class NewsFormatter {
    private array $releases;

    use FormatterHelpers;

    public function __construct(array $releases) {
        $this->releases = $releases;
    }

    public function getNewsListForRelease(string $version): array {
        if (!isset($this->releases[$version])) {
            return [];
        }

        if (!isset($this->releases[$version]['version'], $this->releases[$version]['changes'])) {
            throw new \RuntimeException(
                'Parsed array structure is invalid. Does not contain both version and changes fields'
            );
        }

        if (empty($this->releases[$version]['changes'])) {
            throw new \RuntimeException('Parsed array structure is invalid. Changes list is empty');
        }

        $version = $this->releases[$version];

        foreach ($version['changes'] as &$changes) {
            foreach ($changes as &$change) {
                $change = $this->removeAuthorInBraces($change);
                $change = KeywordEnhancer::enhance($change);
            }
        }

        return $version;
    }


    public function getNewsListForReleaseMarkup(string $version): string {
        $release = $this->getNewsListForRelease($version);

        if (empty($release)) {
            return '';
        }

        $output = '';

        foreach ($release['changes'] as $ext => $changes) {
            $output .= self::markdownTitle($ext);

            foreach ($changes as $change) {
                $output .= self::markdownListItem($change);
            }

            $output .= self::EOL . self::EOL;
        }

        return $output;
    }

    private function removeAuthorInBraces(string $change): string {
        $change = preg_replace('/(^(.*))( \([\w\p{L} ,.-]+\).?$)/u', '$1', $change, 1);

        return $change;
    }
}
