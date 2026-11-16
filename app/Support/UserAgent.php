<?php

namespace App\Support;

/**
 * Small built-in user-agent reader for the Security Center's device lists --
 * recognises the common phones, tablets, browsers and systems. Anything else
 * reads as "Unknown device"; the raw text is still shown on hover.
 */
class UserAgent
{
    /**
     * @return array{device: string, browser: string, os: string}
     */
    public static function describe(?string $ua): array
    {
        $ua = (string) $ua;

        if ($ua === '') {
            return ['device' => 'Unknown', 'browser' => 'Unknown browser', 'os' => 'Unknown system'];
        }

        $device = match (true) {
            (bool) preg_match('/iPad|Tablet|(Android(?!.*Mobile))/i', $ua) => 'Tablet',
            (bool) preg_match('/Mobile|iPhone|iPod|Android|Windows Phone/i', $ua) => 'Phone',
            (bool) preg_match('/Windows|Macintosh|Mac OS X|Linux|X11|CrOS/i', $ua) => 'Desktop',
            default => 'Unknown',
        };

        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Unknown system',
        };

        // Order matters: Edge and Opera also say "Chrome", Chrome also says "Safari".
        $browser = 'Unknown browser';

        foreach ([
            'Edge' => '/Edg(?:e|A|iOS)?\/(\d+)/',
            'Opera' => '/(?:OPR|Opera)\/(\d+)/',
            'Samsung Internet' => '/SamsungBrowser\/(\d+)/',
            'Firefox' => '/(?:Firefox|FxiOS)\/(\d+)/',
            'Chrome' => '/(?:Chrome|CriOS)\/(\d+)/',
            'Safari' => '/Version\/(\d+).*Safari/',
        ] as $name => $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                $browser = "{$name} {$m[1]}";
                break;
            }
        }

        return compact('device', 'browser', 'os');
    }
}
