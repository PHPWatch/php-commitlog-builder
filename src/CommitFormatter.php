<?php

namespace PHPWatch\PHPCommitBuilder;

class CommitFormatter {

    use FormatterHelpers;
    private array $commitsList = [];
    private array $commitsGroupedByAuthor = [];

    private array $nameReplacements = [];

    public function __construct(array $inputCommits, array $nameReplacements = []) {
        $this->nameReplacements = $nameReplacements;
        $this->process($inputCommits);
    }

    private function process(array $inputCommits): void {
        $formattedCommits = [];
        $i = 0;

        foreach ($inputCommits as $commit) {
            $commitArray = $this->splitCommit($commit);
            if ($this->shouldSkip($commitArray['subject'])) {
                continue;
            }

            $commitArray['formatted'] = KeywordEnhancer::enhanceCommit(
                $commitArray['subject'],
                substr($commitArray['hash'], 0, 10)
            );
            $formattedCommits[$i] = $commitArray;

            if (!isset($this->commitsGroupedByAuthor[$commitArray['author']])) {
                $this->commitsGroupedByAuthor[$commitArray['author']] = [];
            }

            $this->commitsGroupedByAuthor[$commitArray['author']][] = $i;

            $i++;
        }

        $this->commitsList = $formattedCommits;
        $this->groupByAuthorNames($this->commitsList, $this->nameReplacements);
    }

    private function groupByAuthorNames(): void {
        foreach ($this->nameReplacements as $originalName => $newName) {
            if (isset($this->commitsGroupedByAuthor[$originalName])) {
                $this->commitsGroupedByAuthor[$newName] = $this->commitsGroupedByAuthor[$originalName];
                unset($this->commitsGroupedByAuthor[$originalName]);
            }
        }

        ksort($this->commitsGroupedByAuthor, SORT_NATURAL | SORT_FLAG_CASE);
    }

    private function splitCommit(\stdClass $commit): array {
        $commitMessage = $commit->commit->message;
        $commitMessageParts = explode("\n", $commitMessage, 2);

        return [
            'subject' => trim(trim($commitMessageParts[0]), '.'),
            'author' => trim($commit->commit->author->name),
            'hash' => trim($commit->sha),
            'message' => trim($commitMessage),
        ];
    }

    private function shouldSkip(string $commitMessage): bool {
        if ($commitMessage === '') {
            return true;
        }

        // Skip merge commits
        if (str_starts_with($commitMessage, 'Merge branch')) {
            return true;
        }

        // Skip merge commits
        if (str_starts_with($commitMessage, 'Merge remote-tracking branch')) {
            return true;
        }

        // Skip "[ci skip]" messages
        if (str_contains($commitMessage, '[ci skip]') || str_contains($commitMessage, '[skip ci]')) {
            return true;
        }

        if (
            str_contains($commitMessage, 'is now for PHP 8')
            || str_contains($commitMessage, 'is now for PHP-8')
            || str_contains($commitMessage, 'PHP-8.0 is now for 8')
        ) {
            return true;
        }

        if (str_starts_with($commitMessage, 'Update NEWS for ')) {
            return true;
        }

        return false;
    }

    public function getFormattedCommitList(): array {
        return $this->commitsList;
    }

    public function getFormattedCommitListMarkup(): string {
        $output = '';
        foreach ($this->getFormattedCommitList() as $commit) {
            $output .= self::markdownListItem(
                $commit['formatted'] . ' by ' . ($this->nameReplacements[$commit['author']] ?? $commit['author'])
            );
        }

        return $output;
    }

    public function getFormattedCommitListGroupedByAuthor(): array {
        $return = $this->commitsGroupedByAuthor;
        foreach ($return as $author => &$commitList) {
            foreach ($commitList as $key => &$commitI) {
                $commitI = $this->commitsList[$commitI];
            }
        }

        return $return;
    }

    public function getFormattedCommitListGroupedByAuthorMarkup(): string {
        $commitsByAuthor = $this->getFormattedCommitListGroupedByAuthor();

        $output = '';
        foreach ($commitsByAuthor as $author => $commitList) {
            $output .= self::markdownTitle($author);
            foreach ($commitList as $commit) {
                $output .= self::markdownListItem($commit['formatted']);
            }

            $output .= self::EOL . self::EOL;
        }

        return $output;
    }
}
