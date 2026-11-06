<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digital Signage</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-body-tertiary min-vh-100">
<nav class="navbar bg-body border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="./">Digital Signage</a>
        <?php if ($view === 'dashboard'): ?>
            <form method="post" class="m-0 d-flex gap-2">
                <?= csrf_field() ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#settings-modal">Einstellungen</button>
                <button class="btn btn-sm btn-outline-secondary" name="action" value="logout">Abmelden</button>
            </form>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
    <?php foreach ($flashes as [$type, $message]): ?>
        <div class="alert alert-<?= h($type) ?>" role="alert"><?= h($message) ?></div>
    <?php endforeach; ?>
    <?php require __DIR__ . "/$view.php"; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="admin.js"></script>
</body>
</html>
