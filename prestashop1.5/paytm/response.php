<?php

	
	include(dirname(__FILE__).'/../../config/config.inc.php');
  include(dirname(__FILE__).'/../../header.php');
  include(dirname(__FILE__).'/paytm.php');
	


  $paytm = new Paytm(); 
  if(isset($_POST) && isset($_POST['CHECKSUMHASH'])){
		$paytm->processPayment();
	}
	
	
	include_once(dirname(__FILE__).'/../../footer.php');
	
?>
 