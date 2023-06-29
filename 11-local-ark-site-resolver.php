<?php

# Local ARK resolver

# Developer: Maarten Coonen, Maastricht University Library, 2023.
# Adapted from the code developed by Gouda Tijd Machine.
# Many thanks to Bob Coret, developer at Gouda Tijd Machine & Netwerk Digitaal Erfgoed, for sharing the original code with us.

# This local resolver should be used in conjunction with '10-ark-fwd-proxy.php' and works like this:
# - Incoming (remote) request from './helper/10-ark-fwd-proxy.php' script
# - The local resolver script looks up the ARK id in the database to determine the site name (slug) and internal Omeka-id for the item or item set
# - In case the ARK points to an item-set, the local resolver script redirects to https://<THIS_HOST>/<$omeka_basepath>/<$slug>/item-set/<$item_set_id>
# - In case the ARK points to an item, the local resolver script redirects to https://<THIS_HOST>/<$omeka_basepath>/<$slug>/item/<$item_id>

# Example end-to-end flow:
# 0. Client enters https://n2t.net/ark:/27364/c1g3pJo in browser
# 1. [HTTP 302 by n2t.net]        https://digitalcollections.library.maastrichtuniversity.nl/ark:/27364/c1g3pJo
# 2. [HTTP 302 by RewriteRule]    https://digitalcollections.library.maastrichtuniversity.nl/helper/10-ark-fwd-proxy.php?ark=ark:/27364/c1g3pJo
# 3. [HTTP 302 by fwd-proxy]      https://digitalcollections-accept.library.maastrichtuniversity.nl/helper/11-local-ark-site-resolver.php?ark=ark:/27364/c1g3pJo
# 4. [HTTP 302 by local-resolver] https://digitalcollections-accept.library.maastrichtuniversity.nl/s/examplesite/item/1234


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
	$stmt = $mysqli->prepare("
		SELECT slug, v.resource_id, r.resource_type 
		FROM $dbname.value v 
		LEFT JOIN $dbname.item_site i ON v.resource_id=i.item_id 
		LEFT JOIN $dbname.site s ON i.site_id=s.id 
		LEFT JOIN $dbname.resource r ON v.resource_id=r.id 
		WHERE v.value=?
	");
	$stmt->bind_param("s", $ark);
	$stmt->execute();
	$result = $stmt->get_result()->fetch_assoc();

	//error_log(print_r($result,true));


	if (isset($_SERVER['HTTP_ACCEPT']) && !empty($_SERVER['HTTP_ACCEPT'])) {
		header('Accept: '.$_SERVER['HTTP_ACCEPT']);
	}

	if ($result) {

		if ($result["resource_type"]=="Omeka\Entity\ItemSet") {
			// Since item-sets are not bound to a site, we need to 
			// lookup the first item in this item-set to determine the site slug
			// The item-set is represented by the "resource_id" in the query below
			$stmt = $mysqli->prepare("
				SELECT slug, i.item_id, i.item_set_id, s.site_id 
				FROM $dbname.item_item_set i
				LEFT JOIN $dbname.item_site s ON i.item_id=s.item_id
				LEFT JOIN $dbname.site t ON t.id=s.site_id
				WHERE i.item_set_id=?
				LIMIT 1
			");
			$stmt->bind_param("s", $result["resource_id"]);
			$stmt->execute();
			$result2 = $stmt->get_result()->fetch_assoc();
			
			$slug = $result2["slug"];
			$item_set_id = $result2["item_set_id"];
					
			// Perform the redirect to the item-set
			header("Location: " . $omeka_basepath . $slug . "/item-set/" . $item_set_id);
			
			// exit() is for clients who don't respect the "Location: ..." header
			exit;
		} else {
			$slug = $result["slug"];
			$item_id = $result["resource_id"];
            
			// Perform the redirect to the item
			header("Location: " . $omeka_basepath . $slug . "/item/" . $item_id);

			// exit() is for clients who don't respect the "Location: ..." header
			exit;
		}
	}

	// Redirect to 404-page when there is no result
	header("Location: /not-found");

	// exit() is for clients who don't respect the "Location: ..." header
	exit;

} else {
	echo "<h1>Missing ARK</h1>";
}
?>
