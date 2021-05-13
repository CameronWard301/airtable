<?php
/**
 * DokuWiki Plugin airtable (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
}

use splitbrain\phpcli\Options;

class cli_plugin_airtable extends DokuWiki_CLI_Plugin {
    private $setup;

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     *
     * @return void
     */
    protected function setup(Options $options) {
        $options->setHelp('Provides a way to interact with airtable using a base_ID, a query string followed by your api_Key');

        // main arguments
        $options->registerArgument('base_ID', 'base ID for an airtable', 'true');
        $options->registerArgument("query", "airtable query", "true");
        $options->registerArgument('api_Key', 'airtable API Key', 'true');
        $options->registerArgument('page_Name', 'The file name for the page to write to', 'true');

        // options
        // $options->registerOption('FIXME:longOptionName', 'FIXME: helptext for option', 'FIXME: optional shortkey', 'FIXME:needs argument? true|false', 'FIXME:if applies only to subcommand: subcommandName');

        // sub-commands and their arguments
        // $options->registerCommand('FIXME:subcommandName', 'FIXME:subcommand description');
        // $options->registerArgument('FIXME:subcommandArgumentName', 'FIXME:subcommand-argument description', 'FIXME:required? true|false', 'FIXME:subcommandName');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     *
     * @return void
     */
    protected function main(Options $options) {
        // $command = $options->getCmd()
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        $arguments = $options->getArgs();
        $theFile = fopen(DOKU_INC . 'data/pages/' . $arguments[3], "w") or die("can't open: " . DOKU_INC . "data/pages/" . $arguments[3] . " file");
        fclose($theFile);

        //curl https://api.airtable.com/v0/appZGFwgzjqeMwdqy/%F0%9F%8D%B4%20Collaborators \ -H "Authorization: Bearer keydCHnFFjxbYtkPN

        $url      = 'https://api.airtable.com/v0/' . $arguments[0] . '/' . $arguments[1];
        $settings = array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $arguments[2]
            )
        );
        $context  = stream_context_create($settings);
        $response = file_get_contents($url, false, $context);

        if($http_response_header[0] != "HTTP/1.1 200 OK") { // if invalid return error:
            echo($http_response_header[0] . " for URL: " . $url);
            die();
        }

        $output = json_decode($response);

        $theFile = fopen(DOKU_INC . 'data/pages/' . $arguments[3], "a") or die("can't open: " . DOKU_INC . "data/pages/" . $arguments[3] . " file");

        $count = 1;

        foreach($output->records as $record) {
            $text = "\n\n======Item: " . $count . "======\n\n";
            $keys = (array) $record->fields;

            foreach($record->fields as $field) {
                if(is_object($field)) {
                    $data = "";
                    foreach($field as $key => $item) {
                        $data .= $key . ": " . $item . "\n";
                    }
                    $text .= $data;
                    continue;
                }
                if(is_array($field)) {
                    continue;
                }

                $encoded_Field_Name = htmlspecialchars("\n==" . array_search($field, $keys) . ":==\n");
                $encoded_Field      = str_replace(array("\xe2\x80\xa8", "\n//", "// "), "", $field); //remove the   character from the airtable data
                //$encoded_Field      = str_replace(array("\xe2\x80\xa8"), "", $field); //remove the   character from the airtable data
                $text .= $encoded_Field_Name . $encoded_Field . "\n\n";

            }

            fwrite($theFile, $text);
            $count++;
        }

        echo $response;
    }

}

