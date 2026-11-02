<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Anmelden</h1>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="password">Passwort</label>
                        <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password" autofocus>
                    </div>
                    <button class="btn btn-primary w-100">Anmelden</button>
                </form>
            </div>
        </div>
    </div>
</div>
