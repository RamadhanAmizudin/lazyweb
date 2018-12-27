<?php

function pw($str = '') {
    global $_CONFIG;
    return password_hash($str, $_CONFIG['user']['encrypt_algo']);
}

function verifypw($str, $hash) {
    return password_verify($str, $hash);
}

function isAuth() {
    return (bool) session('auth', false);
}

function isAdmin() {
    return (bool) session('admin', false);
}

function isPost() {
    return (bool) ($_SERVER['REQUEST_METHOD'] === 'POST');
}

function cookie($index, $default = null) {
    return element($index, $_COOKIE, $default);
}

function get($index, $default = null) {
    return element($index, $_GET, $default);
}

function post($index, $default = null) {
    return element($index, $_POST, $default);
}

function request($index, $default = null) {
    return element($index, $_REQUEST, $default);
}

function session($index, $default = null) {
    return element($index, $_SESSION, $default);
}

function server($index, $default = null) {
    return element($index, $_SERVER, $default);
}

function element($item, array $array, $default = NULL) {
    return array_key_exists($item, $array) ? $array[$item] : $default;
}

function elements($items, array $array, $default = NULL) {
        $return = array();
        is_array($items) OR $items = array($items);
        foreach ($items as $item) {
            $return[$item] = array_key_exists($item, $array) ? $array[$item] : $default;
        }
        return $return;
}

function redirect($uri = '', $method = 'auto', $code = NULL) {
    // IIS environment likely? Use 'refresh' for better compatibility
    if ($method === 'auto' && isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== FALSE) {
        $method = 'refresh';
    }
    elseif ($method !== 'refresh' && (empty($code) OR ! is_numeric($code))) {
        if (isset($_SERVER['SERVER_PROTOCOL'], $_SERVER['REQUEST_METHOD']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1')  {
            $code = ($_SERVER['REQUEST_METHOD'] !== 'GET')
                ? 303   // reference: http://en.wikipedia.org/wiki/Post/Redirect/Get
                : 307;
        } else {
            $code = 302;
        }
    }
    switch ($method) {
        case 'refresh':
            header('Refresh:0;url='.$uri);
            break;
        default:
            header('Location: '.$uri, TRUE, $code);
            break;
    }
    exit;
}
