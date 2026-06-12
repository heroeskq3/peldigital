<?php
$rootDir = __DIR__;
require $rootDir . '/auth.php';
requerirLogin();

$pageTitle = 'Mi perfil · PEL Digital';
$bodyClass = 'page-perfil';

require $rootDir . '/includes/layout/head.php';
require $rootDir . '/includes/layout/header.php';
?>
<main class="perfil-main">
    <div class="perfil-wrap">

        <header class="perfil-page-header">
            <i class="bi bi-person-circle perfil-page-icon"></i>
            <div>
                <h1 class="perfil-page-title">Mi perfil</h1>
                <p class="perfil-page-sub">Gestiona tu información y acceso</p>
            </div>
        </header>

        <!-- Información personal -->
        <section class="perfil-card">
            <h2 class="perfil-card-title"><i class="bi bi-person"></i> Información personal</h2>
            <form id="formInfo" class="perfil-form" novalidate>
                <label class="perfil-label">Nombre
                    <input type="text" name="name" id="fieldName" class="field"
                           value="<?= htmlspecialchars($_SESSION['usuario'] ?? '') ?>" required>
                </label>
                <label class="perfil-label">Correo electrónico
                    <input type="email" name="email" id="fieldEmail" class="field"
                           value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" required>
                </label>
                <div id="infoMsg" class="perfil-msg" hidden></div>
                <button type="submit" class="perfil-btn">
                    <i class="bi bi-check-lg"></i> Guardar cambios
                </button>
            </form>
        </section>

        <!-- Cambiar contraseña -->
        <section class="perfil-card" id="contrasena">
            <h2 class="perfil-card-title"><i class="bi bi-key"></i> Cambiar contraseña</h2>
            <form id="formPw" class="perfil-form" novalidate>
                <label class="perfil-label">Contraseña actual
                    <div class="field-pw">
                        <input type="password" name="current" id="fCurrent" class="field" required>
                        <button type="button" class="field-pw-toggle"
                                onclick="togglePwField('fCurrent','icoCurrent')">
                            <i class="bi bi-eye" id="icoCurrent"></i>
                        </button>
                    </div>
                </label>
                <label class="perfil-label">Nueva contraseña
                    <div class="field-pw">
                        <input type="password" name="new" id="fNew" class="field" required minlength="8">
                        <button type="button" class="field-pw-toggle"
                                onclick="togglePwField('fNew','icoNew')">
                            <i class="bi bi-eye" id="icoNew"></i>
                        </button>
                    </div>
                </label>
                <label class="perfil-label">Confirmar nueva contraseña
                    <div class="field-pw">
                        <input type="password" name="confirm" id="fConfirm" class="field" required minlength="8">
                        <button type="button" class="field-pw-toggle"
                                onclick="togglePwField('fConfirm','icoConfirm')">
                            <i class="bi bi-eye" id="icoConfirm"></i>
                        </button>
                    </div>
                </label>
                <div id="pwMsg" class="perfil-msg" hidden></div>
                <button type="submit" class="perfil-btn">
                    <i class="bi bi-shield-lock"></i> Cambiar contraseña
                </button>
            </form>
        </section>

    </div>
</main>

<?php require $rootDir . '/includes/layout/footer.php'; ?>

<script>
function togglePwField(fid, iid) {
    var f = document.getElementById(fid), i = document.getElementById(iid);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function showMsg(el, text, ok) {
    el.hidden = false;
    el.textContent = text;
    el.className = 'perfil-msg ' + (ok ? 'perfil-msg-ok' : 'perfil-msg-err');
}

document.getElementById('formInfo').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msg  = document.getElementById('infoMsg');
    const btn  = this.querySelector('button[type=submit]');
    const data = new FormData(this);
    data.append('action', 'update_info');
    btn.disabled = true;
    try {
        const r = await fetch(window.APP_BASE + 'api/profile.php', { method: 'POST', body: data });
        const j = await r.json();
        if (j.ok) {
            showMsg(msg, '✓ Perfil actualizado correctamente.', true);
            // Actualizar avatar inicial en el user-menu si está en el DOM
            const av = document.querySelector('.user-menu-avatar');
            const nm = document.querySelector('.user-menu-name');
            const em = document.querySelector('.user-menu-email');
            if (av) av.textContent = j.name.charAt(0).toUpperCase();
            if (nm) nm.textContent = j.name;
            if (em) em.textContent = j.email;
        } else {
            showMsg(msg, j.error || 'Error al actualizar.', false);
        }
    } catch { showMsg(msg, 'Error de conexión.', false); }
    btn.disabled = false;
});

document.getElementById('formPw').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msg  = document.getElementById('pwMsg');
    const btn  = this.querySelector('button[type=submit]');
    const data = new FormData(this);
    data.append('action', 'change_password');
    btn.disabled = true;
    try {
        const r = await fetch(window.APP_BASE + 'api/profile.php', { method: 'POST', body: data });
        const j = await r.json();
        if (j.ok) {
            showMsg(msg, '✓ Contraseña cambiada correctamente.', true);
            this.reset();
        } else {
            showMsg(msg, j.error || 'Error al cambiar contraseña.', false);
        }
    } catch { showMsg(msg, 'Error de conexión.', false); }
    btn.disabled = false;
});

// Scroll a la sección de contraseña si viene con #contrasena
if (window.location.hash === '#contrasena') {
    document.getElementById('contrasena')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<?php require $rootDir . '/includes/layout/scripts.php'; ?>
