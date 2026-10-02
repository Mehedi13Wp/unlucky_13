<?php

namespace App\Services\Leads;

class DomainNormalizer
{
    public function normalizeWebsite(?string $website): array
    {
        $website = trim((string) $website);

        if ($website === '') {
            return [
                'website_url' => null,
                'domain' => null,
            ];
        }

        $normalizedUrl = preg_match('/^https?:\/\//i', $website)
            ? $website
            : 'https://'.$website;

        $host = parse_url($normalizedUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return [
                'website_url' => $normalizedUrl,
                'domain' => null,
            ];
        }

        $domain = strtolower(rtrim($host, '.'));

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            $domain = null;
        }

        return [
            'website_url' => $normalizedUrl,
            'domain' => $domain ?: null,
        ];
    }
}
