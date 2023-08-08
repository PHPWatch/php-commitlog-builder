<?php

namespace PHPWatch\PHPCommitBuilder;

trait FormatterHelpers {
    private const EOL = "\r\n";

    private static function markdownTitle(string $title): string {
        return '### ' . self::plainText($title) . static::EOL;
    }

    private static function markdownListItem(string $listItem): string {
        return ' - ' . self::plainText($listItem) . static::EOL;
    }

    private static function plainText(string $text): string {
        return htmlspecialchars($text);
    }
}
