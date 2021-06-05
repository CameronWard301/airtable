<?php
/**
 * Default settings for the airtable plugin
 *
 * @author Cameron Ward <cameronward007@gmail.com>
 */

$meta['Base_ID']     = array('string');
$meta['API_Key']     = array('string');
$meta['Max_Records'] = array('numeric', '_pattern' => '/^[0-9]+$/'); //only accept numbers