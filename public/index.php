<?php
// Redirect root to admin dashboard
http_response_code(302);
header('Location: /admin/');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0;url=/admin/">
  <title>Redirecting…</title>
</head>
<body>
  <p>Redirecting to <a href="/admin/">/admin/</a>…</p>
</body>
</html>
