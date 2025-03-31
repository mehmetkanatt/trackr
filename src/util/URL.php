<?php

namespace App\util;

class URL
{
    static function clearQueryParams($url, $partsToRemove = [], $removeFragment = true)
    {
        $parsedUrl = parse_url($url);
        $predefinedList = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'ref', 'ref_', 'refid', 'r', 'refsrc', 'ref_source', 'ref_source_', 'ref_sourceid', 'ref_sourceid_', 'refsrc', 'triedRedirect'];
        $partsToRemove = array_merge($partsToRemove, $predefinedList);

        // Reconstruct the base URL without the fragment
        $cleanUrl = (isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '') . $parsedUrl['host'] .
            (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '') .
            (isset($parsedUrl['path']) ? $parsedUrl['path'] : '');

        // Process query parameters
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            foreach ($partsToRemove as $param) {
                unset($queryParams[$param]); // Remove unwanted params
            }
            if (!empty($queryParams)) {
                $cleanUrl .= '?' . http_build_query($queryParams);
            }
        }

        if (!$removeFragment && isset($parsedUrl['fragment'])) {
            $cleanUrl .= '#' . $parsedUrl['fragment'];
        }

        return $cleanUrl;
    }
}