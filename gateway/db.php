<?php
require_once 'db_config.php';

try {
  	$db = new PDO("mysql:dbname=$db_name;host=$db_host", $db_user, $db_password, array( PDO::ATTR_PERSISTENT => true));
	// for production server change the next line to  
	// $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $ex) {
  echo 'Connection failed: ' . $ex->getMessage();
}
?>
