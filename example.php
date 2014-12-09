<?php

/**
 * Example
 * @author Ali Haris 
 */

require 'KodeAuth.php';

/** Create a new instance of PDO */
try {
	$dbh = new PDO('mysql:host=localhost;dbname=session', 'root', 'root');
} catch (Exception $e) {
	echo "Unable to connect to database";
	exit;
}

/** Create an instance of Session */
$Session = new KodeAuth($dbh);

if (!$Session->authed()):
	try{
		$Session->login("username", "password");
	}
	catch (Exception $e){
		echo $e->getMessage();
	}
else:
	echo "Hello {$Session->data['username']}";
endif;