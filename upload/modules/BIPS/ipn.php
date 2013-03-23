<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/BIPS.php');

$BIPS = $_POST;
$hash = hash('sha512', $BIPS['transaction']['hash'] . Configuration::get('BIPS_SECRET'));

if ($BIPS['hash'] == $hash && $BIPS['status'] == 1)
{
	$BIPS = new BIPS();
	$BIPS->writeDetails($_POST['custom']['order_id'], $_POST['custom']['cart_id'], $_POST['invoice'], $_POST['transaction']['hash'], $_POST['transaction']['address']);
}

exit;

?>