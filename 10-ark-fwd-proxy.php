<?php
// ARK forward proxy script
// Developer: Maarten Coonen, Maastricht University Library.

// This script should run at the 'redirect'-URL that was entered during the NAAN registration at arks.org
// It will distinguish incoming ARKs based on the shoulder value and redirect the client to the server
// where the ARK-item is hosted.

// Example
// https://n2t.net/ark:/27364/c1g3pJo redirects to https://digitalcollections.library.maastrichtuniversity.nl/ark:$id
// It is catched by this 10-ark-fwd-proxy.php script and redirected to the acceptance environment based on the shoulder value 'c1'.

// IMPORTANT
// You need the following rewrite rules and conditions in your Apache configuration or .htaccess file to forward incoming ARKs to this local resolver script:
//
// # Redirect incoming ARK URLs to the forward proxy code
// RewriteCond %{REQUEST_URI} ark:/([0-9]{5})/([a-zA-Z0-9]+)$
// RewriteRule ^ark:/([0-9]{5})/([a-zA-Z0-9]+)$ helper/10-ark-fwd-proxy.php?ark=ark:/$1/$2 [PT,L]
// # Do not process any further rewrite rules when request URI is one of these:
// RewriteCond %{REQUEST_URI} helper/10-ark-fwd-proxy.php [OR]
// RewriteCond %{REQUEST_URI} helper/11-local-ark-site-resolver.php [OR]
// RewriteCond %{REQUEST_URI} ^s/*/ark:/([0-9]{5})/([a-zA-Z0-9]+)$
// RewriteRule .* - [L]
//
// # END OF ARK REWRITE RULES


$shoulderLookup = array(
    "a1" => "http://omeka.local",
//    "a1" => "https://digitalcollections-develop.library.maastrichtuniversity.nl",
    "b1" => "https://digitalcollections-test.library.maastrichtuniversity.nl",
    "c1" => "https://digitalcollections-accept.library.maastrichtuniversity.nl",
    "d1" => "https://digitalcollections.library.maastrichtuniversity.nl",
    "e1" => "https://plakkaten.library.maastrichtuniversity.nl"
);

# RewriteRule in .htaccess zorgt dat we op dit script uitkomen


if (!isset($_GET["ark"])) {
    echo "<h1>Missing ARK</h1>";
} else {
    $ark = $_GET["ark"];

    // Split ARK and extract the Shoulder value. Example:
    // $ark = "ark:/27364/c1g3pJo"
    // $shoulder = "c1"
    $parts = explode("/", $ark);

    // TODO: make compatible with longer shoulders. Shoulder always terminates at the first digit.
    $shoulder = substr($parts[2], 0, 2);
    $forwardUrl = $shoulderLookup[$shoulder];

    if (is_null($forwardUrl)) {
        echo "The shoulder '$shoulder' for ARK '$ark' is unknown. Please check your input.";
    } else {
        // Redirect to the server holding this ark
        // exit() is for clients who don't respect the "Location: ..." header
        header("Location: " . $forwardUrl . "/helper/11-local-ark-site-resolver.php?ark=" . $ark);
        exit;
    }
}

?>
