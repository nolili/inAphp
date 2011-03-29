<?php
/**
* A simple implementation of Server Product Model for inApp Purchases
* @author Kaloyan K. Tsvetkov <kaloyan@kaloyan.info>
*/

///////////////////////////////////////////////////////////////////////////////

/** Verify a receipt */
require '../inaphp.php';
$inAphp = new inAphp();
echo $inAphp->verify();