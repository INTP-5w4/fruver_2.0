<?php
/**
 * Registro (reemplaza la vista de Shield; ver Config\Auth::$views['register']).
 * Antes de crear la cuenta se muestra el aviso de privacidad simplificado y el formato
 * de consentimiento. Lo valida App\Controllers\Auth\RegisterController.
 */
$cfg      = config('Privacidad');
$camposPv  = ['aviso_leido', 'mayor_edad', 'tel_contacto', 'canales', ...array_keys($cfg->finalidades), ...array_keys($cfg->canales)];
$todos     = session('errors');
$todos     = is_array($todos) ? $todos : ($todos !== null ? [(string) $todos] : []);
$erroresPv = array_intersect_key($todos, array_flip($camposPv));
$erroresSh = array_diff_key($todos, $erroresPv);

$enviado = null;
if (session()->has('_ci_old_input')) {
    $enviado = [];
    foreach ($camposPv as $campo) {
        $v = old($campo, null, false);
        if (is_string($v)) {
            $enviado[$campo] = $v;
        }
    }
}
$valores = \App\Libraries\Privacidad\FormularioConsentimiento::valoresFormulario($cfg, null, $enviado);
?>
<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.register') ?> <?= $this->endSection() ?>

<?= $this->section('pageStyles') ?>
    <link rel="stylesheet" href="<?= base_url('estilos/privacidad.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('main') ?>

    <div class="container d-flex justify-content-center p-5">
        <div class="card col-12 col-md-7 shadow-sm">
            <div class="card-body">
                <h1 class="card-title mb-4"><?= lang('Auth.register') ?></h1>

                <?php if (session('error') !== null) : ?>
                    <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
                <?php elseif ($erroresSh !== []) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($erroresSh as $error) : ?>
                            <?= esc($error) ?><br>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
                <?php if ($erroresPv !== []) : ?>
                    <div class="alert alert-danger" role="alert">Revisa la sección de privacidad: falta información para crear tu cuenta.</div>
                <?php endif ?>

                <form action="<?= url_to('register') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="form-floating mb-2">
                        <input type="email" class="form-control" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required>
                        <label for="floatingEmailInput"><?= lang('Auth.email') ?></label>
                    </div>

                    <div class="form-floating mb-4">
                        <input type="text" class="form-control" id="floatingUsernameInput" name="username" inputmode="text" autocomplete="username" placeholder="<?= lang('Auth.username') ?>" value="<?= old('username') ?>" required>
                        <label for="floatingUsernameInput"><?= lang('Auth.username') ?></label>
                    </div>

                    <div class="form-floating mb-2">
                        <input type="password" class="form-control" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="new-password" placeholder="<?= lang('Auth.password') ?>" required>
                        <label for="floatingPasswordInput"><?= lang('Auth.password') ?></label>
                    </div>

                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="floatingPasswordConfirmInput" name="password_confirm" inputmode="text" autocomplete="new-password" placeholder="<?= lang('Auth.passwordConfirm') ?>" required>
                        <label for="floatingPasswordConfirmInput"><?= lang('Auth.passwordConfirm') ?></label>
                    </div>

                    <?= view('privacidad/_resumen', ['cfg' => $cfg]) ?>

                    <?= view('privacidad/_formulario', [
                        'cfg'            => $cfg,
                        'valores'        => $valores,
                        'errores'        => $erroresPv,
                        'conDeclaracion' => true,
                        'correo'         => null,
                    ]) ?>

                    <p class="pv-nota">
                        Al pulsar "Aceptar y crear cuenta" manifiestas tu consentimiento por medios electrónicos.
                        Registramos la fecha y hora, la versión del aviso aceptada y tu dirección IP como constancia.
                    </p>

                    <div class="d-grid col-12 col-md-8 mx-auto m-3">
                        <button type="submit" class="btn btn-primary btn-block">Aceptar y crear cuenta</button>
                    </div>

                    <p class="text-center"><?= lang('Auth.haveAccount') ?> <a href="<?= url_to('login') ?>"><?= lang('Auth.login') ?></a></p>
                </form>
            </div>
        </div>
    </div>

<?= $this->endSection() ?>
