<?php

namespace PHPWatch\PHPCommitBuilder;
use Ayesh\CurlFetcher\CurlFetcher;

class CommitFetcher {
    private const API_ENDPOINT_COMPARE = 'https://api.github.com/repos/php/php-src/compare/';
    private const API_ENDPOINT_COMMIT_LIST = 'https://api.github.com/repos/php/php-src/commits';
    private ?string $apiKey = null;
    private CurlFetcher $curlFetcher;

    public function __construct(string $apiKey = null) {
        $this->apiKey = $apiKey;
        $this->curlFetcher = new CurlFetcher();
    }

    public function getCommitListForPastDays(int $pastDays): array {
        return $this->getListDateRange(date('c', time() - ($pastDays * 86400)), date('c'));
    }

    private function getListDateRange(string $since, string $until): array {
        $params = [
            'since' => $since,
            'until' => $until,
            'per_page' => 100,
            'page' => 1,
        ];

        $return = [];
        $hardLimits = 50;

        do {
            $paramsUrl = http_build_query($params);
            $url = static::API_ENDPOINT_COMMIT_LIST . '?' .$paramsUrl;

            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'Authorization: Bearer '. $this->apiKey;
            }

            $data = $this->curlFetcher->getJson($url, $headers);
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $return = array_merge($return, $data);

            $params['page']++;
            $hardLimits--;

        } while (count($data) >= 100 && $hardLimits > 0);

        return $return;
    }
}
