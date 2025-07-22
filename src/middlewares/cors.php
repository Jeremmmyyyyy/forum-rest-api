<?php
/*
 * Copyright (c) 2023. Hadrien Sevel
 * Project: forum-rest-api
 * File: cors.php
 */

/**
 * Set the Cross-Origin Resource Sharing (CORS) headers
 * @return void
 */
function setCorsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Allow if origin is in the list or if there is no origin (direct browser request)
    if ($origin === '' || in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // 24 hours
    } else {
        // Log the forbidden origin
        error_log('CORS forbidden for Origin: "' . $origin . '" on ' . $_SERVER['REQUEST_URI']);
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(array(
            'error' => 'Forbidden: CORS'
        )));
    }
}