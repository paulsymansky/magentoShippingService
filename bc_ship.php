<?php
// Paul Symansky, (c) 2010-2013
// Created Nov. 8, 2010
// Last updated Feb. 10, 2013

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
$result = $client->multiCall($session, array(
	array('sales_order_shipment.info', $_GET['sid']),
	array('directory_country.list')
));

$shipment_info = $result[0];
$country_info = $result[1];

// Exit if there is no such shipment
if(array_key_exists('isFault', $shipment_info)){
	die();
}

// Find order increment ID
$order_info = $client->call($session, 'sales_order.list', array(array('order_id' => array('eq' => $shipment_info['order_id']))));

// Retrieve order information
$order_info = $client->call($session, 'sales_order.info', $order_info[0]['increment_id']);

// Find country information
foreach($country_info as $country_set){
	if($country_set['country_id'] == $order_info['shipping_address']['country_id']){
		$country_info = $country_set;
		break;
	}
}

// Find region/state/province information
$region_info = $client->call($session, 'directory_region.list', $country_info['iso2_code']);

// Change state name to two letter state code for US destinations and territories
if(!empty($region_info)){
	if($country_info['iso2_code'] == 'US'){
		$order_info['shipping_address']['region'] = $region_info[$order_info['shipping_address']['region_id'] - 1]['code'];
	}
	if(
		$country_info['iso2_code'] == 'AS' ||
		$country_info['iso2_code'] == 'GU' ||
		$country_info['iso2_code'] == 'MH' ||
		$country_info['iso2_code'] == 'FM' ||
		$country_info['iso2_code'] == 'MP' ||
		$country_info['iso2_code'] == 'PR' ||
		$country_info['iso2_code'] == 'PW' ||
		$country_info['iso2_code'] == 'VI'
	){
		$order_info['shipping_address']['country_id'] = 'US';
		$order_info['shipping_address']['region'] = $country_info['iso2_code'];
	}
}


// Print the contents of the entries 
$xml = new DOMDocument('1.0', 'ASCII');
$xml->formatOutput = true;

$order = $xml->createElement('order');
$xml->appendChild($order);

$delivery_name = $xml->createElement('delivery_name');																							// Delivery name
$delivery_name->appendChild($xml->createTextNode(xml_encode($order_info['shipping_address']['firstname'] . ' ' . $order_info['shipping_address']['lastname'])));
$order->appendChild($delivery_name);

$delivery_company = $xml->createElement('delivery_company');																					// Delivery company
$delivery_company->appendChild($xml->createTextNode(xml_encode($order_info['shipping_address']['company'])));
$order->appendChild($delivery_company);

$delivery_street_address = $xml->createElement('delivery_street_address');																		// Delivery street address
$delivery_street_address->appendChild($xml->createTextNode(xml_encode(address_block_to_lines($order_info['shipping_address']['street'], 1))));
$order->appendChild($delivery_street_address);

$delivery_street_address2 = $xml->createElement('delivery_street_address2');																	// Delivery street address 2
$delivery_street_address3 = $xml->createElement('delivery_street_address3');																	// Delivery street address 3/urbanization
if($country_info['iso2_code'] == 'US' && $order_info['shipping_address']['region'] == 'PR'){
	$delivery_street_address3->appendChild($xml->createTextNode(xml_encode(address_block_to_lines($order_info['shipping_address']['street'], 2))));
}else{
	$delivery_street_address2->appendChild($xml->createTextNode(xml_encode(address_block_to_lines($order_info['shipping_address']['street'], 2))));
}
$order->appendChild($delivery_street_address2);
$order->appendChild($delivery_street_address3);

$delivery_city = $xml->createElement('delivery_city');																							// Delivery city
$delivery_city->appendChild($xml->createTextNode(xml_encode($order_info['shipping_address']['city'])));
$order->appendChild($delivery_city);

