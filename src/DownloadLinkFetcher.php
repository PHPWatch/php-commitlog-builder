<?php

namespace PHPWatch\PHPCommitBuilder;
class DownloadLinkFetcher {
    public function getLinksForTag(string $tag): array {
        $return = [];

        $urls = $this->getWindowsLinks($tag);
        $urlStatus = $this->getMultiUrlStatus($urls);

        foreach ($urls as $type => $links) {
            foreach ($links as $link) {
                if (isset($urlStatus[$link])) {
                    $return[$type] = [
                        'url' => $link,
                        'size' => $urlStatus[$link],
                    ];
                }
            }
        }

        return $return;
    }

	private static function determineVCVersion(string $tag): string {
		if (preg_match('/^php-7\.2\./', $tag)) {
			return 'VC15';
		}
		if (preg_match('/^php-7\.3\./', $tag)) {
			return 'VC15';
		}
		if (preg_match('/^php-7\.4\./', $tag)) {
			return 'vc15';
		}
		return 'vs16';
	}

    private function getWindowsLinks(string $tag): array {
        $folder = 'releases/archives';
        $folder_alt = 'releases';

        if (preg_match('/^php-[\d.]+0(alpha|beta|rc|RC)\d$/', $tag)) {
            $folder = 'qa/archives';
            $folder_alt = 'qa';
        }

		$vsVersion = self::determineVCVersion($tag);

        return [
            'x64NTS'     => [
                'https://windows.php.net/downloads/' . $folder . '/' . $tag .     '-nts-Win32-'. $vsVersion .'-x64.zip',
                'https://windows.php.net/downloads/' . $folder_alt . '/' . $tag . '-nts-Win32-'. $vsVersion .'-x64.zip',
            ],
            'x64TS'      => [
                'https://windows.php.net/downloads/' . $folder . '/' . $tag .     '-Win32-' . $vsVersion . '-x64.zip',
                'https://windows.php.net/downloads/' . $folder_alt . '/' . $tag . '-Win32-' . $vsVersion . '-x64.zip',
            ],
            'x86NTS'     => [
                'https://windows.php.net/downloads/' . $folder . '/' . $tag .     '-nts-Win32-' . $vsVersion . '-x86.zip',
                'https://windows.php.net/downloads/' . $folder_alt . '/' . $tag . '-nts-Win32-' . $vsVersion . '-x86.zip',
            ],
            'x86TS'      => [
                'https://windows.php.net/downloads/' . $folder . '/' . $tag .     '-Win32-' . $vsVersion . '-x86.zip',
                'https://windows.php.net/downloads/' . $folder_alt . '/' . $tag . '-Win32-' . $vsVersion . '-x86.zip',
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

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ayesh/curl-fetcher');

                curl_multi_add_handle($cm, $ch);
            }
        }

        do {
            curl_multi_exec($cm, $running);
            curl_multi_select($cm);
        } while ($running > 0);

        $completedUrls = [];

        foreach ($handlers as $type => $urls) {
            foreach ($urls as $i => $url) {
                if (curl_getinfo($url, CURLINFO_HTTP_CODE) === 200) {
                    $completedUrls[curl_getinfo($url, CURLINFO_EFFECTIVE_URL)] = curl_multi_getcontent($url);
                }
                curl_multi_remove_handle($cm, $url);
            }
        }

        curl_multi_close($cm);

        foreach ($completedUrls as &$headers) {
            preg_match('/content-length: (?<size>\d+)\D/i', $headers, $matches);
            if (!empty($matches['size'])) {
                $headers = $matches['size'];
            }
            else {
                throw new \LogicException('Content-length not matched');
            }
        }

        return $completedUrls;
    }

}
