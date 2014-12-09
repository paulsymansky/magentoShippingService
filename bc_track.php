<?php
// Paul Symansky, (c) 2010-2013
// Created Feb. 11, 2013
// Last updated Feb. 11, 2013

require_once('bc_common.php');

error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Create new SOAP client using v1 WSDL
$client = new SoapClient('http://' . $_SERVER['SERVER_NAME'] . $mage_dir .'api/soap/?wsdl');
$session = $client->login($api_user, $api_key);

// Exit if there is no order number provided
if(!isset($_GET['sid']) && !is_numeric($_GET['sid'])){
	die();
}

// Retrieve shipment information
$shipment_info = $client->call($session, 'sales_order_shipment.info', $_GET['sid']);

// Exit if there is no such shipment or if a tracking number already exists
if(array_key_exists('isFault', $shipment_info) || !empty($shipment_info["tracks"])){
	die();
}

// Store tracking number and notify customer
$result = $client->multiCall($session, array(
	array('sales_order_shipment.addTrack', array($_GET['sid'], 'usps', 'United States Postal Service', $_GET['tracking'])),
	array('sales_order_shipment.sendInfo', $_GET['sid'])
));

?>