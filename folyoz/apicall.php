<?php

$url = "https://api.stripe.com/v1/customers/cus_EOpQfzahne9yOu";
$apiKey = 'Bearer sk_test_WFxAGuLlWZicjON1afPvVCIC'; // should match with Server key
$headers = array(
     'Authorization: '.$apiKey
);
// Send request to Server
$ch = curl_init($url);
// To save response in a variable from server, set headers;
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// Get response
$response = curl_exec($ch);
// Decode
//$result = json_decode($response);

$result = curl_exec($ch);


print_r($response);
curl_close($ch);


 ?>