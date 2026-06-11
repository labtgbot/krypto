<?php

/**
 * Rss feed class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class RssFeed
{

    /**
     * Rss feed url
     * @var String
     */
    private $rssSQL = null;

    /**
     * Rss feed data
     * @var Array
     */
    private $feedData = null;

    /**
     * RssFeed constructor
     * @param Array $rssdata Rss feed data
     */
    public function __construct($rssdata = null)
    {
        if (!is_null($rssdata)) {
            $this->rssSQL = $rssdata;

            // Load rss feed
            $this->_loadRssFeed();
        }
    }

    /**
     * Get RssFeed url
     * @return String RssFeed url
     */
    public function _getUrl()
    {
        if (is_null($this->rssSQL)) {
            throw new Exception("Error : Data is null for rss feed", 1);
        }
        return $this->rssSQL['url_rssfeed'];
    }

    /**
     * Load rss feed
     * @return [type] [description]
     */
    public function _loadRssFeed()
    {
        // Get rss feed json data & parse
        $dataRssJSON = json_decode(file_get_contents($this->_getRss2JsonUrl()), true);

        // Check rss feed result
        if (is_array($dataRssJSON) && array_key_exists('status', $dataRssJSON) && $dataRssJSON['status'] == "ok") {
            $this->feedData = $dataRssJSON;
        } else {
            error_log('Fail to parse rss feed : '.$this->_getUrl());
        }
    }

    private function _getRss2JsonUrl()
    {
        $query = ['rss_url' => $this->_getUrl()];
        $apiKey = $this->_getRss2JsonApiKey();
        if ($apiKey !== '') {
            $query['api_key'] = $apiKey;
        }

        return 'https://api.rss2json.com/v1/api.json?'.http_build_query($query, '', '&');
    }

    private function _getRss2JsonApiKey()
    {
        if (defined('KRYPTO_RSS2JSON_API_KEY') && trim((string) KRYPTO_RSS2JSON_API_KEY) !== '') {
            return (string) KRYPTO_RSS2JSON_API_KEY;
        }
        if (function_exists('krypto_env_config_value')) {
            $envValue = krypto_env_config_value('KRYPTO_RSS2JSON_API_KEY', '');
            if (trim((string) $envValue) !== '') {
                return (string) $envValue;
            }
        } else {
            $envValue = getenv('KRYPTO_RSS2JSON_API_KEY');
            if ($envValue !== false && trim((string) $envValue) !== '') {
                return (string) $envValue;
            }
        }

        if (class_exists('App')) {
            try {
                $App = new App(false);
                return $App->_getRss2JsonApiKey();
            } catch (Exception $e) {
                error_log('Fail to load rss2json API key from settings : '.$e->getMessage());
            }
        }

        return '';
    }

    /**
     * Get RssFeed title
     * @return String RssFeed title
     */
    public function _getFromTitle()
    {
        return $this->feedData['feed']['title'];
    }

    /**
     * Get feed list
     * @return Array Feed list
     */
    public function _getFeedList()
    {
        return $this->feedData['items'];
    }
}
