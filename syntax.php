<?php
/**
 * Plugin Airtable: Syncs Airtable Content to dokuWiki
 *
 * Syntax: <airtable>TYPE: xxx, TABLE: xxx, WHERE, .......</airtable> - will be replaced with airtable content
 *
 * @license    GPL 3 (https://www.gnu.org/licenses/quick-guide-gplv3.html)
 * @author     Cameron Ward <cameronward007@gmail.com>
 */
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

class InvalidAirtableString extends Exception {
    public function errorMessage(): string {
        return $this->getMessage();
    }
}

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_airtable extends DokuWiki_Syntax_Plugin {

    /**
     * Get the type of syntax this plugin defines.
     *
     * @param
     * @return String <tt>'substition'</tt> (i.e. 'substitution').
     * @public
     * @static
     */
    function getType(): string {
        return 'substition';
    }

    /**
     * What kind of syntax do we allow (optional)
     */
//    function getAllowedTypes() {
//        return array();
//    }

    /**
     * Define how this plugin is handled regarding paragraphs.
     *
     * <p>
     * This method is important for correct XHTML nesting. It returns
     * one of the following values:
     * </p>
     * <dl>
     * <dt>normal</dt><dd>The plugin can be used inside paragraphs.</dd>
     * <dt>block</dt><dd>Open paragraphs need to be closed before
     * plugin output.</dd>
     * <dt>stack</dt><dd>Special case: Plugin wraps other paragraphs.</dd>
     * </dl>
     * @param
     * @return String <tt>'block'</tt>.
     * @public
     * @static
     */
    function getPType(): string {
        return 'block';
    }

    /**
     * Where to sort in?
     *
     * @param
     * @return Integer <tt>6</tt>.
     * @public
     * @static
     */
    function getSort(): int {
        return 1; //
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param $mode //The desired rendermode.
     * @return void
     * @public
     * @see render()
     */
    function connectTo($mode) {
        //$this->Lexer->addSpecialPattern('\[NOW\]',$mode,'plugin_airtable');
        $this->Lexer->addEntryPattern('<airtable>', $mode, 'plugin_airtable');
    }

    function postConnect() {
        $this->Lexer->addExitPattern('</airtable>', 'plugin_airtable');
    }

    /**
     * Handler to prepare matched data for the rendering process.
     *
     * <p>
     * The <tt>$aState</tt> parameter gives the type of pattern
     * which triggered the call to this method:
     * </p>
     * <dl>
     * <dt>DOKU_LEXER_ENTER</dt>
     * <dd>a pattern set by <tt>addEntryPattern()</tt></dd>
     * <dt>DOKU_LEXER_MATCHED</dt>
     * <dd>a pattern set by <tt>addPattern()</tt></dd>
     * <dt>DOKU_LEXER_EXIT</dt>
     * <dd> a pattern set by <tt>addExitPattern()</tt></dd>
     * <dt>DOKU_LEXER_SPECIAL</dt>
     * <dd>a pattern set by <tt>addSpecialPattern()</tt></dd>
     * <dt>DOKU_LEXER_UNMATCHED</dt>
     * <dd>ordinary text encountered within the plugin's syntax mode
     * which doesn't match any pattern.</dd>
     * </dl>
     * @param $match   //String The text matched by the patterns.
     * @param $state   //Integer The lexer state for the match.
     * @param $pos     //Integer The character position of the matched text.
     * @param $handler //Object Reference to the Doku_Handler object.
     * @return array The current lexer state for the match.
     * @public
     * @see render()
     * @static
     */
    function handle($match, $state, $pos, $handler): array {
        switch($state) {
            case DOKU_LEXER_EXIT:
            case DOKU_LEXER_ENTER :
                /** @var array $data */
                $data = array();
                return $data;

            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_MATCHED :
                break;

            case DOKU_LEXER_UNMATCHED :
                return array('airtable' => $match);

        }
        $data = array();
        return $data;
    }

    /**
     * Handle the actual output creation.
     *
     * <p>
     * The method checks for the given <tt>$aFormat</tt> and returns
     * <tt>FALSE</tt> when a format isn't supported. <tt>$aRenderer</tt>
     * contains a reference to the renderer object which is currently
     * handling the rendering. The contents of <tt>$aData</tt> is the
     * return value of the <tt>handle()</tt> method.
     * </p>
     * @param $mode      //String The output format to generate.
     * @param $renderer  Doku_Renderer A reference to the renderer object.
     * @param $data      //Array The data created by the <tt>handle()</tt>
     *                   method.
     * @return Boolean <tt>TRUE</tt> if rendered successfully, or
     *                   <tt>FALSE</tt> otherwise.
     * @public
     * @see handle()
     */
    function render($mode, Doku_Renderer $renderer, $data): bool {
        if($mode != 'xhtml') return false;

        if(!empty($data['airtable'])) {

            define('BASE_ID', $this->getConf('Base_ID'));
            define('API_KEY', $this->getConf('API_Key'));
            try {
                $user_string  = $data['airtable'];
                $display_type = $this->getDisplayType($user_string); //check type is set correctly
                switch(true) { //parse string based on type set
                    case ($display_type == "img"):
                        $decoded_array = $this->parseImageString($user_string);
                        $query_string  = ($decoded_array['table'] . '?filterByFormula=' . urlencode($decoded_array['where']));
                        $request       = json_decode($this->sendRequest($query_string), true);
                        $thumbnails    = $this->parseImageRequest($request);
                        if($thumbnails === false or $thumbnails === null) {
                            throw new InvalidAirtableString("Unknown 'parseImageRequest' error");
                        }
                        $html_tag      = '
                        <a href="' . $thumbnails['full']['url'] . '" target="_blank" rel="noopener">
                            <img alt ="' . $decoded_array['alt-tag'] . '" src="' . $thumbnails[$decoded_array['image-size']]['url'] . '">
                        </a>';
                        $renderer->doc .= $html_tag;
                        break;
                    default:
                        return true;
                }

            } catch(InvalidAirtableString $e) {
                $renderer->doc .= "<p style='color: red; font-weight: bold;'>Airtable Error: " . $e->getMessage() . "</p>";
                return true;
            }
            //return true;
        }

        return false;

    }

    /**
     * parse query string and return the type
     *
     * @param $user_string //data between airtable tags e.g.: <airtable>user_string</airtable>
     * @return string //the display type (image, table, text)
     * @throws InvalidAirtableString
     */
    private function getDisplayType($user_string): string {
        $type = substr($user_string, 0, strpos($user_string, ","));
        if($type == "") {
            throw new InvalidAirtableString("Missing Type Parameter / Not Enough Parameters");
        }
        $decoded_type = explode("type: ", strtolower($type))[1];
        if($decoded_type == null) {
            throw new InvalidAirtableString("Missing Type Parameter");
        }
        $decoded_type   = strtolower($decoded_type);
        $accepted_types = array("img", "image", "picture", "text", "txt", "table", "tbl");
        if(!array_search($decoded_type, $accepted_types)) {
            throw new InvalidAirtableString(
                "Invalid Type Parameter: " . htmlspecialchars($decoded_type) . "
            <br>Accepted Types: " . implode(" | ", $accepted_types)
            );
        }
        if($decoded_type == "img" || $decoded_type == "image" || $decoded_type == "picture") {
            $decoded_type = "img";
        }
        return $decoded_type;
    }

    /**
     * @param $user_string
     * @return array
     * @throws InvalidAirtableString
     */
    private function parseImageString($user_string): array {
        $image_parameter_types  = array("type" => true, "table" => true, "where" => true, "alt-tag" => "", "image-size" => "large"); // accepted parameter names with default values or true if parameter is required.
        $image_parameter_values = array("image-size" => ["", "small", "large", "full"]); // can be empty (substitute default), small, large, full
        $image_query            = $this->getParameters($user_string);
        return $this->checkParameters($image_query, $image_parameter_types, $image_parameter_values);
    }

    /**
     * Splits the query string into an associative array of Type => Value pairs
     *
     * @param $user_string string The user's airtable query
     * @return array
     */
    private function getParameters(string $user_string): array {
        $query        = array();
        $string_array = explode(", ", $user_string);
        foreach($string_array as $item) {
            $parameter                        = explode(": ", $item);
            $query[strtolower($parameter[0])] = $parameter[1]; //creates key value pairs for parameters e.g. [type] = "image"
        }
        return $query;
    }

    /**
     * Checks query parameters to make sure:
     *      Required parameters are present
     *      Missing parameters are substituted with default params
     *      Parameter values match expected values
     *
     * @param $query_array         array
     * @param $required_parameters array
     * @param $parameter_values    array
     * @return array // query array with added default parameters
     * @throws InvalidAirtableString
     */
    private function checkParameters(array &$query_array, array $required_parameters, array $parameter_values): array {
        foreach($required_parameters as $key => $value) {
            if(!array_key_exists($key, $query_array)) { // if parameter is missing:
                if($value === true) { // check if parameter is required
                    throw new InvalidAirtableString("Missing Parameter: " . $key);
                }
                $query_array[$key] = $value; // substitute default
            }
            if(($query_array[$key] == null || $query_array[$key] === "") && $value === true) { //if parameter is required but value is not present
                throw new InvalidAirtableString("Missing Parameter Value for: '" . $key . "'.");
            }
            if(array_key_exists($key, $parameter_values)) { //check accepted parameter_values array
                if(!in_array($query_array[$key], $parameter_values[$key])) { //if parameter value is not accepted:
                    $message = "Invalid Parameter Value: '" . htmlspecialchars($query_array[$key]) . "' for Key: '" . $key . "'.
                    <br>Possible values: " . implode(" | ", $parameter_values[$key]);
                    if(in_array("", $parameter_values[$key])) {
                        $message .= " or ''";
                    }
                    throw new InvalidAirtableString($message);
                }
            }
        }
        return $query_array;
    }

    /**
     * Method to send an airtable API request
     *
     * @param $request
     * @return false|string //JSON String
     * @throws InvalidAirtableString
     */
    private function sendRequest($request) {
        $url      = 'https://api.airtable.com/v0/' . BASE_ID . '/' . $request;
        $settings = array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . API_KEY
            )
        );
        $context  = stream_context_create($settings);
        $response = @file_get_contents($url, false, $context);

        if($http_response_header[0] != "HTTP/1.1 200 OK") { // if invalid request, return error:
            throw new InvalidAirtableString(
                $http_response_header[0] . " for URL: " . htmlspecialchars($url) .
                '<br>Try checking the table name and where parameters'
            );
        }
        return $response;
    }

    /**
     * Recursive method to find an array(needle) within the JSON response (haystack
     *
     * @param        $haystack
     * @param string $needle
     * @return false|array
     */
    private function parseImageRequest($haystack, $needle = "thumbnails") {
        foreach($haystack as $key) {
            if(is_array($key)) {
                if(array_key_exists($needle, $key)) {
                    return $key[$needle];
                }
                return $this->parseImageRequest($key, $needle);
            }
        }
        return false;
    }

}