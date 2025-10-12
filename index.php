<?php

/**
 * Root index redirect
 * 
 * For development convenience, redirect root requests to the admin interface.
 * In production, you might want to serve a different page or remove this file.
 */

header('Location: /admin/');
exit;