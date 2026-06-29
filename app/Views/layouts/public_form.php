<?php

declare(strict_types=1);

$pageTitle = isset($title) ? (string) $title : 'LegalOPS Cloud';
$csrf = isset($csrfToken) ? (string) $csrfToken : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($pageTitle) ?> · LegalOPS Cloud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        :root{--legal-navy:#082754;--legal-green:#087b4f;--legal-muted:#667085;--legal-border:#d8e0e9;--legal-bg:#f4f7fa}
        body{min-height:100vh;background:var(--legal-bg);color:var(--legal-navy);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
        .public-shell{min-height:100vh;position:relative;overflow:hidden}
        .public-shell::before{content:"";position:fixed;inset:0 auto 0 0;width:clamp(18px,7vw,110px);background:var(--legal-navy);clip-path:polygon(0 0,100% 0,58% 50%,100% 100%,0 100%);z-index:-1}
        .public-container{width:min(1120px,calc(100% - 2rem));margin-inline:auto;padding:3rem 0}
        .brand-wordmark{font-family:Georgia,"Times New Roman",serif;font-weight:700;letter-spacing:-.02em;color:var(--legal-navy)}
        .public-panel{background:#fff;border:1px solid var(--legal-border);border-radius:14px;box-shadow:0 14px 36px rgba(8,39,84,.07)}
        .form-label{font-weight:650;color:#172b4d}.form-control,.form-select{border-color:#cfd8e3;min-height:46px}
        .form-control:focus,.form-select:focus{border-color:var(--legal-green);box-shadow:0 0 0 .2rem rgba(8,123,79,.12)}
        .btn-legal{background:var(--legal-green);border-color:var(--legal-green);color:#fff;font-weight:650}
        .btn-legal:hover,.btn-legal:focus{background:#06653f;border-color:#06653f;color:#fff}
        .text-muted-legal{color:var(--legal-muted)}.success-panel{border:1px solid #9fd9c0;background:#f0fbf6;color:#075f3e}
        @media(max-width:767.98px){.public-shell::before{width:8px;clip-path:none}.public-container{padding:1.25rem 0}.public-panel{border-radius:10px}}
    </style>
</head>
<body>
<main class="public-shell">
    <div class="public-container"><?= $content ?></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php if (!empty($publicScript)): ?>
    <script src="<?= e((string) $publicScript) ?>"></script>
<?php endif; ?>
</body>
</html>
