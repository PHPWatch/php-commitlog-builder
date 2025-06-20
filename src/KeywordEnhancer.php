<?php

namespace PHPWatch\PHPCommitBuilder;

class KeywordEnhancer {
    protected const array CODIFY_PATTERNS = [
        '/\b(?<!`)(?:zend|php)_[a-z_]+\(\)(?!`)/i', // zend_foo_bar()
        '/\b(?<!`)(?:zend|php|_php)_[a-z_*]+\b(?![`.(=])\*?/i', // zend_foo_bar
        '/\b(?<![`\/])[a-z][a-z\d_-]+(.stubs?)?\.(phpt?|c|h)(?![`.?])/', // run-tests.php / foo.stub.php foo.stubs.php / test-foo-bar.phpt,
        '/\b(?<!`)ext\/[a-z_]+\b(?![`\/])/', // ext-names
        '/\b(?<!`)[A-Za-z][A-Za-z\d]+::(?:__)?[a-z][A-Za-z\d_]+\(\)(?![`\/-])/', // Class::methods()
        '/\b(?<!`)[A-Z][A-Za-z]+::(?:__)[a-z][A-Za-z\d_]+(?![`\/-])\b(?![)(])/', // Class::__magicMethods
        '/\b(?<!`)[A-Z][A-Za-z]+::[A-Z_]+\b(?![`\/(])/', // Class::CONSTANTS
        '/\b(?<![`\\\\])[A-Z][A-Z\\\\a-z]+::[a-z][A-Za-z_\d]+\b(?![`\/(])/', // Class::constants
        '/\b(?<!`-)[a-z]+_[a-z]+(?:_[a-z_]+)?\(\)(?![`\/])/', // Functions with underscores and ()
        '/\b(?<![`>])[a-z_][a-z][a-z\d_]+\(\)(?![`.>\/-])/', // Functions with underscores and ()
        '/\b(?<!`)(?:ldap|ftp|array|mb|stream|open|hash|xml|proc|pcntl)_[a-z_]+\d?\b(?![`\/])/', // Functions with underscores and no ()
        '/\b(?<!`)(xleak|xfail|skipif)\b(?![`\/])/i', // xleak
        '/(?<![`>()-])--[a-z][a-z-]+(?![`])/i', // --flags, --flags-and-more
        '/(?<![`>()-])\bext\/[a-z_\d\/-]+\.phpt\b(?![`])/i', // ext/test/test/test.phpt
        '/\b(?<![`>:-])__[A-Z\d_]+(?![`])/i', // __PROPERTY__
        '/(?<=\s)(?<![`>-])\\\\[A-Z][a-z]+\\\\[A-Z][A-Za-z]+(?![`])\b/', // Stricter, class name like \Dom\HTMLDocument
        '/\b(?<![`>()-])(?:(main|ext|Zend|tests|win32|scripts|sapi|pear|docs|build)\/(?:[a-z\/_]+))(?:\.(c|php|phpt|yml|yaml|cpp|m4|txt|w32|h))(?::\d+)?(?![`])/i', // files in php-src
        '/\b(?<!`)(?:(SOAP|SO|TCP|SOCK|FILTER|XML|FTP|CURL|GREP))_[A-Z_]+\b(?![`\/(])/', // CONSTANTS (with fixed prefixes)
    ];

    public static function enhance(string $inputText): string {
        return static::format($inputText);
    }

    public static function enhanceCommit(string $commitSubject, string $shortHash): string {
        return static::format($commitSubject, $shortHash);
    }

    private static function format(string $inputText, ?string $shortHash = null): string {
        $inputText = static::linkToBug($inputText);
        $inputText = static::linkToGitHub($inputText, $shortHash);
        $inputText = static::codifyText($inputText);
        $inputText = static::linkToSecurityAnnouncements($inputText);

        return $inputText;
    }

    private static function linkToBug(string $subject): string {
        if (preg_match('/#[5-9]\d{4}/', $subject)) {
            return preg_replace('/#(\d{5})/', '[#$1](https://bugs.php.net/bug.php?id=$1)', $subject);
        }

        return $subject;
    }

    private static function linkToGitHub(string $subject, ?string $shortHash = null): string {
        $subject = preg_replace('/\b(?<!\[)GH-(\d{3,6})\b/', "[GH-$1](https://github.com/php/php-src/issues/$1)", $subject);

        $subject = preg_replace('/\b(?<!\[)GH-(\d{3,6})\b/', "[GH-$1](https://github.com/php/php-src/issues/$1)", $subject);
        $subject = preg_replace('/(?<![`>()\b\d\S\[-])#([1-3]\d\d\d\d)\b(?![`-])/', "[GH-$1](https://github.com/php/php-src/issues/$1)", $subject);

        $subject = preg_replace(
            '/\b(?<!\[)(([a-fA-F\d]){8})([a-fA-F\d]){4,32}\b/',
            "[$1](https://github.com/php/php-src/commit/$0)",
            $subject
        );

        if (preg_match('/(?<!\[)\(#(\d{3,6})\)$/', $subject)) {
            return preg_replace('/\(#(\d{3,6})\)$/', "in [GH-$1](https://github.com/php/php-src/pull/$1)", $subject);
        }

        if (preg_match('/Closes GH-(\d{3,6})\D?/', $subject, $matches)) {
            $subject .= sprintf(" in [GH-%d](https://github.com/php/php-src/pull/%d)", $matches[1], $matches[1]);
            return $subject;
        }

        if ($shortHash) {
            return $subject . sprintf(' in [%s](https://github.com/php/php-src/commit/%s)', $shortHash, $shortHash);
        }

        return $subject;
    }

    private static function linkToSecurityAnnouncements(string $inputText): string {
        if (preg_match('/(CVE-20\d\d-\d{1,5})\D/i', $inputText)) {
            $inputText = preg_replace(
                '/(CVE-20\d\d-\d{1,5})(\D)/i',
                "[$1](https://nvd.nist.gov/vuln/detail/$1)$2",
                $inputText
            );
        }

        if (preg_match('/(GHSA-[a-z\d-]{14})(\W)/i', $inputText)) {
            $inputText = preg_replace(
                '/(GHSA-[a-z\d-]{14})(\W)/',
                "[$1](https://github.com/php/php-src/security/advisories/$1)$2",
                $inputText
            );
        }

        return $inputText;
    }

    private static function codifyText(string $input): string {
        foreach (static::CODIFY_PATTERNS as $pattern) {
            $input = preg_replace($pattern, '`$0`', $input);
        }

        return $input;
    }
}