$delivery_postcode = $xml->createElement('delivery_postcode');																					// Delivery post code
$delivery_postcode->appendChild($xml->createTextNode(xml_encode($order_info['shipping_address']['postcode'])));
$order->appendChild($delivery_postcode);

$delivery_state = $xml->createElement('delivery_state');																						// Delivery state
$delivery_state->appendChild($xml->createTextNode(xml_encode($order_info['shipping_address']['region'])));
$delivery_state->setAttribute("error", 'false');
$order->appendChild($delivery_state);

$delivery_country = $xml->createElement('delivery_country');																					// Delivery country
$delivery_country->appendChild($xml->createTextNode(xml_encode($country_info['name'])));
$delivery_country->setAttribute("error", 'false');
$order->appendChild($delivery_country);

$delivery_country_code = $xml->createElement('delivery_country_code');																			// Delivery country code
$delivery_country_code->appendChild($xml->createTextNode(xml_encode($country_info['iso2_code'])));
$order->appendChild($delivery_country_code);

$customers_email_address = $xml->createElement("customers_email_address");																		// Customer's email address
$customers_email_address->appendChild($xml->createTextNode(utf8_encode($order_info['customer_email'])));
$order->appendChild($customers_email_address);

$customers_telephone = $xml->createElement("customers_telephone");																				// Customer's telephone number
$customers_telephone->appendChild($xml->createTextNode(xml_encode(format_telephone($order_info['shipping_address']['telephone']))));
$order->appendChild($customers_telephone);

$shipping = $xml->createElement('shipping');																									// Shipping
$shipping->appendChild($xml->createTextNode(xml_encode($order_info['base_shipping_amount'])));
$order->appendChild($shipping);

$insurance = $xml->createElement('insurance');																									// Insurance
$insurance->appendChild($xml->createTextNode(xml_encode('FALSE')));
$order->appendChild($insurance);

$subtotal = $xml->createElement('subtotal');																									// Order subtotal
$subtotal->appendChild($xml->createTextNode(xml_encode($order_info['subtotal'])));
$order->appendChild($subtotal);

$total = $xml->createElement('total');																											// Order total
$total->appendChild($xml->createTextNode(xml_encode($order_info['grand_total'])));
$order->appendChild($total);

$weight = $xml->createElement('weight');																										// Order weight
$weight->appendChild($xml->createTextNode(xml_encode($order_info['weight'])));
$order->appendChild($weight);

$weight = $xml->createElement('weight_g');																										// Order weight (g)
$weight->appendChild($xml->createTextNode(xml_encode(lbs_to_grams($order_info['weight']))));
$order->appendChild($weight);

$weight = $xml->createElement('weight_lbs');																									// Order weight (lbs)
$weight->appendChild($xml->createTextNode(xml_encode(lbs_to_lbs_and_ozs($order_info['weight'], 'lbs'))));
$order->appendChild($weight);

$weight = $xml->createElement('weight_oz');																										// Order weight (oz)
$weight->appendChild($xml->createTextNode(xml_encode(lbs_to_lbs_and_ozs($order_info['weight'], 'ozs'))));
$order->appendChild($weight);

header('Content-Type: text/xml');
echo $xml->saveXML();



// Encode characters to be DOM compatible and upper case
function xml_encode($str){
	return utf8_encode(mb_strtoupper($str));
}

// Return a specific line in the address block
function address_block_to_lines($addr, $line){
	$addr = explode("\n", $addr);
	return $addr[$line - 1];
}

// Format the telephone number (remove all non-numeric characters)
function format_telephone($number){
	return preg_replace('[\D]', '', $number);
}

// Convert weight in lbs to weight in grams
function lbs_to_grams($weight){
	return round($weight * 453.592);
}

// Convert weight in lbs to weight in lbs and ozs
function lbs_to_lbs_and_ozs($weight, $units){
	switch($units){
		case 'lbs':
			return floor($weight);
		case 'ozs':
			return number_format(($weight - floor($weight)) * 16, 1);
	}
}

?>