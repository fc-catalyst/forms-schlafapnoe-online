<?php

namespace CF\WordPress;

class HooksByURL extends Hooks {
    public function purgeCacheByRelevantURLsTRUE($urls) {
        if ($this->isPluginSpecificCacheEnabled() || $this->isAutomaticPlatformOptimizationEnabled()) {
            $wpDomainList = $this->integrationAPI->getDomainList();
            if (!count($wpDomainList)) {
                return;
            }
            $wpDomain = $wpDomainList[0];
            $zoneTag = $this->api->getZoneTag($wpDomain);
            if (!isset($zoneTag)) {
                return;
            }

            $urls = (array) $urls;
/*
            $postIds = (array) $postIds;
            $urls = [];
            foreach ($postIds as $postId) {
                // Do not purge for autosaves or updates to post revisions.
                if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
                    continue;
                }

                $postType = get_post_type_object(get_post_type($postId));
                if (!is_post_type_viewable($postType)) {
                    continue;
                }

                $savedPost = get_post($postId);
                if (!is_a($savedPost, 'WP_Post')) {
                    continue;
                }

                $relatedUrls = apply_filters('cloudflare_purge_by_url', $this->getPostRelatedLinks($postId), $postId);
                $urls = array_merge($urls, $relatedUrls);
            }
//*/
            // Don't attempt to purge anything outside of the provided zone.
            foreach ($urls as $key => $url) {
                $url_to_test = $url;
                if (is_array($url) && !!$url['url']) {
                    $url_to_test = $url['url'];
                }

                if (!Utils::strEndsWith(parse_url($url_to_test, PHP_URL_HOST), $wpDomain)) {
                    unset($urls[$key]);
                }
            }

            if (empty($urls)) {
                return;
            }

            // Filter by unique urls
            $urls = array_values(array_filter(array_unique($urls)));
/*
            $activePageRules = $this->api->getPageRules($zoneTag, "active");
            $hasCacheOverride = $this->pageRuleContains($activePageRules, "cache_level", "cache_everything");

            // Should we not have a 'cache_everything' page rule override, feeds
            // shouldn't be attempted to be purged as they are not cachable by
            // default.
            if (!$hasCacheOverride) {
                $this->logger->debug("cache everything behaviour found, filtering out feeds URLs");
                $urls = array_filter($urls, array($this, "pathIsNotForFeeds"));
            }

            // Fetch the page rules and should we not have any hints of cache
            // all behaviour or APO, filter out the non-cacheable URLs.
            if (!$hasCacheOverride && !$this->isAutomaticPlatformOptimizationEnabled()) {
                $this->logger->debug("cache everything behaviour and APO not found, filtering URLs to only be those that are cacheable by default");
                $urls = array_filter($urls, array($this, "pathHasCachableFileExtension"));
            }

            if ($this->zoneSettingAlwaysUseHTTPSEnabled($zoneTag)) {
                $this->logger->debug("zone level always_use_https is enabled, removing HTTP based URLs");
                $urls = array_filter($urls, array($this, "urlIsHTTPS"));
            }
//*/
            if (!empty($urls)) {
                //do_action('cloudflare_purged_urls', $urls, $postIds);
                $chunks = array_chunk($urls, 30);

                foreach ($chunks as $chunk) {
                    $isOK = $this->api->zonePurgeFiles($zoneTag, $chunk);

                    $isOK = ($isOK) ? 'succeeded' : 'failed';
                    $this->logger->debug("List of URLs purged are: " . print_r($chunk, true));
                    $this->logger->debug("purgeCacheByRelevantURLs " . $isOK);
                }

                // Purge cache on mobile if APO Cache By Device Type
                if ($this->isAutomaticPlatformOptimizationCacheByDeviceTypeEnabled()) {
                    foreach ($chunks as $chunk) {
                        $isOK = $this->api->zonePurgeFiles($zoneTag, array_map(array($this, 'toPurgeCacheOnMobile'), $chunk));

                        $isOK = ($isOK) ? 'succeeded' : 'failed';
                        $this->logger->debug("List of URLs purged on mobile are: " . print_r($chunk, true));
                        $this->logger->debug("purgeCacheByRelevantURLs " . $isOK);
                    }
                }
            }
        }
    }
}