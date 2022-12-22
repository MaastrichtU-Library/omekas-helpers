<?php

# Local ARK resolver

# Developer: Maarten Coonen, Maastricht University Library, 2022.
# Adapted from the code developed by Gouda Tijd Machine.
# Many thanks to the Bob Coret, developer at Gouda Tijd Machine & Netwerk Digitaal Erfgoed, for sharing the original code with us.

# This local resolver should be used in conjunction with '10-ark-fwd-proxy.php' and works like this:
# - Incoming (remote) request from './helper/10-ark-fwd-proxy.php' script
# - The local resolver script looks up the ARK id in the database to determine the site name (slug)
# - The local resolver script redirects to https://<THIS_HOST>/<$omeka_basepath>/<$slug>/ark:/27364/c1g3pJo

# Example end-to-end flow:
# 0. Client enters https://n2t.net/ark:/27364/c1g3pJo in browser
# 1. [HTTP 302 by n2t.net]        https://digitalcollections.library.maastrichtuniversity.nl/ark:/27364/c1g3pJo
# 2. [HTTP 302 by RewriteRule]    https://digitalcollections.library.maastrichtuniversity.nl/helper/10-ark-fwd-proxy.php?ark=ark:/27364/c1g3pJo
# 3. [HTTP 302 by fwd-proxy]      https://digitalcollections-accept.library.maastrichtuniversity.nl/helper/11-local-ark-site-resolver.php?ark=ark:/27364/c1g3pJo
# 4. [HTTP 302 by local-resolver] https://digitalcollections-accept.library.maastrichtuniversity.nl/s/examplesite/ark:/27364/c1g3pJo


// Given the URL http://omeka.local/s/examplesite/ark:/99999/a12vpho
// the $omeka_basepath would be "/s/"
// and the lookup result for $slug would be 'examplesite'
$omeka_basepath="/s/";

if (isset($_GET["ark"])) {
	//$ark="ark:/60537/b9MTov";
	$ark=preg_replace('/[^a-z0-9\:\/]/i','',$_GET["ark"]);

	# get omeka database settings
	$database_settings=parse_ini_file("../config/database.ini");

	# connect to db via mysqli
	try {
		$mysqli = new mysqli($database_settings["host"], $database_settings["user"], $database_settings["password"], $database_settings["dbname"]);
	} catch (\mysqli_sql_exception $e) {
		 throw new \mysqli_sql_exception($e->getMessage(), $e->getCode());
	}

	# find site slug
	$dbname = $database_settings["dbname"];	// Note: $dbname can be left out in query below
	$stmt = $mysqli->prepare("SELECT slug, v.resource_id, r.resource_type FROM $dbname.value v LEFT JOIN $dbname.item_site i ON v.resource_id=i.item_id LEFT JOIN $dbname.site s ON i.site_id=s.id LEFT JOIN $dbname.resource r ON v.resource_id=r.id WHERE v.value=?");
	$stmt->bind_param("s", $ark);
	$stmt->execute();
	$result = $stmt->get_result()->fetch_assoc();

	//error_log(print_r($result,true));

	if ($ark=="ark:/60537/bI5Sxd") {
		$slug="";
	} else {
		$slug="referentie";  // als een item niet in een site zit (bijv. data catalog en distributies) kan er geen slug gevonden worden 
	}

	if (isset($_SERVER['HTTP_ACCEPT']) && !empty($_SERVER['HTTP_ACCEPT'])) {
		header('Accept: '.$_SERVER['HTTP_ACCEPT']);
	}
	if ($result) {

		if ($result["resource_type"]=="Omeka\Entity\ItemSet") {
		    if (empty($slug)) {
				header("Location: " . $omeka_basepath . "collection/" . $ark);
			} else {
				header("Location: " . $omeka_basepath . $slug . "/collection/" . $ark);
			}
            // exit() is for clients who don't respect the "Location: ..." header
			exit;
		} else {
			$slug=$result["slug"];
            // exit() is for clients who don't respect the "Location: ..." header
			header("Location: " . $omeka_basepath . $slug . "/" . $ark);
            exit;
		}
	}

	header("Location: " . $omeka_basepath);
    // exit() is for clients who don't respect the "Location: ..." header
    exit;

} else {
	echo "<h1>Missing ARK</h1>";
}
?>
