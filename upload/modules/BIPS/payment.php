<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/BIPS.php');

if (!$cookie->isLogged())
    Tools::redirect('authentication.php?back=order.php');
   
$BIPS = new BIPS();
echo $BIPS->execPayment($cart);

include_once(dirname(__FILE__).'/../../footer.php');

?>