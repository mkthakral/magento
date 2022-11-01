<?php

require_once '../app/Mage.php';
umask(0);
Mage::app('default');
ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 




$youtubeVideoURL=$_REQUEST['youtubeVideoURL'];

//Remove last '/' is its there
if(substr($youtubeVideoURL,-1) === "/"){
	$youtubeVideoURL = substr($youtubeVideoURL, 0, -1);
}

$theURL = "http://www.youtube.com/oembed?url=".$youtubeVideoURL. "&format=json";
$headers = get_headers($theURL);


if(substr($headers[0], 9, 3) === "200" ){
	echo "";
}else if(substr($headers[0], 9, 3) === "401" ){
	echo "Unauthorized";
}else{
	echo "Invalid";
}

   
?>  