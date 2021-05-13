<?php
define("AIRTABLE_CONF", "config.conf");
define("DEBUG_ENABLED", false);
define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_INC . 'inc/utf8.php');
require_once(DOKU_INC . 'inc/pageutils.php');
//shell_exec('php bin/plugin.php airtable appZGFwgzjqeMwdqy Martin%20Requests?sort%5B0%5D%5Bfield%5D=Ticket%20%23 keydCHnFFjxbYtkPN start2.txt');

function log_debug($data) {
    $logFile = fopen("request_logs.txt", "a+") or die("airtable request log error, unable to open file");
    $log = date("Y/m/d h:i:sa") . "\n" . $data . "\n\n";
    fwrite($logFile, $log);
    fclose($logFile);
}

$configFile = fopen(AIRTABLE_CONF, "r") or die("Unable to open airtable plugin config.conf file");
$config = array();

try {
    while(!feof($configFile)) { // read until EOF
        $line = fgets($configFile); //read each line
        preg_match("/^(?P<key>\w+)\s+(?P<value>.*)/", $line, $matches); //split line to key: values
        if(isset($matches['key'])) {
            $params                  = explode(', ', $matches['value']);
            $config[$matches['key']] = $params;
        }
    }
} catch(Exception $e) {
    die("Airtable ConfigFile Processing Error: \n" . $e);
}

foreach($config as $request) {
    $command = "php " . DOKU_INC . "bin/plugin.php airtable";
    foreach($request as $param) {
        $command .= " " . $param;
    }
    $command .= " 2>&1";
    $output  = shell_exec($command);
    if(DEBUG_ENABLED) {
        if($output == null) {
            $output = "null Output";
        }
        log_debug("Command: " . $command . "\nResulted in: " . $output);
    }
}
//var_dump($config);

fclose($configFile);

/*$configFile = fopen(AIRTABLE_CONF, "r") or die("Unable to open airtable plugin config.conf file");
$data = fread($configFile, filesize(AIRTABLE_CONF));
$dataDecode = parse
*/
