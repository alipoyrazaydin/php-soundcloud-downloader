<?php
// This script is written by kigipux (Ali Poyraz AYDIN)
// Feel free to change, distribute and use it on your own.

error_reporting(E_ALL & ~E_NOTICE);

// Config
$scd_config = array(
  "API-URL"    => "https://api-v2.soundcloud.com",
  "CLIENT-ID"  => null // Use null to dynamically generate ClientID
);

// Requests
$requests = array(
  "resolve" => $scd_config["API-URL"] . "/resolve?client_id=[0]&url=[1]"
);

// Variables
$_SCD_VARS = array();

// Framework
function regex_all($exp,$content){
  preg_match_all($exp,$content,$_PREG_OUTPUT);
  return $_PREG_OUTPUT;
}
function fetch($url){
  return file_get_contents($url);
}
function strformat($source,$arr){
  $stext = $source;
  $is = count($arr);
  for ($i = 0;$i < $is;$i++) $stext = str_replace("[".$i."]",$arr[$i],$stext);
  return $stext;
}
function filter_filename($filename) {
    $filename = preg_replace(
        '~
        [<>:"/\\\|?*]|
        [\x00-\x1F]|-
        [\x7F\xA0\xAD]|
        [#\[\]@!$&\'()+,;=]| -
        [{}^\~`]
        ~x',
        '-', $filename);
    $filename = ltrim($filename, '.-');
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
    return $filename;
}
function stream_notification_callback($b, $o, $i,$ik, $bs, $abs) {
    static $db = null;
    switch($b) {
    case STREAM_NOTIFY_RESOLVE:
    case STREAM_NOTIFY_AUTH_REQUIRED:
    case STREAM_NOTIFY_COMPLETED:
    case STREAM_NOTIFY_FAILURE:
    case STREAM_NOTIFY_AUTH_RESULT:
        break;

    case STREAM_NOTIFY_REDIRECTED:
        echo "Forwarded to: ", $i, "\n";
        break;

    case STREAM_NOTIFY_CONNECT:
        echo "Connected, Downloading content now...\n";
        break;

    case STREAM_NOTIFY_FILE_SIZE_IS:
        $db = $abs;
        echo "File Size: ", $db, "\n";
        break;

    case STREAM_NOTIFY_MIME_TYPE_IS:
        echo "MIME type: ", $i, "\n";
        break;

    case STREAM_NOTIFY_PROGRESS:
        if ($bs > 0) {
            if (!isset($db)) {
                printf("\rFile size unknown... %2d kb downloaded..",$bs/1024);
            } else {
                $length = (int)(($bs/$db)*100);
                $dom_length = (int)(20 / 100 * $length);
                printf("\r[%-20s] %%%d (%2d/%2d kb)", str_repeat("=",$dom_length). ">", $length, ($bs/1024), $db/1024);
            }
        }
        break;
    }
}
function downloadFile($url){
  $ctx = stream_context_create();
  if (PHP_SAPI == "cli") stream_context_set_params($ctx, array("notification" => "stream_notification_callback"));
  return @file_get_contents($url,false,$ctx);
}

// Functions
function getvars(){
  // detect if it's a web platform or command line
  $_cmds = (PHP_SAPI == "cli" ? "cmdline" : "get");
  if (
    $_cmds == "get"
  ){
    global $_SCD_VARS;
    $_SCD_VARS = $_GET;
  }
  else
  if (
    $_cmds == "cmdline"
  ) {
    global $_SCD_VARS;
    global $argv, $argc;
    $argarr = $argv;
    array_shift($argarr);
    foreach ($argarr as $arg){
      $argk = explode("=",$arg);
      $_SCD_VARS[$argk[0]] = $argk[1];
    }
  }
}

function getMP3fromSCLink($link){
  // Check if ClientID is set
  global $scd_config;
  global $requests;
  if (!isset($scd_config["CLIENT-ID"])){
    // ClientID is not set, generate a Dynamic ClientID instead. (EXTREMELY SLOW DUE TO SOUNDCLOUD)
    if (PHP_SAPI == "cli") echo "No ClientID found, Generating a Dynamic ClientID (this will take a moment)\n";
    $scPage = fetch("https://soundcloud.com/");
    $clientIDContainingAssetURL = end(regex_all('/src=\"(https:\/\/a-v2\.sndcdn\.com\/assets\/[^\.]+\.js)"/',$scPage)[1]);
    $scrPage = fetch($clientIDContainingAssetURL);
    $scd_config["CLIENT-ID"] = regex_all('/[\,|\{]client_id:\"([^\"]+)\"/',$scrPage)[1][0];
  }
  
  // Resolve and get download link
  if (PHP_SAPI == "cli") echo "Resolving URL...\n";
  $resolv_base = json_decode(file_get_contents(strformat($requests["resolve"],array($scd_config["CLIENT-ID"],$link))),true);
  $resolved = array_shift(array_filter($resolv_base["media"]["transcodings"],function($elem){return $elem["format"]["protocol"] === "progressive";}))["url"]."?client_id=".$scd_config["CLIENT-ID"];
  $mp3_url = json_decode(fetch($resolved),true)["url"];
  if (PHP_SAPI == "cli") echo "Downloading Content\n";
  $content = downloadFile($mp3_url);
  if (PHP_SAPI == "cli") echo "\n";
  if (PHP_SAPI == "cli") file_put_contents($resolv_base["id"]." - ".filter_filename($resolv_base["title"]).".mp3",$content);
  if (PHP_SAPI !== "cli"){
    header("Content-type: audio/mpeg");
    echo $content;
  }
}

// Start
if (PHP_SAPI == "cli") echo "Soundcloud Downloader PHP by kigipux (Ali Poyraz AYDIN)\n";
getvars();

if (!isset($_SCD_VARS["url"])){
    if (PHP_SAPI == "cli"){echo "No URL variable supplied. Exiting.\n";exit;}
    if (PHP_SAPI !== "cli"){
      header("Content-type: application/json");
      echo '{"error":"No URL variable supplied."}';
    }
}
getMP3fromSCLink($_SCD_VARS["url"]);

?>
