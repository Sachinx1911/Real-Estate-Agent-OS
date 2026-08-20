<?php
require_once __DIR__ . '/includes/error_page.php';
re360_error_page(500, 'Something went wrong',
    'The server hit an unexpected problem. It has been logged. Please try again in a moment.');
