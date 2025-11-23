<?php

namespace PHPWatch\PHPCommitBuilder;

class DownloadLinkFetcher {

	private const string RELEASES_JSON = 'https://downloads.php.net/~windows/releases/releases.json';
	private const string RELEASES_QA_JSON = 'https://downloads.php.net/~windows/qa/releases.json';

	private ?array $hashes = null;

    public function getLinksForTag(string $tag): array {
        $return = [];

        $urls = $this->getWindowsLinks($tag);
        $urlStatus = $this->getMultiUrlStatus($urls);

        foreach ($urls as $type => $links) {
            foreach ($links as $link) {
                if (isset($urlStatus[$link])) {
					$file = basename($link);
                    $return[$type] = [
                        'url' => $link,
                        'size' => $urlStatus[$link],
						'sha256' => isset($this->hashes[$file]) ? $this->hashes[$file]['sha256'] : null,
                    ];
                }
            }
        }

        return $return;
    }

    private static function inspectTag(string $tag): array {
		$info = [
			'QA' => false,
			'VS' => 'vs17',
			'lookupHash' => false,
		];

		if (preg_match('/^php-[\d.]+0(alpha|beta|rc|RC)\d$/', $tag)) {
			$info['QA'] = true;
		}

        if (preg_match('/^php-7\.2\./', $tag) || preg_match('/^php-7\.3\./', $tag)) {
            $info['VS'] = 'VC15';
			return $info;
        }

        if (preg_match('/^php-7\.4\./', $tag)) {
			$info['VS'] = 'vc15';
			return $info;
        }

        if (preg_match('/^php-8\.[0123]\./', $tag)) {
			$info['VS'] = 'vs16';
			$info['lookupHash'] = true;
			return $info;
        }

		return $info;
    }

	private function ensureReleasesJson(): void {
		if ($this->hashes !== null) {
			return;
		}

		$hashes = [];

		$this->ingestHashList(self::RELEASES_JSON, $hashes);
		$this->ingestHashList(self::RELEASES_QA_JSON, $hashes);

		$this->hashes = $hashes;
	}

	private function ingestHashList(string $url, &$hashes): void {
		$releasesJson = file_get_contents(self::RELEASES_JSON);
		$releases = json_decode($releasesJson, associative: true, depth: 10, flags: JSON_THROW_ON_ERROR);

		foreach ($releases as $version_ => $files) {
			foreach ($files as $file) {
				if (!isset($file['zip'])) {
					continue;
				}

				if (isset($file['zip']['sha256'])) {
					$hashes[$file['zip']['path']]['sha256'] = $file['zip']['sha256'];
				}
			}
		}
	}

    private function getWindowsLinks(string $tag): array {
        $folder = 'releases/archives';
        $folder_alt = 'releases';

		$info = self::inspectTag($tag);
		$vsVersion = $info['VS'];

		if ($info['QA']) {
			$folder = 'qa/archives';
			$folder_alt = 'qa';
		}

		$this->ensureReleasesJson();

        return [
            'x64NTS'     => [
                'https://downloads.php.net/~windows/' . $folder . '/' . $tag .     '-nts-Win32-'. $vsVersion .'-x64.zip',
                'https://downloads.php.net/~windows/' . $folder_alt . '/' . $tag . '-nts-Win32-'. $vsVersion .'-x64.zip',
            ],
            'x64TS'      => [
                'https://downloads.php.net/~windows/' . $folder . '/' . $tag .     '-Win32-' . $vsVersion . '-x64.zip',
                'https://downloads.php.net/~windows/' . $folder_alt . '/' . $tag . '-Win32-' . $vsVersion . '-x64.zip',
            ],
            'x86NTS'     => [
                'https://downloads.php.net/~windows/' . $folder . '/' . $tag .     '-nts-Win32-' . $vsVersion . '-x86.zip',
                'https://downloads.php.net/~windows/' . $folder_alt . '/' . $tag . '-nts-Win32-' . $vsVersion . '-x86.zip',
            ],
            'x86TS'      => [
                'https://downloads.php.net/~windows/' . $folder . '/' . $tag .     '-Win32-' . $vsVersion . '-x86.zip',
                'https://downloads.php.net/~windows/' . $folder_alt . '/' . $tag . '-Win32-' . $vsVersion . '-x86.zip',
            ],
        ];
    }

    private function getMultiUrlStatus(array $urlsets): array {
        $cm = curl_multi_init();
        $handlers = [];

        foreach ($urlsets as $type => $urls) {
            foreach ($urls as $i => $url) {
                $ch = curl_init($url);

                $handlers[$type][$i] = $ch;

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_PROTOCOLS, CURLPROTO_HTTPS  => CURLPROTO_HTTP,
                    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_ENCODING => '',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_USERAGENT => 'ayesh/curl-fetcher',

                    CURLOPT_NOBODY => true,
                    CURLOPT_HEADER => true,

					CURLOPT_HTTPHEADER => [
						'Range: bytes=-1',
					],
                ]);

                curl_multi_add_handle($cm, $ch);
            }
        }

        do {
            curl_multi_exec($cm, $running);
            curl_multi_select($cm);
        } while ($running > 0);

        $completedUrls = [];

        foreach ($handlers as $urls) {
            foreach ($urls as $url) {
                if (curl_getinfo($url, CURLINFO_HTTP_CODE) === 206) {
                    $completedUrls[curl_getinfo($url, CURLINFO_EFFECTIVE_URL)] = curl_multi_getcontent($url);
                }
                curl_multi_remove_handle($cm, $url);
            }
        }

        curl_multi_close($cm);

        foreach ($completedUrls as &$headers) {
            preg_match('/content-range: bytes \d+-\d+\/(?<size>\d+)\b/i', $headers, $matches);
            if (!empty($matches['size'])) {
                $headers = $matches['size'];
            }
            else {
                throw new \LogicException('Content-range not matched');
            }
        }

        return $completedUrls;
    }

}
