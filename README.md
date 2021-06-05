# Airtable Dokuwiki Sync

A Dokuwiki plugin to sync data from airtable

## Prerequisites:

* Create an airtable account and get an API key: https://airtable.com/api - also see their documentation on how to
  create a query
* Make sure your account is **READ ONLY**

## Installation

* Place the airtable folder inside your Dokuwiki plugin directory:
  DOKUWIKI_ROOT/lib/plugins
* Set your **Base ID** and **API Key** using Dokuwiki's [configuration Manager](https://www.dokuwiki.org/plugin:config)
* set **Max Records** parameter - this will add a limit to the number of rows in a table that a user can generate

## Usage:

Use the following syntax on any dokuwiki page.
`{{airtable>type: "theType" | record-url: "theRecordURL"}}`

Each parameter: is followed by a space and values enclosed in "". Parameters are separated by ' | ' (note the importance
of spaces here)  
Multiple fields are separated by `,`

Required Parameters:

* `type: ` - This sets the display mode. Values:
  * `image`
  * `record`
  * `table`
  * `text`
* `record-url: ` - The table/record you wish to pull data from.
  * Within airtable, expand any record (by clicking on a field and pressing space) and copy the URL in the address bar.
  * For a table URL, simply select the required table and copy the URL from the address bar
  * E.g. https://airtable.com/tblQeRuyF7dZuuOLr/viwY9EwnBsF9dWsPt?blocks=hide

### Tables:

To display data from airtable in a table, use the following syntax:  
`{{airtable>type: "table" | record-url: "theURL" | fields: "field1", "field2" | where: "FORMULA" | order-by: "field_to_order_by" | order: "ORDER DIRECTION" | max-records: "X"}}`  
You must specify **at least one** field.

Optional Parameters:

* `where` -  [an airtable formula](https://support.airtable.com/hc/en-us/articles/203255215-Formula-Field-Reference)
  used to filter the results
* `order-by` - specifies how the records will be ordered.
* `order` - the direction of the order. Values:
  * `asc` - Default
  * `desc`
* `max-records` - the maximum number of records displayed in the table. The default is the number specified in the
  configuration manager. Any values higher than the maximum set will be ignored, and the admins maximum is used instead

#### Example:

`{{airtable>type: "table" | fields: "Name", "# of bulbs", "Posted By", "Post Text", "Post Image(s)" | where: "AND({Idea #} <= 3450, {Idea #} > 3430)" | max-records: "10" | order-by: "# of bulbs" | order: "asc" | record-url: "https://airtable.com/tblZnH5CY11MZEboF/viwVm4rH7dceE7VV2?blocks=hide"}}`  
Note: any media (images/audio/video) returned by airtable, will also be rendered inside the table.
![Table Example](images/table.png)

### Records:

Any table query that results in one record will also be rendered as a 'record'.  
Records are rendered as a template where text is displayed on the left and media on the right. Syntax:  
`{{airtable>type: "record" | record-url: "theURL" | fields: "field1", "field2" | alt-tag: "ALT_TAG"}}`  
You must specify **at least one** field.

Optional Parameters:

* `alt-tag` - used if an image is found - Image
  description. [How to write a good alt tag](https://moz.com/learn/seo/alt-text)

#### Example:

`{{airtable>Type: "record" | Fields: "Photo ID", "Ref #", "Upload Date", "Photo" | record-url: "https://airtable.com/tblwWxohDeMeAAzdW/viwlkwVdJY5sDNEWB/rec8WvGh6arIxPbqs?blocks=hide"}}`
![Record Example](images/record.png)

### Images:

To embed an image from airtable use the following syntax:

`{{airtable>type: "image" | record-url: "URL" | alt-tag: "ALT_TAG" | image-size: "IMG_SIZE" | position: "POS"}}`

Optional Parameters:

* `alt-tag: ` - Image description. [How to write a good alt tag](https://moz.com/learn/seo/alt-text)
* `image-size: ` - The size of the image to appear on the page. Values:
  * `small`
  * `large` - Default
  * `full`
* `position` - Where the image is displayed on the page. Values:
  * `block`  - Default
  * `left`   - Floats left
  * `centre` - Floats centre
  * `right`  - Floats right

#### Example:

`{{airtable>type: "image" | record-url: "https://airtable.com/tblZnH5CY11MZEboF/recAvmU5363ofWYR6" | image-size: "full" | alt-tag: "Digital Snake to Ethernet" | position: "right"}}`
![Image Example](images/image.JPG)

### Text:

To pull data from airtable and display as inline text use the following syntax:

`{{airtable>type: "text" | fields: "field1", "field2" | record-url: "theRecordURL"}}`
This display type requires the additional parameter: `fields`  
**At least one** field must be specified. Multiple fields will be concatenated to a single string
**Make sure to write the field exactly as it appears in airtable**

#### Example:

`Text Before: {{airtable>Type: "text" | Fields: "Part Name" | record-url:
"https://airtable.com/tblK7lKsAP0YqSOwu/viwi6PpnGIhJsNAuf/recsKmhxWmZFckx66"}} :Text After`  
Will produce:  
`Text Before: Bottom Felt :Text After`