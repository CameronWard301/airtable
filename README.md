# Airtable Dokuwiki Sync

A Dokuwiki plugin to sync data from airtable

## Prerequisites:

* Create an airtbale account and get an API key: https://airtable.com/api - also see their documentation on how to
  create a query
* Make sure your account is **READ ONLY**

## Installation

* Place the airtable folder inside your Dokuwiki plugin directory:
  DOKUWIKI_ROOT/lib/plugins
* Set your **Base ID** and **API Key** using Dokuwiki's [configuration Manager](https://www.dokuwiki.org/plugin:config)

## Usage:

Use the following syntax on any dokuwiki page. (Currently only image sync is working)  
Required Parameters:

* `type: ` - `Values: img, table, text` This sets the display mode
* `table: ` - The table you wish to pull data from.
  * You can find the table id by visiting your base and copying it from the url.
  * E.g. https://airtable.com/tblQeRuyF7dZuuOLr/viwY9EwnBsF9dWsPt?blocks=hide the table ID here is: `tblQeRuyF7dZuuOLr`


* `where:` -  [formula](https://support.airtable.com/hc/en-us/articles/203255215-Formula-Field-Reference) used to filter
  the results

### Images:

`<airtable>type: image, table: TABLE_NAME, where: QUERY_PARAM</airtable>`  
Optional Parameters:  
`image-size: ` - `Values: small, large, full` - The size of the image to appear on the page (large is default)   
`alt-tag: ` - Image description. [How to write a good alt tag](https://moz.com/learn/seo/alt-text)

#### Example:

`<airtable>Type: Image, Table: tblwWxohDeMeAAzdW, WHERE: {Ref #} = 19, image-size: small, alt-tag: marble-machine-x</airtable>`  
This would display a small image from the specified table where the reference id for the field = 19. It would also set
the images alt tag to: "marble-machine-x"
