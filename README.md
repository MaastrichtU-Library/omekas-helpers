# Omeka S helpers
Various helper scripts used at the [Digital Collections by University Library Maastricht](https://digitalcollections.library.maastrichtuniversity.nl).

**Contents**
1. [ARK resolver scripts](#ark-resolver-scripts)
2. ...

## ARK resolver scripts
The two ARK resolver scripts in this repo provide global-to-local ARK resolvability for your items in Omeka S.

The two scripts should be used in conjunction with rewrite rules on the webserver briefly work like this:
1. Client enters ARK-PID-URL in browser.
2. n2t.net resolves the ARK to the redirect-URL registered with your NAAN (here: Omeka S base URL).
3. A set of rewrite rules on the webserver will forward the client to the `10-ark-fwd-proxy.php` script. Based on the shoulder 
   value of the PID (i.e. `d1` for Omeka S production instance), the script will redirect the client to the proper target system. 
4. At the target system, the client is catched by the `11-local-ark-site-resolver.php` script, which will lookup the 
   Omeka S site-slug where this ARK-item lives and redirect the client to it. 

Note that the `10-ark-fwd-proxy.php` script only needs to run at the server "NAAN-redirect-URL" server. 
The `11-local-ark-site-resolver.php` script must be present **at all target servers**.

### Prerequisites
- a NAAN with global n2t.net resolvability registered for your organisation at [arks.org](https://arks.org/about/getting-started-implementing-arks/)
  - Example: the NAAN for Maastricht University Library is https://n2t.net/ark:/27364
- [Omeka S](https://omeka.org/s/download/) version 3.2.0 or higher
- [ARK module](https://github.com/Daniel-KM/Omeka-S-module-Ark)
- Webserver compatible with rewrite rules. This readme is written for Apache. Similar approaches exist for Nginx and other webservers.

### Usage
1. SSH into your server
2. Go to the Omeka S installation directory
    ```
    cd /path/to/your/omekas/root
    ```
3. Git clone this repository
4. Rename the cloned folder to `helper`
    ```
    mv /path/to/your/omekas/root/omekas-helpers /path/to/your/omekas/root/helper
    ```
5. Open the file `./helper/10-ark-fwd-proxy.php` in a text editor and change the values for `$shoulderLookup` to the shoulder values and URLs used in your environment
    ```
    $shoulderLookup = array(
        "a1" => "http://your.dev.domain.org",
        "b1" => "https://your.test.domain.org",
        "c1" => "https://your.acc.domain.org",
        "d1" => "https://your.prod.domain.org"
    );
    ```
6. Set the rewrite rules in the `.htaccess` file used by Apache. Open the file `/path/to/your/omekas/root/.htaccess` in 
   a text editor and insert the following content at the top of the file, just below the statement `RewriteEngine On`
    ```
    # Redirect incoming ARK URLs to the forward proxy code
    RewriteCond %{REQUEST_URI} ark:/([0-9]{5})/([a-zA-Z0-9]+)$
    RewriteRule ^ark:/([0-9]{5})/([a-zA-Z0-9]+)$ helper/10-ark-fwd-proxy.php?ark=ark:/$1/$2 [PT,L]
    
    # Do not process any further rewrite rules when request URI is one of these:
    RewriteCond %{REQUEST_URI} helper/10-ark-fwd-proxy.php [OR]
    RewriteCond %{REQUEST_URI} helper/11-local-ark-site-resolver.php [OR]
    RewriteCond %{REQUEST_URI} ^s/*/ark:/([0-9]{5})/([a-zA-Z0-9]+)$
    RewriteRule .* - [L] 
    ```

If the Omeka S modules, the resolver scripts and the rewrite rules are configured correctly, then URLs like 
https://n2t.net/ark:/<NAAN>/<shoulder><mintedID> should lead you to the correct item page in Omeka S. 


## Acknowledgements
Many thanks to [Bob Coret](https://github.com/coret), developer at Gouda Tijd Machine & Netwerk Digitaal Erfgoed, who 
shared a large part of the ARK-resolver code with us.

## License
This code is licensed under [GNU GPLv3](./LICENSE) terms and conditions.
