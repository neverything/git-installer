<?php

namespace SayHello\GitInstaller\Package\Provider;

use SayHello\GitInstaller\Helpers;

class Gitlab extends Provider
{
    public static string $provider = 'gitlab';

    public static function validateUrl($url): bool
    {
        if (!$url) return false;
        $parsed = self::parseGitlabUrl($url);
        return $parsed['host'] === 'gitlab.com' && isset($parsed['id']);
    }

    private static function parseGitlabUrl($url): array
    {
        if (substr($url, -4) === ".git") {
            $url = rtrim($url, ".git");
        }
        $url = rtrim($url, '/');
        $url = explode('/-/', $url)[0];

        $regex = '/^(https:\/\/gitlab\.com\/|git@gitlab.com:)([\S]+)/';
        $match = preg_match($regex, $url, $matches);

        if ($match) {
            $params = explode('/', $matches[2]);
            return [
                'host' => 'gitlab.com',
                'id' => urlencode($matches[2]),
                'repo' => end($params),
                'url' => $url,
            ];
        }

        return [
            'host' => 'invalid',
            'id' => '',
            'repo' => '',
        ];
    }

    public static function getInfos($url)
    {
        if (!self::validateUrl($url)) {
            return new \WP_Error(
                'invalid_url',
                sprintf(__('"%s" is not a valid Gitlab repository', 'shgi'), $url)
            );
        }

        $parsedUrl = self::parseGitlabUrl($url);
        // https://gitlab.com/api/v4/projects/say-hello%2Fplugins%2Fhello-cookies
        $apiUrl = 'https://gitlab.com/api/v4/projects/' . $parsedUrl['id'];
        $auth = self::authenticateRequest($apiUrl);

        $response = Helpers::getRestJson($auth[0], $auth[1]);
        if (is_wp_error($response)) return $response;

        $branches = self::getBranches($parsedUrl['id']);

        if (is_wp_error($branches)) return $branches;

        return [
            'key' => $parsedUrl['repo'],
            'name' => $response['name'],
            'private' => $response['visibility'] === 'private',
            'provider' => self::$provider,
            'branches' => $branches,
            'baseUrl' => $response['web_url'],
            'apiUrl' => $apiUrl,
        ];
    }

    private static function getBranches($id)
    {
        $apiUrl = 'https://gitlab.com/api/v4/projects/' . $id;
        $apiBranchesUrl = "{$apiUrl}/repository/branches";
        $auth = self::authenticateRequest($apiBranchesUrl);
        $response = Helpers::getRestJson($auth[0], $auth[1]);
        if (is_wp_error($response)) return $response;

        $branches = [];
        foreach ($response as $branch) {
            $branches[$branch['name']] = [
                'name' => $branch['name'],
                'url' => $branch['web_url'],
                'zip' => trailingslashit($apiUrl) . 'repository/archive.zip?sha=' . $branch['name'],
                'default' => $branch['default'],
            ];
        }
        return $branches;
    }

    private static function getRepoFolderFiles($id, $branch, $folder = ''): array
    {
        $allPagesParsed = false;
        $page = 0;
        $files = [];
        $perPage = 100;

        while (!$allPagesParsed) {
            $page++;
            $auth = self::authenticateRequest("https://gitlab.com/api/v4/projects/{$id}/repository/tree/?ref={$branch}&recursive=1&per_page={$perPage}&page={$page}");
            $response = Helpers::getRestJson($auth[0], $auth[1]);
            if (count($response) < $perPage) {
                $allPagesParsed = true;
            }
            $files = array_merge(
                $files,
                array_values(
                    array_filter(
                        $response,
                        function ($element) use ($folder) {
                            if ($element['type'] !== 'blob') return false;
                            if (!str_starts_with($element['path'], $folder)) return false;
                            if ($element['path'] === 'style.css') return true;
                            $relativePath = substr($element['path'], strlen($folder));
                            if (str_contains($relativePath, '/')) return false;
                            return str_ends_with($relativePath, '.php');
                        }
                    )
                )
            );
        }

        return array_map(function ($element) use ($folder, $id, $branch) {
            $path = urlencode($element['path']);
            $url = "https://gitlab.com/api/v4/projects/{$id}/repository/files/{$path}?ref={$branch}";
            $content = self::fetchFileContent($url);
            return [
                'file' => $element['path'],
                'fileUrl' => $url,
                'content' => $content,
            ];
        }, $files);
    }

    public static function fetchFileContent($url): ?string
    {
        $auth = self::authenticateRequest($url);
        $response = Helpers::getRestJson($auth[0], $auth[1]);

        return base64_decode($response['content']);
    }

    public static function validateDir($url, $branch, $dir): array
    {
        $parsed = self::parseGitlabUrl($url);
        return self::getRepoFolderFiles($parsed['id'], $branch, $dir);
    }

    public static function authenticateRequest($url, $args = []): array
    {
        $authHeader = self::authHeader();
        if ($authHeader) {
            $args = [
                'headers' => [
                    'Authorization' => $authHeader,
                ]
            ];
        }

        return [$url, $args];
    }

    public static function authHeader()
    {
        $gitlabToken = sayhelloGitInstaller()->Settings->getSingleSettingValue('git-packages-gitlab-token');
        if (!$gitlabToken) return false;
        return 'Bearer ' . Provider::trimString($gitlabToken);
    }

    public static function export(): object
    {
        return new class {
            public function name(): string
            {
                return 'Gitlab';
            }

            public function hasToken(): bool
            {
                return boolval(sayhelloGitInstaller()->Settings->getSingleSettingValue('git-packages-gitlab-token'));
            }

            public function validateUrl($url): bool
            {
                return Gitlab::validateUrl($url);
            }

            public function getInfos($url)
            {
                return Gitlab::getInfos($url);
            }

            public function authenticateRequest($url, $args = []): array
            {
                return Gitlab::authenticateRequest($url, $args);
            }

            public function validateDir($url, $branch, $dir = ''): array
            {
                return Gitlab::validateDir($url, $branch, $dir);
            }

            public function fetchFileContent($url): string
            {
                return Gitlab::fetchFileContent($url);
            }

            public function getAuthHeader()
            {
                return Gitlab::authHeader();
            }
        };
    }
}
