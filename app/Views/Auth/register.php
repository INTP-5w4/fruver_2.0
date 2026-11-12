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

// Errores de Shield que van debajo de su campo; los demás (si hubiera) arriba del formulario.
$camposCuenta = ['email', 'username', 'password', 'password_confirm'];
$erroresCampo = array_intersect_key($erroresSh, array_flip($camposCuenta));
$erroresSh    = array_diff_key($erroresSh, $erroresCampo);
$errorCampo   = static fn (string $campo): string => isset($erroresCampo[$campo])
    ? '<p class="campo-error" id="err-' . $campo . '">' . esc($erroresCampo[$campo]) . '</p>'
    : '';
$describe = static fn (string $campo): string => isset($erroresCampo[$campo])
    ? ' aria-describedby="err-' . $campo . '" aria-invalid="true"'
    : '';

// Reglas de Shield (Config\Auth) que el navegador puede mostrar y revisar mientras se escribe.
$auth        = config('Auth');
$minPassword = (int) $auth->minimumPasswordLength;
$notasPassword = json_encode([
    'No uses tu correo ni tu nombre de usuario',
    'Evita contraseñas comunes (123456, qwerty, tu nombre…)',
    'Una frase de varias palabras es más segura y fácil de recordar',
], JSON_UNESCAPED_UNICODE);
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
                        <input type="email" class="form-control<?= isset($erroresCampo['email']) ? ' campo-con-error' : '' ?>" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email"
                               maxlength="254" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required<?= $describe('email') ?>>
                        <label for="floatingEmailInput"><?= lang('Auth.email') ?></label>
                    </div>
                    <?= $errorCampo('email') ?>

                    <div class="form-floating mb-4">
                        <input type="text" class="form-control<?= isset($erroresCampo['username']) ? ' campo-con-error' : '' ?>" id="floatingUsernameInput" name="username" inputmode="text" autocomplete="username"
                               minlength="3" maxlength="30" pattern="[A-Za-z0-9.]+" data-regla-patron="Solo letras sin acento, números y punto (sin espacios)"
                               placeholder="<?= lang('Auth.username') ?>" value="<?= old('username') ?>" required<?= $describe('username') ?>>
                        <label for="floatingUsernameInput"><?= lang('Auth.username') ?></label>
                    </div>
                    <?= $errorCampo('username') ?>

                    <div class="form-floating mb-2">
                        <input type="password" class="form-control<?= isset($erroresCampo['password']) ? ' campo-con-error' : '' ?>" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="new-password"
                               minlength="<?= $minPassword ?>" data-reglas="<?= esc($notasPassword, 'attr') ?>"
                               placeholder="<?= lang('Auth.password') ?>" required<?= $describe('password') ?>>
                        <label for="floatingPasswordInput"><?= lang('Auth.password') ?></label>
                    </div>
                    <?= $errorCampo('password') ?>

                    <div class="form-floating mb-4">
                        <input type="password" class="form-control<?= isset($erroresCampo['password_confirm']) ? ' campo-con-error' : '' ?>" id="floatingPasswordConfirmInput" name="password_confirm" inputmode="text" autocomplete="new-password"
                               data-coincide="password" placeholder="<?= lang('Auth.passwordConfirm') ?>" required<?= $describe('password_confirm') ?>>
                        <label for="floatingPasswordConfirmInput"><?= lang('Auth.passwordConfirm') ?></label>
                    </div>
                    <?= $errorCampo('password_confirm') ?>

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
