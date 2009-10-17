<?php
require_once '../lib/vuzit.php';
require_once 'geo_location.php';

// GENERAL FUNCTIONS

// Returns the parameter from the _GET array or null if it isn't present.
function get($key)
{
  return array_key_exists($key, $_GET) ? $_GET[$key] : null;
}

// Exports a set of CSV fields into a line of CSV text.  The function
// mirrors the PHP fputcsv function.  
function csv_line($fields, $delimeter = ',', $enclosure = '"')
{
  $result = '';

  for($i = 0; $i < count($fields); $i++)
  {
    if($i != 0) {
      $result .= $delimeter;
    }
    $result .= ($enclosure . str_replace('"', '""', $fields[$i]) . $enclosure);
  }

  return $result . "\r\n";
}

// Load the header
function header_load($doc = null)
{
  $id = '';
  $onload = '';
  if($doc != null) 
  {
    $timestamp = time();
    $id = $doc->getId();
    $sig = Vuzit_Service::signature("show", $doc->getId(), $timestamp);
    $onload = "initialize()";
  }
?>
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
  <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml">
    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
      <title>Vuzit <?php echo get("c") ?> Command Example</title>
      <link href="http://vuzit.com/stylesheets/Vuzit-2.8.css" rel="Stylesheet" type="text/css" />
      <script src="http://vuzit.com/javascripts/Vuzit-2.8.js" type="text/javascript"></script>

      <script type="text/javascript">
        // Called when the page is loaded.  
        function initialize()  {
          vuzit.Base.apiKeySet("<?php echo Vuzit_Service::getPublicKey(); ?>"); 
          var options = {signature: '<?php echo rawurlencode($sig); ?>', 
                         timestamp: '<?php echo $timestamp ?>'}
          var viewer = vuzit.Viewer.fromId("<?php echo $id; ?>", options);
          
          viewer.display(document.getElementById("vuzit_viewer"), { zoom: 1 });
        }
      </script>
    </head>

    <body onload="<?php echo $onload; ?>"> 

    <h2>Command: <?php echo get("c"); ?></h2>
<?php
}

// Prints the footer
function footer_load()
{
?>
    </body>
  </html>
<?php
}

// COMMAND FUNCTIONS

// Runs the load command
function load_command()
{
  $doc = Vuzit_Document::findById(get("id"));
  header_load($doc);
  $pdf_url = Vuzit_Document::downloadUrl($doc->getId(), "pdf");

  // TODO: Grab the file type and use it to determine the other file 
  //       to download?  
  ?>
    <h3>
      Document - <?php echo $doc->getID(); ?>
    </h3>
    <ul>
      <li>Title: <?php echo $doc->getTitle(); ?></li>
      <li>Subject: <?php echo $doc->getSubject(); ?></li>
      <li>Page count: <?php echo $doc->getPageCount(); ?></li>
      <li>Width: <?php echo $doc->getPageWidth(); ?></li>
      <li>Height: <?php echo $doc->getPageHeight(); ?></li>
      <li>File size: <?php echo $doc->getFileSize(); ?></li>
      <li><a href="<?php echo $pdf_url; ?>">PDF Download</a></li>
    </ul>

    <div id="vuzit_viewer" style="width: 650px; height: 500px;"></div>
  <?php
  footer_load();
}

// Runs the delete command
function delete_command()
{
  header_load();
  try
  {
    $id = get("id");
    Vuzit_Document::destroy($id);
    echo "Delete succeeded: " . $id;
  }
  catch(Vuzit_ClientException $ex)
  {
    echo "Delete of " . $id . " failed with code [" . $ex->getCode() . "], message: " . $ex->getMessage();
  }
  footer_load();
}

// Runs the upload command
function upload_command()
{
  $doc = Vuzit_Document::upload(get("path"));
  header_load($doc);
  ?>
    <h3>
      Document - <?php echo $doc->getID(); ?>
    </h3>

    <div id="vuzit_viewer" style="width: 650px; height: 500px;"></div>
  <?php
  footer_load();
}

// Runs the event command
function event_command()
{
  $options = array();

  if(get("v") != null) {
    $options["value"] = get("v");
  }
  if(get("e") != null) {
    $options["event"] = get("e");
  }
  if(get("l") != null) {
    $options["limit"] = get("l");
  }

  $list = Vuzit_Event::findAll(get("id"), $options);

  switch(get("o"))
  {
    case "csv":
      event_load_csv($list);
      break;
    default:
      header_load();
      event_load_html($list);
      footer_load();
      break;
  }
}

// Loads the output of the event list as a CSV file.  
function event_load_csv($list, $fileName = "events.csv")
{
  $csv = '';

  $csv .= csv_line(array("web_id", "event", "page", "requested_at", "value", 
                         "remote_host", "referer", "user_agent", "zoom"));
  foreach($list as $event)
  {
    $fields = array($event->getWebId(),
                    $event->getEvent(),
                    $event->getPage(),
                    date("m/d/y H:i", $event->getRequestedAt()),
                    $event->getValue(),
                    $event->getRemoteHost(),
                    $event->getReferer(),
                    $event->getUserAgent(),
                    $event->getZoom()
                   );
    $csv .= csv_line($fields);
  }
  
  header('Content-type: text/csv');
  header("Content-Disposition: attachment; filename=\"" . $fileName . "\";"); 
  echo $csv;
}

// Loads the output of the event list as HTML.  
function event_load_html($list)
{
  ?>
  <h3>
    Document <?php echo $list[0]->getWebId(); ?>
  </h3>
  <p>
    Total events: <?php echo count($list); ?>
  </p>
  <ol>
  <?
  $event = count($list);
  for($i = 0; $i < count($list); $i++)
  { 
    $item = $list[$i];
    $event--;
    $host = $item->getRemoteHost();
    ?>
      <li>
        [<?php echo $item->getEvent(); ?>] on page <?php echo $item->getPage(); ?> 
        for <?php echo $item->getDuration(); ?> seconds 
        at <?php echo date("Y-d-m H:i:s", $item->getRequestedAt()); ?> 
        (value: <?php echo $item->getValue(); ?>) - 
        <a href="<?php echo $item->getReferer(); ?>">Referring page</a> - 
        Remote host: 
        <a href="location.php?ip=<?php echo $host; ?>"><?php echo $host; ?></a>
        - User Agent: <?php echo substr($item->getUserAgent(), 0, 5); ?>...
      </li>
    <?php
  } 
  echo "</ol>";
}

// MAIN EXECUTION

// Load and check the keys
$public_key = get("puk");
$private_key = get("prk");

if($public_key == null || $private_key == null)
{
  echo "Please provide the public (puk) and private (prk) keys";
  return;
}

$service_url = get("su");

if($service_url != null) {
  Vuzit_Service::setServiceUrl($service_url);
}

Vuzit_Service::setPublicKey($public_key);
Vuzit_Service::setPrivateKey($private_key);

// Grab the command and execute
switch(get("c"))
{
  case "load":
    load_command();
    break;
  case "upload":
    upload_command();
    break;
  case "delete":
    delete_command();
    break;
  case "event":
    event_command();
    break;
}
?>
