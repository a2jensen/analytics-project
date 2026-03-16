<?php
session_start();

require_once __DIR__ . '/app/core/Auth.php';
require_once __DIR__ . '/app/core/Router.php';

// Last-resort safety net — any exception that escapes a controller renders the 500 view
// instead of PHP's default error output.
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    require __DIR__ . '/app/views/errors/500.php';
});

Router::dispatch();
