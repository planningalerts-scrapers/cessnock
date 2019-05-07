<?php
### Cessnock City Council scraper
require_once 'vendor/autoload.php';
require_once 'vendor/openaustralia/scraperwiki/scraperwiki.php';

use PGuardiario\PGBrowser;

date_default_timezone_set('Australia/Sydney');

# Default to 'thisweek', use MORPH_PERIOD to change to 'thismonth' or 'lastmonth' for data recovery
switch(getenv('MORPH_PERIOD')) {
    case 'thismonth' :
        $sdate = date('01/m/Y');
        $edate = date('t/m/Y');
        break;
    case 'lastmonth' :
        $sdate = date('01/m/Y', strtotime('-1 month'));
        $edate = date('t/m/Y', strtotime('-1 month'));
        break;
    default          :
        if ( preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', getenv('MORPH_PERIOD'), $matches) == true) {
            $sdate = date('01/m/Y', strtotime($matches[0]. '-01'));
            $edate = date('t/m/Y', strtotime($matches[0]. '-01'));
        } else {
            $sdate = date('d/m/Y', strtotime('-10 days'));
            $edate = date('d/m/Y');
        }
        break;
}
print "Getting data between " .$sdate. " and " .$edate. ", changable via MORPH_PERIOD environment\n";

$url_base = "http://datracker.cessnock.nsw.gov.au";
$comment_base = "mailto:council@cessnock.nsw.gov.au";

# Agreed Terms
$browser = new PGBrowser();
$page = $browser->get($url_base . "/");
$form = $page->form();
$form->set('agreed', 'true');
$page = $form->submit();

/* Request the actual payload
 * Note: $junk has been modified to download 1000 records - Not for slow server!
 */
$headers = ["Accept: application/json, text/javascript, */*; q=0.01"];
$junk = "draw=1&columns%5B0%5D%5Bdata%5D=0&columns%5B0%5D%5Bname%5D=&columns%5B0%5D%5Bsearchable%5D=true&columns%5B0%5D%5Borderable%5D=false&columns%5B0%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B0%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B1%5D%5Bdata%5D=1&columns%5B1%5D%5Bname%5D=&columns%5B1%5D%5Bsearchable%5D=true&columns%5B1%5D%5Borderable%5D=false&columns%5B1%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B1%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B2%5D%5Bdata%5D=2&columns%5B2%5D%5Bname%5D=&columns%5B2%5D%5Bsearchable%5D=true&columns%5B2%5D%5Borderable%5D=false&columns%5B2%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B2%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B3%5D%5Bdata%5D=3&columns%5B3%5D%5Bname%5D=&columns%5B3%5D%5Bsearchable%5D=true&columns%5B3%5D%5Borderable%5D=false&columns%5B3%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B3%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B4%5D%5Bdata%5D=4&columns%5B4%5D%5Bname%5D=&columns%5B4%5D%5Bsearchable%5D=true&columns%5B4%5D%5Borderable%5D=false&columns%5B4%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B4%5D%5Bsearch%5D%5Bregex%5D=false&start=0&length=1000&search%5Bvalue%5D=&search%5Bregex%5D=false&json=";
$json = '{"ApplicationNumber":null,"ApplicationYear":null,"DateFrom":"01/04/2017","DateTo":"01/04/2017","DateType":"1","RemoveUndeterminedApplications":false,"ApplicationDescription":null,"ApplicationType":null,"UnitNumberFrom":null,"UnitNumberTo":null,"StreetNumberFrom":null,"StreetNumberTo":null,"StreetName":null,"SuburbName":null,"PostCode":null,"PropertyName":null,"LotNumber":null,"PlanNumber":null,"ShowOutstandingApplications":false,"ShowExhibitedApplications":false,"PropertyKeys":null,"PrecinctValue":null,"IncludeDocuments":false}';
$json = json_decode($json);
$json->DateFrom = $sdate;
$json->DateTo   = $edate;
$json = json_encode($json);
$page = $browser->post($url_base. "/Application/GetApplications", $junk. urlencode($json), $headers);

# get payload from the HTTP respond
$payload = preg_split("#\n\s*\n#Uis", $page->html);
$payload = json_decode($payload[1]);

foreach ($payload->data as $record) {
    $description = explode("<b>", $record[4])[1];
    $description = strip_tags($description);
    $description = empty($description) ? $record[2] : preg_replace('/\s+/', ' ', $description);

    $date_received = explode("/", $record[3]);
    $date_received = $date_received[2]. "-" .$date_received[1]. "-" .$date_received[0];

    # Put all information in an array
    $application = [
        'council_reference' => $record[1],
        'address'           => explode(" <br/>", $record[4])[0],
        'description'       => $description,
        'info_url'          => $url_base . "/Application/ApplicationDetails/" .$record[0],
        'comment_url'       => $comment_base,
        'date_scraped'      => date('Y-m-d'),
        'date_received'     => $date_received
    ];

    print ("Saving record " . $application['council_reference'] . " - " .$application['address']. "\n");
//        print_r ($application);
    scraperwiki::save(array('council_reference'), $application);
}

?>
