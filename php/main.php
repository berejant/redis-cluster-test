#!/usr/bin/env php
<?php

$redis_master = new Redis;
$redis_master->connect('redis-master');

$redis_slave = new Redis;
$redis_slave->connect('redis-replica');

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 3);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Connection: Keep-Alive',
    'Keep-Alive: 10'
]);

$base_url = 'https://uk.wikipedia.org/wiki/';

$next_url = $redis_master->lPop('urls') ?: '';
while (is_string($next_url)) {
    $response = xFetch('url_' . md5($next_url), 60, 1, function () use ($next_url, $ch, $base_url) {
        curl_setopt($ch, CURLOPT_URL, $base_url . $next_url);
        $response = curl_exec($ch);
        echo 'Download ', urldecode($next_url), ' - ', strlen($response), PHP_EOL;

        parse_urls($response);

        return $response;
    });

    $next_url = $redis_master->lPop('urls');
}

function xFetch($key, $ttl, $beta, Closure $fallback)
{
    [$value, $delta, $expiry] = cacheRead($key);
    if (!$value || (time() - $delta * $beta * log(rand())) > $expiry) {
        $start  = time();
        if ($value) {
            echo 'Probalistic cache flush ', PHP_EOL;
        }
        $value  = $fallback($key);
        $delta  = time() - $start;
        $expiry = time() + $ttl;
        cacheWrite($key, [$value, $delta, $expiry], $ttl);
    }

    return $value;
}

function cacheRead($key) {
    global $redis_slave;
    $value = $redis_slave->get($key);
    if ($value) {
        return json_decode($value, true);
    }
    return null;
}

function cacheWrite($key, $value, $ttl) {
    global $redis_master;
    $value = json_encode($value);
    $redis_master->setex($key, $ttl, $value);
    print_redis_memory_info();
}

function parse_urls($response) {
    global $redis_master, $redis_slave;

    if (preg_match_all('#href="/wiki/(?P<url>.*?)"#', $response, $matches)) {
        $args = ['urls'];
        foreach ($matches['url'] as $url) {
            if (!$redis_slave->exists('url_' . md5($url))) {
                $args[] = $url;
            }
        }

        if (isset($args[1])) {
            call_user_func_array([$redis_master, 'rPush'], $args);
        }
    }
}

function print_redis_memory_info() {
    global $redis_master;

    echo 'Redis used memory: ', $redis_master->info('memory')['used_memory_human'], PHP_EOL;
    echo 'Redis keyspace: ', array_values($redis_master->info('Keyspace'))[0], PHP_EOL;
}

echo 'Finish', PHP_EOL;
