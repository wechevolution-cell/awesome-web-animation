<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($code) ?> — <?= e($title) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-body">
<main class="auth-card" id="main">
  <p class="eyebrow">Error <?= e($code) ?></p>
  <h1><?= e($title) ?></h1>
  <p class="muted"><?= e($text) ?></p>
  <p><a class="btn" href="/">Go to homepage</a></p>
</main>
</body>
</html>
