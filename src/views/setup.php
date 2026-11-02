<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Ersteinrichtung</h1>
                <p class="text-body-secondary">Den Einrichtungsschlüssel findest du auf dem Server in der Datei <code>data/setup-key.txt</code>.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="key">Einrichtungsschlüssel</label>
                        <input class="form-control font-monospace" id="key" name="key" required autocomplete="off" autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Neues Admin-Passwort</label>
                        <input class="form-control" id="password" name="password" type="password" minlength="10" required autocomplete="new-password">
                        <div class="form-text">Mindestens 10 Zeichen.</div>
                    </div>
                    <button class="btn btn-primary w-100">Passwort festlegen</button>
                </form>
            </div>
        </div>
    </div>
</div>
