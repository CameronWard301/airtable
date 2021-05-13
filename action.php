<?php
/**
 * DokuWiki Plugin airtable (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
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
        $controller->register_hook('CONFMANAGER_CONFIGFILES_REGISTER', 'BEFORE', $this, 'addConfigFile', array());

    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $params [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function addConfigFile(Doku_Event $event, $params) {

        if(class_exists('ConfigManagerSingleLineCoreConfig')) {
            $description   = '
            Specify which airtable queries to run and which pages the data should go to.
            
            KEY: Enter a unique int - this is the order the requests are processed e.g. starting from 0
            VALUE Use this format: BASEID, QUERY, APIKEY, DESTINATION_FILE
            E.g.
            appZGFwgzjqeMwdqy, Martin%20Requests, keydCHnFFjxbYtkPN, start2.txt
            This will pull data from the "Martin Requests" airtable and save the result in start2.txt
            
            Note, please add the cronjob to your crontab in order to run your configuration file:
            */5 * * * * /usr/bin/php /home/username/public_html/lib/plugins/airtable/jobs.php >/dev/null 2>&1
            
            The job above ^ will make all requests in this config file every 5 minutes
            
            Airtable queries need to be URL encoded. Please see: https://codepen.io/airtable/full/rLKkYB?baseId=appZGFwgzjqeMwdqy&tableId=tbluKjrlpF4zBDr61
            '; //TODO Remove API KEY BEFORE PUBLISHING
            $config        = new ConfigManagerTwoLine('Airtable Requests', $description, DOKU_INC . 'lib/plugins/airtable/config.conf');
            $event->data[] = $config;
        }
    }

    /*public function handle_action_act_preprocess(Doku_Event $event, $param) {
    }*/

}

