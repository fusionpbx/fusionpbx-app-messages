<?php
// Function Explanation
    // This function is used as SSRF protection before a remote media URL is
    // fetched. A URL is only considered safe when it uses http or https, its
    // host resolves, and every resolved ip address is a public address.
    // Loopback, private, link-local, reserved, multicast and carrier-grade
    // NAT addresses are all blocked, so an inbound message cannot be abused
    // to probe or attack internal network resources.
    // The result is cached per url so that repeated calls within the same
    // request do not repeat the dns lookups.

    // Example usage:
    //      if (is_safe_remote_url($message_media_url)) {
    //          //fetch the media
    //      }

function is_safe_remote_url($url) {
    static $cache = [];

    $url = (string) $url;
    if ($url === '') {
        return false;
    }

    //avoid repeated dns lookups for the same url within a single request
    if (array_key_exists($url, $cache)) {
        return $cache[$url];
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return $cache[$url] = false;
    }

    //only allow http and https
    $scheme = strtolower($parts['scheme'] ?? 'http');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return $cache[$url] = false;
    }

    $host = strtolower($parts['host']);

    //strip the brackets from an ipv6 literal
    if (substr($host, 0, 1) === '[' && substr($host, -1) === ']') {
        $host = substr($host, 1, -1);
    }

    //collect the ip addresses to validate
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        //the host is an ip literal
        $ips[] = $host;
    }
    else {
        if (strpos($host, ':') !== false) {
            //a hostname containing a colon is malformed
            return $cache[$url] = false;
        }

        //resolve the hostname
        $resolved = @gethostbynamel($host);
        if (is_array($resolved) && count($resolved) > 0) {
            $ips = array_values($resolved);
        }
        else {
            //gethostbynamel returns the host itself when it fails to resolve
            $ip = @gethostbyname($host);
            if (!is_string($ip) || $ip === $host || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                //unresolvable host
                return $cache[$url] = false;
            }
            $ips[] = $ip;
        }
    }

    //every resolved address must be a public ip address
    foreach ($ips as $ip) {
        if (!is_public_ip($ip)) {
            return $cache[$url] = false;
        }
    }

    return $cache[$url] = true;
}

// Function Explanation
    // This function returns true when the ip address is a public address.
    // Loopback, private, link-local, reserved, documentation, benchmarking
    // and multicast ranges are blocked, as is the carrier-grade NAT range
    // (100.64.0.0/10).
    // The ranges are checked with packed binary comparisons so the function
    // behaves the same on every php build.

function is_public_ip($ip) {
    $packed = @inet_pton((string) $ip);
    if ($packed === false) {
        return false;
    }

    //unwrap an ipv4-mapped ipv6 address (eg. ::ffff:192.168.1.1)
    if (strlen($packed) == 16 && substr($packed, 0, 10) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" && substr($packed, 10, 2) === "\xff\xff") {
        return is_public_ip(inet_ntop(substr($packed, 12)));
    }

    //the non-public ranges, packed as [start, end] pairs
    if (strlen($packed) == 4) {
        $blocked = [
            ['0.0.0.0', '0.255.255.255'],         //this network on this interface
            ['10.0.0.0', '10.255.255.255'],       //private
            ['100.64.0.0', '100.127.255.255'],    //carrier grade nat
            ['127.0.0.0', '127.255.255.255'],     //loopback
            ['169.254.0.0', '169.254.255.255'],   //link-local
            ['172.16.0.0', '172.31.255.255'],     //private
            ['192.0.0.0', '192.0.0.255'],         //ietf protocol assignments
            ['192.0.2.0', '192.0.2.255'],         //documentation
            ['192.88.99.0', '192.88.99.255'],     //6to4 relay-anycast
            ['192.168.0.0', '192.168.255.255'],   //private
            ['198.18.0.0', '198.19.255.255'],     //benchmarking
            ['198.51.100.0', '198.51.100.255'],   //documentation
            ['203.0.113.0', '203.0.113.255'],     //documentation
            ['224.0.0.0', '239.255.255.255'],     //multicast
            ['240.0.0.0', '255.255.255.254'],     //reserved
        ];
    }
    else {
        $blocked = [
            ['::', '::'],                                                    //unspecified
            ['::1', '::1'],                                                  //loopback
            ['::', '::ffff:ffff'],                                           //ipv4-compatible (deprecated)
            ['64:ff9b::', '64:ff9b:ffff:ffff:ffff:ffff:ffff:ffff'],           //nat64 (reserved)
            ['100::', '100::ffff:ffff:ffff:ffff'],                           //discarded
            ['2001:db8::', '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff'],        //documentation
            ['2002::', '2002:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],           //6to4 (deprecated)
            ['fc00::', 'fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],           //unique local
            ['fe80::', 'febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],           //link-local
            ['fec0::', 'feff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],           //site-local (deprecated)
            ['ff00::', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],           //multicast
        ];
    }

    //the address is not public when it falls inside any of the blocked ranges
    foreach ($blocked as $range) {
        $start = inet_pton($range[0]);
        $end = inet_pton($range[1]);
        if ($start !== false && $end !== false && packed_compare($packed, $start) >= 0 && packed_compare($packed, $end) <= 0) {
            return false;
        }
    }

    return true;
}

// Function Explanation
    // This function compares two packed ip addresses byte by byte, like
    // bcmp() does. It is used instead of bcmp() because the bcmath
    // extension is not available on every installation.
    // Returns -1 when $a sorts before $b, 1 when it sorts after and 0
    // when both are identical.

function packed_compare($a, $b) {
    $len = strlen($a);
    for ($i = 0; $i < $len; $i++) {
        $byte_a = ord($a[$i]);
        $byte_b = ord($b[$i]);
        if ($byte_a !== $byte_b) {
            return $byte_a < $byte_b ? -1 : 1;
        }
    }
    return 0;
}
