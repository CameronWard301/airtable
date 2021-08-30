<?php
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */
/**
 * DokuWiki Plugin airtable (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron Ward <cameronward007@gmail.com>
 */

if(!defined('DOKU_INC')) die();

class helper_plugin_airtable_cacheInterface extends DokuWiki_Plugin {

    /**
     * Get the data stored in the cache file e.g. thumbnail encoded data
     *
     * @param $cache_id string the id of the cache
     * @return mixed
     */
    public function getExistingCache(string $cache_id) {
        $cache = new cache_airtable($cache_id);
        return json_decode($cache->retrieveCache(), true);
    }

    /**
     * Updates the E tag of the cache file to be the current time
     * @param $cache_id string the id of the cache
     */
    public function updateETag(string $cache_id) {
        $cache = new cache_airtable($cache_id);
        $cache->storeETag(md5(time()));
    }

    /**
     * Return true if the cache is still fresh, otherwise return false
     * @param      $cache_id string the cache id
     * @param      $time     // the expiry time of the cache
     * @return bool
     */
    public function checkCacheFreshness(string $cache_id, $time): bool {
        $cache = new cache_airtable($cache_id);

        if($cache->checkETag($time)) {
            return true;
        }

        return false;
    }

    /**
     * Public function generates new cache object
     * Stores the data within a json encoded cache file
     * @param      $cache_id  string the unique identifier for the cache
     * @param null $data      The data to be stored in the cache
     * @param null $timestamp When the cache was created
     * @return cache_airtable the cache object
     */
    public function newCache(string $cache_id, $data = null, $timestamp = null): cache_airtable {
        $cache = new cache_airtable($cache_id);
        $cache->storeCache(json_encode($data));
        $cache->storeETag($timestamp);
        return $cache;
    }

    /**
     * Get the file path of the cache file associated with the ID
     * @param $cache_id string The id of the cache file
     * @return string The file path for the cache file
     */
    public function getCacheFile(string $cache_id): string {
        $cache = new cache_airtable($cache_id);
        return $cache->cache;
    }

    /**
     * Method to call the airtable API
     *
     * @param $request
     * @return false|string|array
     */
    public
    function sendRequest($request) {
        $base_ID = $this->getConf('Base_ID');
        $api_key = $this->getConf('API_Key');
        $url     = 'https://api.airtable.com/v0/' . $base_ID . '/' . $request;
        $curl    = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            'Authorization: Bearer ' . $api_key
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        //TODO: remove once in production:
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);//

        $api_response = json_decode(curl_exec($curl), true); //decode JSON to associative array

        if(curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            if(key_exists("error", $api_response)) {
                $message = json_encode($api_response['error']);
            } else {
                $message = "Unknown API api_response error";
            }
            return array(false => $message);
        }
        curl_close($curl);
        return $api_response;
    }
}

/**
 * Class that handles cache files, file locking and cache expiry
 */
class cache_airtable extends \dokuwiki\Cache\Cache {
    public $e_tag = '';
    var $_etag_time;

    public function __construct($embed_id) {
        parent::__construct($embed_id, '.airtable');
        $this->e_tag = substr($this->cache, 0, -15) . '.etag';
    }

    public function getETag($clean = true) {
        return io_readFile($this->e_tag, $clean);
    }

    public function storeETag($e_tag_value): bool {
        if($this->_nocache) return false;

        return io_saveFile($this->e_tag, $e_tag_value);
    }

    public function getCacheData() {
        return json_decode($this->retrieveCache(), true);

    }

    /**
     * Public function that returns true if the cache (Etag) is still fresh
     * Otherwise false
     * @param $expireTime
     * @return bool
     */
    public function checkETag($expireTime): bool {
        if($expireTime < 0) return true;
        if($expireTime == 0) return false;
        if(!($this->_etag_time = @filemtime($this->e_tag))) return false; //check if cache is still there
        if((time() - $this->_etag_time) > $expireTime) return false; //Cache has expired
        return true;
    }
}
