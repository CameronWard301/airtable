<?php
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpMultipleClassDeclarationsInspection */
/**
 * DokuWiki Plugin airtable (action component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

//must be run within DokuWiki
if(!defined('DOKU_INC')) {
    die();
}

class InvalidAirtableCacheCreation extends Exception {
    public function errorMessage(): string {
        return $this->getMessage();
    }
}

class action_plugin_airtable extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, 'handle_indexer_tasks_run');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');
    }

    /**
     * Checks the currently used cache files and then invalidates the new cache if it is out of date
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered
     *
     * @return void
     */
    public function handle_indexer_tasks_run(Doku_Event $event, $param) {
        $metadata = p_get_metadata($_GET['id']);
        if(empty($metadata)) {
            return;
        }
        if(!($cacheHelper = $this->loadHelper('airtable_cacheInterface'))) { //load the helper function
            echo 'could not load cache interface helper';
        }
        $cache_ids = $metadata['plugin']['airtable']['cache_ids'];
        if(!empty($cache_ids)) {
            $expire_time = $this->getConf('Airtable_Refresh') * 60; //todo add * 60
            foreach($cache_ids as $cache_id => $request) { //check each cache file to see if it needs updating
                if(!$cacheHelper->checkCacheFreshess($cache_id, $expire_time)) {
                    //cache is not fresh, time to update
                    $event->preventDefault();
                    $event->stopPropagation();
                    touch($cacheHelper->getCacheFile($cache_id));
                }
            }
        }
    }

    public function handle_parser_cache_use(Doku_Event &$event, $param) {
        $page_cache =& $event->data;

        if(!isset($page_cache->page)) {
            return;
        }
        if(!isset($page_cache->mode) || $page_cache->mode != 'xhtml') {
            return;
        }

        $metadata = p_get_metadata($page_cache->page, 'plugin');

        if(empty($metadata['airtable'])) {
            return;
        }

        if(!($cacheHelper = $this->loadHelper('airtable_cacheInterface'))) { //load the helper functions
            echo 'could not load the cache interface helper';
        }

        $cache_items = $metadata['airtable']['cache_ids'];

        try {
            if(!empty($cache_items)) {
                $expire_time = $this->getConf('Airtable_Refresh') * 60; //todo add * 60
                foreach($cache_items as $cache_id => $request) {
                    if(!$cacheHelper->checkCacheFreshness($cache_id, $expire_time)) { //update cache if not fresh (expired)
                        $latest_api_data = $cacheHelper->sendRequest($request); //get latest API data from airtable
                        $existing_cache  = $cacheHelper->getExistingCache($cache_id);//get existing data from cache
                        if($latest_api_data === $existing_cache) {
                            $cacheHelper->updateETag($cache_id); //update the last time we checked the cache
                        } else {
                            $new_cache                      = $cacheHelper->newCache($cache_id, $latest_api_data, md5(time()));
                            $page_cache->depends['files'][] = $new_cache->cache;
                            $event->preventDefault();   // stop dokuwiki carrying out its own checks
                            $event->stopPropagation();  // avoid other handlers of this event, changing our decision here
                            $event->result = false;     // don't use the cached version
                            return;
                        }
                    }
                    $page_cache->depends['files'][] = $cacheHelper->getCacheFile($cache_id); //adds the file path of the cache file to the depends array
                }
            }
            return;
        } catch(InvalidAirtableCacheCreation $e) {
            echo "<p style='color: red; font-weight: bold;'>External Embed Error: " . $e->getMessage() . "</p>";
            return;
        }
    }
}