# Airtable Dokuwiki Sync  
A Dokuwiki plugin to sync data from airtable  

## Prerequisites:
* Download and install [confmanager](https://www.dokuwiki.org/plugin:confmanager) This is used to create and format the config file
* Create an airtbale account and get an API key: https://airtable.com/api - also see their documentation on how to create a query  

## Installation
Place the airtable folder inside your Dokuwiki plugin directory:  
`DOKUWIKI_ROOT/lib/plugins`  
Add a cron job for automated scheduling of all syncs made in the config file with jobs.php  
E.g. ` */5 * * * * /usr/bin/php /home/username/public_html/lib/plugins/airtable/jobs.php >/dev/null 2>&1`
Will run all jobs in the config file, every 5 minutes.  

## Usage:
Add as many requests as you need in the config file using the config manager in Dokuwiki. This can be found in the admin panel under "Configuration File Manager" once confmanager is installed.

KEY: Enter a unique int - this is the order the requests are processed e.g. starting from 0  
VALUE Use this format: BASEID, QUERY, APIKEY, DESTINATION_FILE  
E.g.  
`appZGFwgzjqeMwdqy, Martin%20Requests, APIKEY, start2.txt`
This will pull data from the "Martin Requests" airtable and save the result in start2.txt
Airtable queries need to be URL encoded. Please see: https://codepen.io/airtable/full/rLKkYB?baseId=appZGFwgzjqeMwdqy&tableId=tbluKjrlpF4zBDr61
