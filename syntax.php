<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection DuplicatedCode */
/**
 * Plugin Airtable: Syncs Airtable Content to dokuWiki
 *
 * Syntax: <airtable>TYPE: xxx, TABLE: xxx, WHERE, .......</airtable> - will be replaced with airtable content
 *
 * @license    GPL 3 (https://www.gnu.org/licenses/quick-guide-gplv3.html)
 * @author     Cameron Ward <cameronward007@gmail.com>
 */
// must be run within DokuWiki
if(!defined('DOKU_INC')) {
    die();
}

/**
 * Class InvalidAirtableString
 *
 * Handles the airtable query string exception and
 *
 */
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
        return 'normal';
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
        return 1;
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
        $this->Lexer->addEntryPattern('{{airtable>', $mode, 'plugin_airtable');
    }

    function postConnect() {
        $this->Lexer->addExitPattern('}}', 'plugin_airtable');
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
            return array();

            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_MATCHED :
                break;
            case DOKU_LEXER_UNMATCHED :
                if(!empty($match)) {
                    try {
                        //get config options
                        define('BASE_ID', $this->getConf('Base_ID'));
                        define('API_KEY', $this->getConf('API_Key'));
                        define('MAX_RECORDS', $this->getConf('Max_Records'));
                        define('VALID_FIELD_TYPES', array('string', 'rating', 'multi_select', 'checkbox', 'url', 'attachment', 'linked_record'));
                        define('AIRTABLE_CACHE_TIME', $this->getConf('Airtable_Refresh'));
                        define(
                            'RATING_CHECKBOX_OPTIONS',
                            array(
                                'checkbox_checked'   => array('unicode' => '&#9745;', 'colour' => '#E52E4D'),
                                'checkbox_unchecked' => array('unicode' => '&#9744;', 'colour' => '#E52E4D'),
                                'tick'               => array('unicode' => '&#10004;', 'colour' => '#E52E4D'),
                                'star'               => array('unicode' => '&#9733;', 'colour' => '#E52E4D'),
                                'heart'              => array('unicode' => '&#10084;', 'colour' => '#E52E4D'),
                                'thumb'              => array('unicode' => '&#128077;', 'colour' => '#E52E4D'),
                                'flag'               => array('unicode' => '&#x2691;', 'colour' => '#E52E4D')
                            )
                        );

                        //validate config variables
                        if(empty(BASE_ID)) {
                            throw new InvalidAirtableString('Empty Base ID, set this in the configuration manager in the admin panel');
                        }
                        if(empty(API_KEY)) {
                            throw new InvalidAirtableString('Empty Airtable API Key, set this in the configuration manager in the admin panel');
                        }
                        if(empty(MAX_RECORDS)) {
                            throw new InvalidAirtableString('Empty MAX_RECORDS value, set this in the configuration manager in the admin panel');
                        }
                        if(empty(AIRTABLE_CACHE_TIME)) {
                            throw new InvalidAirtableString('Empty AIRTABLE_CACHE_TIME, set this in the configuration manager in the admin panel');
                        }

                        global $cacheHelper;
                        if(!($cacheHelper = $this->loadHelper('airtable_cacheInterface'))) {
                            throw new InvalidAirtableString('Could not load cache interface helper');
                        }

                        $user_string  = $match;
                        $display_type = $this->getDisplayType($user_string); //check type is set correctly
                        // MAIN PROGRAM:
                        switch(true) { //parse string based on type set
                            case ($display_type === "tbl"):
                                $parameter_array = $this->parseTableString($user_string);
                                $api_response    = $this->sendTableRequest($parameter_array);

                                if(count($api_response['records']) == 1 && $parameter_array['force-table'] == 'false') { //if query resulted in one record, render as a template:
                                    $html = $this->renderRecord($parameter_array, $api_response['records'][0]);
                                } else {
                                    $html = $this->renderTable($parameter_array, $api_response);
                                }
                                break;

                            case ($display_type === "record"):
                                $parameter_array = $this->parseRecordString($user_string);
                                $api_response    = $this->sendRecordRequest($parameter_array);
                                $html            = $this->renderRecord($parameter_array, $api_response);
                                break;
                            //return array('airtable_html' => $html);
                            case ($display_type === "attachment"):
                                $parameter_array = $this->parseImageString($user_string);
                                $api_response    = $this->sendRecordRequest($parameter_array);
                                $html            = $this->renderAttachments($parameter_array, $api_response);
                                break;
                            //return array('airtable_html' => $html);
                            case ($display_type === "txt"):
                                $parameter_array = $this->parseTextString($user_string);
                                $api_response    = $this->sendRecordRequest($parameter_array);
                                $html            = $this->renderText($parameter_array, $api_response);
                                break;
                            //return array('airtable_html' => $html);
                            default:
                                throw new InvalidAirtableString("Unknown Embed Type");
                        }
                        $cache_id = $this->cacheApiResponse($match, $api_response, $cacheHelper);
                        return array('airtable_html' => $html, 'cache_id' => $cache_id, 'request' => $parameter_array['request']); //return the html to render and the cache ID to be added to the pages metadata
                    } catch(InvalidAirtableString $e) {
                        $html = "<p style='color: red; font-weight: bold;'>Airtable Error: " . $e->getMessage() . "</p>";
                        return array('airtable_html' => $html);
                    }
                }
        }
        return array();
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
     * @see          handle()
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public
    function render($mode, Doku_Renderer $renderer, $data): bool {
        if($data === false) {
            return false;
        }
        if($mode == 'xhtml') {
            if(!empty($data['airtable_html'])) {
                $renderer->doc .= $data['airtable_html'];
                return true;
            } else {
                return false;
            }
        } elseif($mode == 'metadata') {
            if(!empty($data['cache_id']) && !empty($data['request'])) {
                /** @var Doku_Renderer_metadata $renderer */

                //erase persistent metadata tags that are no longer used
                if(isset($renderer->persistent['plugin']['airtable']['cache_ids'])) {
                    unset($renderer->persistent['plugin']['airtable']['cache_ids']);
                    $renderer->meta['plugin']['airtable']['cache_ids'] = array();
                }

                //merge with previous tags and make the values unique
                if(!isset($renderer->meta['plugin']['airtable']['cache_ids'])) {
                    $renderer->meta['plugin']['airtable']['cache_ids'] = array();
                }
                $renderer->meta['plugin']['airtable']['cache_ids'] = array_unique(array_merge($renderer->meta['plugin']['airtable']['cache_ids'], array($data['cache_id'] => $data['request'])));
            }
            return true;
        }
        return false;
    }

    /**
     * Method for rendering a table
     *
     * @param $parameter_array
     * @param $api_response
     * @return string
     * @throws InvalidAirtableString
     */
    private
    function renderTable($parameter_array, $api_response): string {
        $parameter_array['media-styles'] = 'min-width: 250px;';
        $html                            = '<div style="overflow-x: auto"><table class="airtable-table">';
        if($parameter_array['orientation'] == 'horizontal') {
            $html .= '<thead><tr>';
            foreach($parameter_array['fields'] as $field) {
                $html .= '<th>' . $field['name'] . '</th>';
            }
            $html .= '</thead></tr><tbody>';
            foreach($api_response['records'] as $record) {
                $html .= '<tr>';
                foreach($parameter_array['fields'] as $field) {
                    $parsed_field = $this->processAirtableFieldType($parameter_array, $field, $record['fields']);
                    $html         .= '<td>' . $parsed_field . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        } else {
            foreach($parameter_array['fields'] as $field) {
                $html .= '<tr><th>' . $field['name'] . '</th>';
                foreach($api_response['records'] as $record) {
                    $parsed_field = $this->processAirtableFieldType($parameter_array, $field, $record['fields']);
                    $html         .= '<td>' . $parsed_field . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table></div>';

        }
        return $html;
    }

    /**
     * Private Method for rendering a single record.
     * Fields and field data appear on the left. If there is an image present,
     * it will appear to the top right of the text
     *
     * @param $parameter_array
     * @param $api_response
     * @return string
     * @throws InvalidAirtableString
     */
    private
    function renderRecord($parameter_array, $api_response): string {
        $fields                          = $parameter_array['fields'];
        $html_start                      = '<div class="airtable-record">';
        $html                            = '';
        $parameter_array['media-styles'] = 'float: right; max-width: 350px; margin-left: 10px;';

        foreach($fields as $field) {
            $response_html = $this->processAirtableFieldType($parameter_array, $field, $api_response['fields']);
            if($response_html == false) {
                continue;
            }
            if($field['type'] == 'attachment') { //add attachment above other html elements
                $html = '
                <div>
                    ' . $response_html . '
                </div>' . $html;

            } else {
                $html .= '
            <div>
                <h3>' . $field['name'] . '</h3>
                <p>' . $response_html . '</p>
            </div>';
            }
        }
        $html = $html_start . $html;
        $html .= '<div style="clear: both;"></div>';
        $html .= '</div>'; //close html_start tag
        return $html;
    }

    /**
     * Private helper function to render attachments e.g. images, videos etc
     * @param $parameter_array
     * @param $api_response
     * @return string html code (typically an image or video tag)
     * @throws InvalidAirtableString
     */
    private function renderAttachments(&$parameter_array, $api_response): string {
        $parameter_array['media-styles'] = 'max-width: 250px;';
        $attachment_html                 = '';
        foreach($parameter_array['fields'] as $field) {
            $field['type']   = 'attachment';
            $attachment_html .= $this->processAirtableFieldType($parameter_array, $field, $api_response['fields']);
        }
        return $attachment_html;
    }

    /**
     * Generates HTML for rendering a single image:
     *
     * @param        $data
     * @param        $image
     * @param string $image_styles
     * @return string
     * @throws InvalidAirtableString
     */
    private
    function renderImage($data, $image, $image_styles = ""): string {
        if(!key_exists('thumbnails', $image)) {
            throw new InvalidAirtableString('Could not find thumbnails in image query');
        }
        if($data['media-position'] == "centre") {
            $position = "mediacenter";
        } elseif($data['media-position'] == 'right') {
            $position = "mediaright";
        } elseif($data['media-position'] == "left") {
            $position = "medialeft";
        } else {
            $position = '';
        }

        if(!key_exists('image-size', $data)) {
            $data['image-size'] = 'large';
        }
        return '
        <div>
            <a href="' . htmlspecialchars($image['thumbnails']['full']['url']) . '" target="_blank" rel="noopener" title="' . htmlspecialchars($data["attachment-title"]) . '">
                <img alt ="' . htmlspecialchars($data['alt-tag']) . '" src="' . htmlspecialchars($image['thumbnails'][$data['image-size']]['url']) . '" style="' . $image_styles . ' ' . '" class="airtable-image ' . $position . '">
            </a>
        </div>';
    }

    /**
     * Private method for rendering text.
     *
     * @param $parameter_array
     * @param $api_response
     * @return string
     * @throws InvalidAirtableString
     */
    private
    function renderText($parameter_array, $api_response): string {
        $fields = $parameter_array['fields'];
        $html   = '';
        foreach($fields as $field) {
            $response_html = $this->processAirtableFieldType($parameter_array, $field, $api_response['fields']);
            $html          .= $response_html . ' ';
        }
        return rtrim($html);
    }

    /**
     * Method that chooses the correct rendering type for the given media:
     *
     * @param        $data
     * @param        $media
     * @return string
     * @throws InvalidAirtableString
     */
    private
    function renderMedia($data, $media): string {
        if(!array_key_exists('media-styles', $data)) {
            $data['media-styles'] = '';
        }
        $type = $media['type'];
        if($type == 'image/jpeg' || $type == 'image/jpg' || $type == 'image/png') {
            return $this->renderImage($data, $media, $data['media-styles']);
        }
        if($type == 'video/mp4' || $type == 'video/quicktime') {
            return $this->renderVideo($media, $data['media-styles']);
        }
        throw new InvalidAirtableString("Unknown media type or invalid attachment index: " . htmlspecialchars($type));
    }

    /**
     * Generates HTML for rendering a video
     *
     * @param $video
     * @param $video_styles
     * @return string
     */
    private
    function renderVideo($video, $video_styles): string {
        return '<video controls class="airtable-video" style="' . htmlspecialchars($video_styles) . '"><source src="' . htmlspecialchars($video["url"]) . '" type="video/mp4"></video>';
    }

    /**
     * Private function that checks the type of the field parameter the user expected vs what the airtable API returned
     * Function returns relevant html code based on the type set e.g. a checked tick box if the api returns such.
     *
     * @param $parameter_array
     * @param $user_field
     * @param $api_response_fields
     * @return string
     * @throws InvalidAirtableString
     */
    public function processAirtableFieldType($parameter_array, $user_field, $api_response_fields): string {
        $type = $user_field['type'];

        if(!array_key_exists($user_field['name'], $api_response_fields) && $type !== 'checkbox') { //if field is not present in array:
            return false;
        }
        $response_field = $api_response_fields[$user_field['name']];

        switch(true) {

            case ($type === 'string'):
                if(array_key_exists('option', $user_field)) {
                    throw new InvalidAirtableString("Invalid option for String for field: ", htmlspecialchars($user_field['name']));
                }

                if(!is_string($response_field) && !is_int($response_field)) {
                    $html = $this->renderAnyExternalLinks(htmlspecialchars(json_encode($response_field)));  //convert to string if element in response is not already a string
                    return str_replace("\n", "<br>", $html); //replace new lines with line break
                }
                $html = $this->renderAnyExternalLinks(htmlspecialchars($response_field));
                return str_replace("\n", "<br>", $html); //replace new lines with line break

            case($type === 'checkbox'):
                $symbol    = RATING_CHECKBOX_OPTIONS['checkbox_unchecked']['unicode'];
                $colour    = 'color: ' . RATING_CHECKBOX_OPTIONS['checkbox_unchecked']['colour'] . ';';
                $font_size = 'font-size: 26px;';
                if(array_key_exists('option', $user_field)) {
                    $checkbox_options = explode(',', $user_field['option']);
                    if(!key_exists($checkbox_options[0], RATING_CHECKBOX_OPTIONS)) { //check symbol type is allowed
                        throw new InvalidAirtableString('Invalid option for type rating for option: ' . htmlspecialchars($checkbox_options[0]) . '<br>Please use the following option types: ' . json_encode(array_keys(RATING_CHECKBOX_OPTIONS)));
                    }
                    //override defaults:
                    if(array_key_exists(1, $checkbox_options)) {
                        $colour = 'color: ' . htmlspecialchars($checkbox_options[1]) . ';';
                    }
                    if(array_key_exists(2, $checkbox_options)) {
                        $font_size = 'font-size: ' . htmlspecialchars($checkbox_options[2]) . ';';
                    }
                    if($response_field != null) {
                        $symbol = RATING_CHECKBOX_OPTIONS[$checkbox_options[0]]['unicode'];
                    }
                    return '<span style="' . $colour . ' ' . $font_size . '">' . $symbol . '</span>';

                }
                if($response_field == true) { //show checked checkbox
                    $symbol = RATING_CHECKBOX_OPTIONS['checkbox_checked']['unicode'];
                    return '<span style="' . $colour . ' ' . $font_size . '">' . $symbol . '</span>';
                }
                return '<span style="' . $colour . ' ' . $font_size . '">' . $symbol . '</span>';

            case($type === 'rating'):
                $symbol    = RATING_CHECKBOX_OPTIONS['star']['unicode'];
                $colour    = 'color: ' . RATING_CHECKBOX_OPTIONS['star']['colour'] . ';';
                $font_size = 'font-size: 26px;';
                if(array_key_exists('option', $user_field)) {
                    $rating_options = explode(',', $user_field['option']);
                    if(!key_exists($rating_options[0], RATING_CHECKBOX_OPTIONS)) { //check symbol type is allowed
                        throw new InvalidAirtableString('Invalid option for type rating for option: ' . htmlspecialchars($rating_options[0]) . '<br>Please use the following option types: ' . json_encode(array_keys(RATING_CHECKBOX_OPTIONS)));
                    }
                    //override defaults:
                    $symbol = RATING_CHECKBOX_OPTIONS[$rating_options[0]]['unicode'];
                    if(array_key_exists(1, $rating_options)) {
                        $colour = 'color: ' . htmlspecialchars($rating_options[1]) . ';';
                    }
                    if(array_key_exists(2, $rating_options)) {
                        $font_size = 'font-size: ' . htmlspecialchars($rating_options[2]) . ';';
                    }
                }
                return str_repeat('<span style="' . $colour . ' ' . $font_size . '">' . $symbol . '</span>', htmlspecialchars($response_field));

            case($type === 'url'):
                if(array_key_exists('option', $user_field)) {
                    $label = $user_field['option'];
                } else {
                    $label = null;
                }
                return $this->renderAnyExternalLinks($response_field, $label);

            case($type === 'attachment'):
                $attachment_html = '';
                if(array_key_exists('index', $user_field)) { //if the user is requesting specific images related to a record:
                    $index = intval($user_field['index'][1]); //get the index e.g. [1]
                    if($index < 0) {
                        $index = count($response_field) + $index; //e.g. [-1] returns the last item in the list
                    }
                    if(array_key_exists('2', $user_field['index'])) { //if the user is requesting a range of images
                        $offset = intval($user_field['index'][2]);
                        if($offset < 0) {
                            $offset = count($response_field) + $offset;
                        }
                        if($offset == 0) {
                            $offset = 1;
                        }
                        $response_field = array_slice($response_field, $index, $offset); //get array of selected images
                    } else {
                        return $this->renderMedia($parameter_array, $response_field[$index]); //renders a single image specified by the user
                    }
                }
                foreach($response_field as $attachment) { //renders all or a range of images
                    $attachment_html .= $this->renderMedia($parameter_array, $attachment);

                }
                return $attachment_html;
            case ($type === 'linked_record'):
                if(!array_key_exists('option', $user_field)) {
                    throw new InvalidAirtableString('Please provide the table ID and Field name to display the linked record: ' . htmlspecialchars($user_field['name']));
                }
                preg_match_all('/\'[A-Za-z 0-9()#]+\'/m', $user_field['option'], $link_fields);
                if(count($link_fields[0]) !== 1) {
                    throw new InvalidAirtableString('Please provide one field as an optional parameter for linked records.');
                }
                $link_field = $link_fields[0][0];
                $table_ID   = explode(',', $user_field['option'])[0]; //extract the linked table ID
                $request    = $table_ID . '?';
                $request    .= urlencode('fields[]') . '=' . urlencode(trim($link_field, "'")); //don't add a '&' for the first field
                $request    .= '&filterByFormula=SEARCH(RECORD_ID()' . urlencode(', "');
                foreach($response_field as $index => $record_ID) {
                    if($index == array_key_last($response_field)) {
                        $request .= urlencode($record_ID) . urlencode(',');
                    }
                    $request .= urlencode($record_ID);
                }
                $request .= urlencode('") != ""');
                global $cacheHelper;
                $LR_response = $this->checkAPIResponse($cacheHelper->sendRequest($request));
                $html        = '';
                foreach($LR_response['records'] as $record) {
                    $link_label = $record['fields'][trim($link_field, "'")];
                    if($link_label == null) {
                        continue;
                    }
                    if(!is_string($link_label) && !is_int($link_label)) {
                        throw new InvalidAirtableString("The field: " . htmlspecialchars($link_field) . " contains non String items, please choose another field");
                    }
                    $link_url = 'https://airtable.com/' . $table_ID . '/' . $record['id'];
                    $html     .= $this->renderAnyExternalLinks($link_url, $link_label) . '<br>';
                }
                return $html;
            case ($type === 'multi_select'):
                $html = '';
                foreach($response_field as $field) {
                    $html .= htmlspecialchars($field) . '<br>';
                }
                return rtrim($html, '<br>');

            default:
                throw new InvalidAirtableString('Unknown type specified for field: ' . htmlspecialchars($user_field['name']) . '. With type: ' . htmlspecialchars($type));
        }
    }

    /**
     * Sets the required parameters for type: table
     *
     * @param $user_string
     * @return array
     * @throws InvalidAirtableString
     */
    private
    function parseTableString($user_string): array {
        $table_parameter_types  = array("display" => true, "table" => true, "fields" => true, "record-url" => true, "where" => "", "order-by" => "", "order" => "asc", "max-records" => "", "force-table" => "false", "orientation" => "horizontal", "attachment-title" => "");
        $table_parameter_values = array("order" => ["asc", "desc"], "force-table" => ["true", "false"], "orientation" => ["horizontal", "vertical"]);
        $table_query            = $this->decodeRecordURL($this->getParameters($user_string));
        return $this->checkRequestParameters($table_query, $table_parameter_types, $table_parameter_values);
    }

    /**
     * Sets the required parameters for type: record
     * @param $user_string
     * @return array
     * @throws InvalidAirtableString
     */
    private
    function parseRecordString($user_string): array {
        $record_parameter_types  = array("display" => true, "record-url" => true, "table" => true, "fields" => true, "record-id" => true, "alt-tag" => "", "attachment-title" => "");
        $record_parameter_values = array();
        $record_query            = $this->decodeRecordURL($this->getParameters($user_string));
        return $this->checkRequestParameters($record_query, $record_parameter_types, $record_parameter_values);
    }

    /**
     * Sets the required parameters for type: image
     * Also sets accepted values for specific parameters
     *
     * @param $user_string
     * @return array The decoded string with the parameter names stored as keys
     * @throws InvalidAirtableString
     */
    private
    function parseImageString($user_string): array {
        $image_parameter_types  = array("display" => true, "record-url" => true, 'table' => true, 'fields' => true, 'record-id' => true, "alt-tag" => "", "image-size" => "large", "media-position" => "block", "attachment-title" => ""); // accepted parameter names with default values or true if parameter is required.
        $image_parameter_values = array("image-size" => ["", "small", "large", "full"], "media-position" => ['', 'left', 'centre', 'right', 'block']); // can be empty (substitute default), small, large, full
        $image_query            = $this->decodeRecordURL($this->getParameters($user_string));
        return $this->checkRequestParameters($image_query, $image_parameter_types, $image_parameter_values);
    }

    /**
     * Sets the required parameters for type: text
     *
     * @param $user_string
     * @return array
     * @throws InvalidAirtableString
     */
    private
    function parseTextString($user_string): array {
        $text_parameter_types  = array("display" => true, "table" => true, "fields" => true, "record-id" => true, "record-url" => true);
        $text_parameter_values = array();
        $text_query            = $this->decodeRecordURL($this->getParameters($user_string));
        return $this->checkRequestParameters($text_query, $text_parameter_types, $text_parameter_values);
    }

    /**
     * parse query string and return the type
     *
     * @param $user_string //data between airtable tags e.g.: <airtable>user_string</airtable>
     * @return string //the display type (image, table, text)
     * @throws InvalidAirtableString
     */
    private
    function getDisplayType($user_string): string {
        $display_type = substr($user_string, 0, strpos($user_string, " | "));
        if($display_type == "") {
            throw new InvalidAirtableString("Missing Display Parameter / Not Enough Parameters");
        }
        $decoded_string       = explode("display: ", strtolower($display_type))[1];
        $decoded_display_type = str_replace('"', '', $decoded_string);
        if($decoded_display_type == null) {
            throw new InvalidAirtableString("Missing Display Parameter");
        }
        $decoded_display_type   = strtolower($decoded_display_type);
        $accepted_display_types = array("img", "image", "picture", "text", "txt", "table", "tbl", "record", "attachment", "media");
        if(array_search($decoded_display_type, $accepted_display_types) === false) {
            throw new InvalidAirtableString(
                "Invalid Type Parameter: " . htmlspecialchars($decoded_display_type) . "
            <br>Accepted Types: " . implode(" | ", $accepted_display_types)
            );
        }
        //Set to a standard type:
        if($decoded_display_type == "img" || $decoded_display_type == "image" || $decoded_display_type == "picture" || $decoded_display_type == "attachment" || $decoded_display_type == "media") {
            $decoded_display_type = "attachment";
        }
        if($decoded_display_type == "text") {
            $decoded_display_type = "txt";
        }
        if($decoded_display_type == "table") {
            $decoded_display_type = "tbl";
        }
        return $decoded_display_type;
    }

    /**
     * Splits the query string into an associative array of Type => Value pairs
     *
     * @param $user_string string The user's airtable query
     * @return array
     * @throws InvalidAirtableString
     */
    private
    function getParameters(string $user_string): array {
        $query           = array();
        $query['fields'] = array();
        $string_array    = explode(' | ', trim(preg_replace('/\s+/', ' ', $user_string)));
        foreach($string_array as $item) {
            $parameter = explode(": ", $item); //creates key value pairs for parameters e.g. [type] = "image"
            if(strtolower($parameter[0]) === "fields") { // separate field names into an array if it exists
                $this->parseFields($parameter[1], $query); //split up the field string into field names, types and options
            } else {
                $query[strtolower($parameter[0])] = trim(str_replace('"', '', $parameter[1]));
            }
        }

        return $query;
    }

    /**
     * Private function that splits the fields entered by a user up into 3 parts: name, type and options
     * These are used later when decoding the airtable API response to render the correct field
     *
     * @param $field_string
     * @param $query
     * @throws InvalidAirtableString
     */
    private function parseFields($field_string, &$query) {
        preg_match_all('/".+?"(?:@[a-zA-Z_]+(?:\[[a-zA-Z\-:0-9 ,_()#\']+])?)?/m', $field_string, $matches);//Finds field and type pairs
        foreach($matches[0] as $match) {
            $field = array();
            if(strpos($match, '"@') === false || !strpos($match, '" @') === false) { //No type set, treat field as a String
                $field['name'] = str_replace('"', '', $match);
                $field['type'] = 'string';
            } else { //Process the type set by user
                $items         = explode('@', $match);
                $field['name'] = trim(str_replace('"', '', $items[0]));
                if(preg_match('/\[.+?](?:@[a-zA-Z0-9,]+)?/m', $items[1], $option)) { //if there is also an option set
                    $field['type'] = strtolower(str_replace($option[0], '', $items[1]));
                    $clean_option  = preg_replace('/[\[\]]/m', '', $option); //split out the option part e.g.[star]
                    preg_match('/(-?[1-9]?[0-9]*)(?::(-?[1-9]?[0-9]+)|:?([0-9]+))?/', $clean_option[0], $matches);
                    if($matches[0] !== "") {
                        $field['index'] = $matches;
                    } else {
                        $field['option'] = $clean_option[0];
                    }
                } else {
                    $field['type'] = strtolower($items[1]);
                    if(!in_array($field['type'], VALID_FIELD_TYPES)) {
                        throw new InvalidAirtableString('Invalid field type for: ' . htmlspecialchars($field['type']) . '<br>Please choose from one of the valid types: ' . json_encode(VALID_FIELD_TYPES));
                    }
                }
            }
            array_push($query['fields'], $field);
        }
    }

    /**
     * Extracts the table, view and record ID's from record-url
     *
     * @param $query
     * @return mixed
     * @throws InvalidAirtableString
     */
    private
    function decodeRecordURL($query) {
        if(array_key_exists("record-url", $query)) {
            //// "tbl\w+|viw\w+|rec\w+/ig" One line preg match?
            preg_match("/tbl\w+/i", $query["record-url"], $table); //extract table, view, record from url
            preg_match("/viw\w+/i", $query["record-url"], $view);
            preg_match("/rec\w+/i", $query["record-url"], $record_id);

            $query['table']     = urlencode($table[0]); //url encode each part
            $query['view']      = urlencode($view[0]);
            $query['record-id'] = urlencode($record_id[0]);
            return $query;
        } else {
            throw new InvalidAirtableString("Missing record-url parameter");
        }
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
    private
    function checkRequestParameters(array &$query_array, array $required_parameters, array $parameter_values): array {
        foreach($required_parameters as $key => $value) {
            if(!array_key_exists($key, $query_array) || empty($query_array[$key])) { // if parameter is missing:
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
     * Method for checking text and replacing links with <a> tags for external linking
     * If there are no links present, return the text with no modification
     *
     * @param      $string string the string to find links in
     * @param null $label  the label to use fir the url (optional)
     * @return string
     */
    private
    function renderAnyExternalLinks($string, $label = null): string {
        $regular_expression = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";

        if(preg_match_all($regular_expression, $string, $url_matches)) { // store all url matches in the $url array
            foreach($url_matches[0] as $link) {
                if(strstr($link, ':') === false) { //if link is missing http, add it to the front of the url
                    $url = 'http://' . $link;
                } else {
                    $url = $link;
                }
                $search = $link;
                parse_str(parse_url(urldecode($url))['query'], $url_parameters); //get the url parameters
                if($label === null) {
                    if(key_exists('amp;dw-link', $url_parameters)) { //if the user has provided a label within the url
                        $url_parameters['dw-link'] = $url_parameters['amp;dw-link']; //create standard label
                    }
                    if(key_exists('dw-link', $url_parameters)) {
                        $label = htmlspecialchars($url_parameters['dw-link']); //set label
                    } else {
                        $label = htmlspecialchars($url); //set url to be label
                    }
                }
                $replace = '<a href = "' . $url . '" title = "' . $link . '" target = "_blank" rel = "noopener" class = "urlextern">' . $label . '</a>';
                $string  = str_replace($search, $replace, $string);
            }
        }
        return $string;
    }

    /**
     * Method to encode a record request
     *
     * @param $data
     * @return false|string //JSON String
     * @throws InvalidAirtableString
     */
    public
    function sendRecordRequest(&$data) {
        $request         = $data['table'] . '/' . urlencode($data['record-id']);
        $data['request'] = $request;
        global $cacheHelper;
        return $this->checkAPIResponse($cacheHelper->sendRequest($request));
    }

    /**
     * Method to encode a table request
     *
     * @param $data
     * @return false|string
     * @throws InvalidAirtableString
     */
    public
    function sendTableRequest(&$data) {
        $request = $data['table'] . '?';
        //Add each field to the request string
        foreach($data['fields'] as $index => $field) {
            if($index >= 1) {
                $request .= '&' . urlencode('fields[]') . '=' . urlencode($field['name']);
            } else {
                $request .= urlencode('fields[]') . '=' . urlencode($field['name']); //don't add a '&' for the first field
            }
        }

        //add filter:
        if(key_exists('where', $data)) {
            if($data['where'] !== "") {
                $request .= '&filterByFormula=' . urlencode($data['where']);
            }
        }

        //Set max records:
        if(key_exists('max-records', $data)) {
            if($data['max-records'] !== "") {
                if((int) $data['max-records'] <= MAX_RECORDS) {
                    $max_records = $data['max-records'];
                } else {
                    $max_records = MAX_RECORDS;
                }
            } else {
                $max_records = MAX_RECORDS;
            }
        } else {
            $max_records = MAX_RECORDS;
        }
        $request .= '&maxRecords=' . $max_records;

        //set order by which field and order direction:
        if(key_exists('order', $data)) {
            $order = $data['order'];
        } else {
            $order = "asc";
        }

        if(key_exists('order-by', $data)) {
            if($data['order-by'] !== "") {
                $request .= '&' . urlencode('sort[0][field]') . '=' . urlencode($data['order-by']);
                $request .= '&' . urlencode('sort[0][direction]') . '=' . urlencode($order);
            }
        }
        $data['request'] = $request;//store to add to metadata later

        global $cacheHelper;
        return $this->checkAPIResponse($cacheHelper->sendRequest($request));
    }

    /**
     * Creates a unique cache ID from the user syntax string and the page ID
     * Returns the cache ID created
     * @param $airtable_string string e.g. {{airtable>display: "record" ....}}
     * @param $data            mixed the data returned from the airtable API
     * @param $cacheHelper     mixed the cacheHelper Object
     * @return string the ID of the cache file created
     */
    private function cacheApiResponse($airtable_string, $data, $cacheHelper): string {
        $cache_id = md5($_GET['id'] . '_' . $airtable_string);
        $cacheHelper->newCache($cache_id, $data, md5(time())); //cache the airtable request
        return $cache_id;
    }

    /**
     * Returns the API response if there is no error
     * Otherwise display the error to the user
     * @throws InvalidAirtableString
     */
    private function checkAPIResponse($response) {
        if(key($response) !== false) {
            return $response;
        } else {
            throw new InvalidAirtableString($response[key($response)]);
        }
    }
}