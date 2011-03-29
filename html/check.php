<?php
/**
* A simple implementation of Server Product Model for inApp Purchases
* @author Kaloyan K. Tsvetkov <kaloyan@kaloyan.info>
*/

///////////////////////////////////////////////////////////////////////////////
 
/** Check for a product */
require '../inaphp.php';
$inAphp = new inAphp();
echo $inAphp->check();