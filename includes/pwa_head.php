<link rel="manifest" href="<?= BASE_PATH ?>/manifest.json">
<meta name="theme-color" content="#2C377F">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Última Estação">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="icon" type="image/png" sizes="192x192" href="<?= BASE_PATH ?>/assets/img/icons/icon-192.png">
<link rel="apple-touch-icon" href="<?= BASE_PATH ?>/assets/img/icons/apple-touch-icon.png">
<?php $css_v = (int)@filemtime(__DIR__ . '/../assets/css/app.css'); ?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css?v=<?= $css_v ?>">