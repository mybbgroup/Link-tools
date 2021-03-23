<?php

/**
 * This is an example file which you can copy to extra-curl-opts.php in this
 * same directory if your host requires additional cURL options to be set.
 * Make sure that the file to which you copy it is readable by your web server,
 * otherwise it will simply be ignored.
 *
 * Additional cURL options may be required, for example, for a host that
 * mandates the use of an HTTP proxy to access external web servers. Below are
 * the additional options which were thought to be required for GoDaddy but
 * which turned out to no longer(?) work there.
 *
 * You are free to add any of the possible options listed here:
 *
 * https://www.php.net/manual/en/function.curl-setopt.php
 */
return array(
	CURLOPT_PROXYTYPE      =>                           CURLPROXY_HTTP,
	CURLOPT_PROXY          => 'http://proxy.shr.secureserver.net:3128',
	CURLOPT_SSL_VERIFYPEER =>                                    false,
);