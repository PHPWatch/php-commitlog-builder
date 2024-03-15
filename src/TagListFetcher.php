<?php

namespace PHPWatch\PHPCommitBuilder;

use Ayesh\CurlFetcher\CurlFetcher;
use stdClass;

class TagListFetcher {
    private const string API_ENDPOINT_TAG_LIST = 'https://api.github.com/repos/php/php-src/tags';
    private const string API_ENDPOINT_TAG_INDIVIDUAL = 'https://api.github.com/repos/php/php-src/git/commits/%tag';

    private const string REGEX_TAG_PATTERN = '/^php-\d\.\d\.(?:\d\d?|0(?:alpha\d|beta\d|rc\d|RC\d)?)$/i';
    private ?string $apiKey;
    private CurlFetcher $curlFetcher;

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey;
        $this->curlFetcher = new CurlFetcher();
    }

    public function getReleaseTags(): array {
        $tags = $this->getAllTags();
        return array_filter($tags, static function (stdClass $tag): bool {
            return (bool)preg_match(self::REGEX_TAG_PATTERN, $tag->name);
        });
    }

    public function getAllTags(): array {
        $params = [
            'page' => 1,
            'per_page' => 100,
        ];

        $baseUrl = static::API_ENDPOINT_TAG_LIST;

        $return = [];
        $hardLimits = 50;

        do {
            $paramsUrl = http_build_query($params);
            $url = $baseUrl . '?' . $paramsUrl;

            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
            }

            $data = $this->curlFetcher->getJson($url, $headers);
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $return = array_merge($return, $data);

            $params['page']++;
            $hardLimits--;
        } while (count($data) >= 100 && $hardLimits > 0);

        return $return;
    }

    public function getSingleTagDate(string $tagHash): string {
        $baseUrl = strtr(static::API_ENDPOINT_TAG_INDIVIDUAL, ['%tag' => $tagHash]);

        $headers = [];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $tagInfo = $this->curlFetcher->getJson($baseUrl, $headers);
        return substr($tagInfo->author->date, 0, 10);
    }
}
