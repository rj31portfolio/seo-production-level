<?php

namespace App\Services\Seo;

class RobotsParser
{
    public function parse(string $text): array
    {
        $groups = [];
        $agents = [];
        $rules = [];
        $sitemaps = [];
        $delay = 0;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key,$value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if ($rules) {
                    $groups[] = ['agents' => $agents, 'rules' => $rules];
                    $agents = [];
                    $rules = [];
                }
                $agents[] = strtolower($value);
            } elseif (in_array($key, ['allow', 'disallow'], true) && $agents && $value !== '') {
                $rules[] = ['allow' => $key === 'allow', 'path' => $value];
            } elseif ($key === 'sitemap') {
                $sitemaps[] = $value;
            } elseif ($key === 'crawl-delay' && is_numeric($value)) {
                $delay = max($delay, min(60, (float) $value));
            }
        }
        if ($agents) {
            $groups[] = ['agents' => $agents, 'rules' => $rules];
        }

        return ['groups' => $groups, 'sitemaps' => array_values(array_unique($sitemaps)), 'crawl_delay_seconds' => $delay];
    }

    private function encoded(string $path): string
    {
        $path = preg_replace_callback('/%([0-9a-f]{2})/i', fn ($m) => preg_match('/[A-Za-z0-9._~-]/', chr(hexdec($m[1]))) ? chr(hexdec($m[1])) : strtoupper($m[0]), $path);

        return preg_replace_callback('/[^\x00-\x7f]/', fn ($m) => sprintf('%%%02X', ord($m[0])), $path);
    }

    public function allowed(array $parsed, string $url, string $agent = 'seoagencyos'): bool
    {
        $path = (parse_url($url, PHP_URL_PATH) ?: '/').(($query = parse_url($url, PHP_URL_QUERY)) !== null ? '?'.$query : '');
        $path = $this->encoded($path);
        $specific = array_filter($parsed['groups'], fn ($g) => in_array(strtolower($agent), $g['agents'], true));
        $groups = $specific ?: array_filter($parsed['groups'], fn ($g) => in_array('*', $g['agents'], true));
        $best = -1;
        $allow = true;
        foreach ($groups as $group) {
            foreach ($group['rules'] as $rule) {
                $pattern = $this->encoded($rule['path']);
                $end = str_ends_with($pattern, '$');
                if ($end) {
                    $pattern = substr($pattern, 0, -1);
                }
                $regex = '~^'.str_replace('\*', '.*', preg_quote($pattern, '~')).($end ? '$' : '').'~';
                $length = strlen(str_replace('*', '', $pattern));
                if (preg_match($regex, $path) && ($length > $best || ($length === $best && $rule['allow']))) {
                    $best = $length;
                    $allow = $rule['allow'];
                }
            }
        }

        return $allow;
    }
}
