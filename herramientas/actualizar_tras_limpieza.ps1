<#
.SYNOPSIS
    Pone tu copia de fruver_2.0 al día después de la limpieza del historial del 22/11/2026.

.DESCRIPTION
    El 22/11/2026 se reescribió el historial del repositorio en GitHub para borrar dos volcados
    con datos personales (fruver.sql y entrada.sql). Todos los commits cambiaron de identificador,
    así que una copia anterior ya no coincide con GitHub. NO hagas "git pull" ni "git push"
    con tu copia vieja: volverías a subir esos datos.

    Este script, en tu copia:
      1. Respalda TODO en Documentos\fruver_respaldo_<fecha> (fuera de htdocs, para que el
         servidor web no lo publique): un bundle de git con todas tus ramas, tus cambios sin
         commit y tus commits sin subir como parches.
      2. Guarda tus cambios sin commit (stash).
      3. Trae el historial limpio de GitHub.
      4. Reconstruye cada rama local encima de su versión limpia y le vuelve a aplicar tus
         commits que no habías subido (sin los dos archivos retirados).
      5. Te deja en la rama del remoto con el cambio más reciente y te devuelve tus cambios
         sin commit.
      6. Borra de tu copia los objetos viejos (los datos personales ya no quedan en .git).

    No toca .env, vendor/ ni writable/ (archivos ignorados por git).

.PARAMETER Ruta
    Carpeta del repositorio. Por omisión, la carpeta actual.

.PARAMETER Rama
    Rama que quieres dejar activa. Por omisión, la rama de GitHub con el commit más reciente.

.PARAMETER SoloRevisar
    Muestra lo que haría (cambios sin commit, commits sin subir, ramas) sin cambiar nada.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\herramientas\actualizar_tras_limpieza.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File actualizar_tras_limpieza.ps1 -Ruta C:\xampp\htdocs\fruver_2.0 -SoloRevisar
#>
[CmdletBinding()]
param(
    [string]$Ruta = (Get-Location).Path,
    [string]$Rama = '',
    [switch]$SoloRevisar
)

Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'

$Remoto            = 'origin'
$ArchivosRetirados = @('fruver.sql', 'entrada.sql')
$Separador         = "`t"

# ── Utilidades ───────────────────────────────────────────────────────────
function Escribir-Paso([string]$Texto)  { Write-Host ''; Write-Host "▶ $Texto" -ForegroundColor Cyan }
function Escribir-Ok([string]$Texto)    { Write-Host "  ✓ $Texto" -ForegroundColor Green }
function Escribir-Aviso([string]$Texto) { Write-Host "  ! $Texto" -ForegroundColor Yellow }
function Escribir-Info([string]$Texto)  { Write-Host "    $Texto" }

# git con salida capturada. En Windows PowerShell, lo que git escribe en stderr no debe
# detener el script: se decide por el código de salida.
function Invoke-Git {
    param([string[]]$Argumentos, [switch]$PermitirFallo)
    $anterior = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $salida = & $script:Git -C $script:Repo @Argumentos 2>&1
        $codigo = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $anterior
    }
    $lineas = @($salida | ForEach-Object { "$_" } | Where-Object { $_ -ne '' })
    if ($codigo -ne 0 -and -not $PermitirFallo) {
        throw "Falló: git $($Argumentos -join ' ')`n$($lineas -join "`n")"
    }
    return [pscustomobject]@{ Codigo = $codigo; Lineas = $lineas; Texto = ($lineas -join "`n") }
}

function Lineas-Git([string[]]$Argumentos) { return @((Invoke-Git $Argumentos).Lineas) }

# Huella de un commit que sobrevive a la reescritura: fecha y correo del autor + asunto.
function Huella([string]$Commit) {
    return (Invoke-Git @('log', '-1', "--format=%at$Separador%ae$Separador%s", $Commit)).Texto
}

# ── 0. Comprobaciones ────────────────────────────────────────────────────
# git: el del PATH o el de las rutas de instalación habituales (Git for Windows, GitHub Desktop).
$script:Git = $null
$comando = Get-Command git -ErrorAction SilentlyContinue
if ($comando) { $script:Git = $comando.Source }
if (-not $script:Git) {
    $candidatos = @(
        (Join-Path $env:ProgramFiles 'Git\cmd\git.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Git\cmd\git.exe'),
        (Join-Path $env:LOCALAPPDATA 'Programs\Git\cmd\git.exe')
    )
    $candidatos += @(Get-ChildItem (Join-Path $env:LOCALAPPDATA 'GitHubDesktop\app-*\resources\app\git\cmd\git.exe') -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | ForEach-Object { $_.FullName })
    $script:Git = $candidatos | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}
if (-not $script:Git) { throw 'No se encontró git. Instálalo (https://git-scm.com) y vuelve a intentar.' }

$script:Repo = (Resolve-Path $Ruta).Path
if ((Invoke-Git @('rev-parse', '--is-inside-work-tree') -PermitirFallo).Texto -ne 'true') {
    throw "La carpeta $script:Repo no es un repositorio de git."
}
$script:Repo = (Invoke-Git @('rev-parse', '--show-toplevel')).Texto -replace '/', '\'
$gitDir = (Invoke-Git @('rev-parse', '--absolute-git-dir')).Texto

$url = (Invoke-Git @('remote', 'get-url', $Remoto) -PermitirFallo).Texto
if (-not $url) { throw "El repositorio no tiene el remoto '$Remoto'." }
if ($url -notmatch 'fruver_2\.0') { Escribir-Aviso "El remoto '$Remoto' es $url (se esperaba fruver_2.0). Revisa que sea la carpeta correcta." }

foreach ($marca in @('MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'rebase-merge', 'rebase-apply')) {
    if (Test-Path (Join-Path $gitDir $marca)) {
        throw "Hay una operación de git a medias ($marca). Termínala o cancélala (git merge --abort / git rebase --abort) y vuelve a correr el script."
    }
}

Write-Host "Repositorio: $script:Repo" -ForegroundColor White
Write-Host "Remoto:      $url" -ForegroundColor White

# ── 1. Inventario (antes de traer nada) ──────────────────────────────────
Escribir-Paso 'Revisando tu copia'
$ramaActual = (Invoke-Git @('symbolic-ref', '--short', '-q', 'HEAD') -PermitirFallo).Texto
$sucio      = @(Lineas-Git @('status', '--porcelain', '--untracked-files=all'))
$ramas      = @(Lineas-Git @('for-each-ref', '--format=%(refname:short)', 'refs/heads'))

$plan = @()
foreach ($r in $ramas) {
    $tip    = (Invoke-Git @('rev-parse', $r)).Texto
    $unicos = @(Lineas-Git @('rev-list', '--reverse', '--no-merges', $r, '--not', '--remotes'))
    $todos  = @(Lineas-Git @('rev-list', '--reverse', $r, '--not', '--remotes'))
    $base   = $tip
    if ($todos.Count -gt 0) {
        $base = (Invoke-Git @('rev-parse', "$($todos[0])^") -PermitirFallo).Texto
        if ($base -match '\s|^\^' -or -not $base) { $base = '' }
    }
    $plan += [pscustomobject]@{
        Rama       = $r
        Tip        = $tip
        Base       = $base
        HuellaBase = $(if ($base) { Huella $base } else { '' })
        Unicos     = $unicos
        Uniones    = $todos.Count - $unicos.Count
    }
}

Escribir-Info "Rama actual: $(if ($ramaActual) { $ramaActual } else { '(ninguna, HEAD separado)' })"
Escribir-Info "Cambios sin commit: $($sucio.Count) archivo(s)"
foreach ($p in $plan) {
    $txt = "Rama $($p.Rama): $($p.Unicos.Count) commit(s) sin subir"
    if ($p.Uniones -gt 0) { $txt += " y $($p.Uniones) merge(s) (se aplicarán sus cambios en línea)" }
    Escribir-Info $txt
}
$stashes = @(Lineas-Git @('stash', 'list'))
if ($stashes.Count -gt 0) { Escribir-Info "Tienes $($stashes.Count) stash guardado(s) de antes." }

if ($SoloRevisar) {
    Write-Host ''
    Write-Host 'Modo -SoloRevisar: no se cambió nada.' -ForegroundColor Yellow
    return
}

# ── 2. Respaldo fuera del proyecto ───────────────────────────────────────
Escribir-Paso 'Respaldando todo antes de cambiar nada'
$respaldo = Join-Path ([Environment]::GetFolderPath('MyDocuments')) ("fruver_respaldo_" + (Get-Date -Format 'yyyyMMdd_HHmmss'))
New-Item -ItemType Directory -Path $respaldo -Force | Out-Null
Invoke-Git @('bundle', 'create', (Join-Path $respaldo 'todas_las_ramas.bundle'), '--all') | Out-Null
Escribir-Ok "Bundle con todas tus ramas: $respaldo\todas_las_ramas.bundle"

foreach ($p in $plan) {
    if ($p.Unicos.Count -eq 0) { continue }
    $dir = Join-Path $respaldo ("parches\" + ($p.Rama -replace '[\\/:*?"<>|]', '_'))
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
    $n = 1
    foreach ($c in $p.Unicos) {
        Invoke-Git @('format-patch', '-1', $c, '-o', $dir, '--start-number', "$n") | Out-Null
        $n++
    }
}
if (@($plan | Where-Object { $_.Unicos.Count -gt 0 }).Count -gt 0) { Escribir-Ok "Tus commits sin subir, como parches: $respaldo\parches" }

for ($i = 0; $i -lt $stashes.Count; $i++) {
    $parche = Invoke-Git @('stash', 'show', '-p', '--include-untracked', "stash@{$i}") -PermitirFallo
    if ($parche.Codigo -ne 0) { $parche = Invoke-Git @('stash', 'show', '-p', "stash@{$i}") -PermitirFallo }
    Set-Content -Path (Join-Path $respaldo "stash_$i.patch") -Value $parche.Texto -Encoding UTF8
}

$guardeStash = $false
if ($sucio.Count -gt 0) {
    Invoke-Git @('diff', 'HEAD', '--binary') | ForEach-Object { Set-Content -Path (Join-Path $respaldo 'cambios_sin_commit.patch') -Value $_.Texto -Encoding UTF8 }
    Invoke-Git @('stash', 'push', '--include-untracked', '-m', 'actualizar_tras_limpieza: cambios sin commit') | Out-Null
    $guardeStash = $true
    Escribir-Ok 'Tus cambios sin commit quedaron guardados (stash) y en cambios_sin_commit.patch.'
}

# ── 3. Historial limpio ──────────────────────────────────────────────────
Escribir-Paso 'Trayendo el historial limpio de GitHub'
Invoke-Git @('fetch', $Remoto, '--prune', '--force', "+refs/heads/*:refs/remotes/$Remoto/*", '+refs/tags/*:refs/tags/*') | Out-Null

$conDatos = @(Lineas-Git (@('rev-list', '--remotes=' + $Remoto, '--') + $ArchivosRetirados))
if ($conDatos.Count -gt 0) {
    if ($guardeStash) { Invoke-Git @('stash', 'pop') -PermitirFallo | Out-Null }
    throw "GitHub todavía tiene los archivos retirados en el historial. No se cambió tu copia (respaldo en $respaldo). Avisa a quien hizo la limpieza."
}
Escribir-Ok 'GitHub ya no tiene fruver.sql ni entrada.sql en ninguna rama.'

$remotas = @(Lineas-Git @('for-each-ref', '--sort=-committerdate', '--format=%(refname:lstrip=3)', "refs/remotes/$Remoto") | Where-Object { $_ -ne 'HEAD' })
if ($remotas.Count -eq 0) { throw 'El remoto no tiene ramas.' }
if (-not $Rama) { $Rama = $remotas[0] }
if ($remotas -notcontains $Rama -and $ramas -notcontains $Rama) { throw "La rama '$Rama' no existe ni en GitHub ni en tu copia." }
Escribir-Info "Rama con el cambio más reciente en GitHub: $($remotas[0])"

# Commits nuevos indexados por su huella, para ubicar dónde empezaba cada rama local.
$mapa = @{}
foreach ($l in (Lineas-Git @('log', "--remotes=$Remoto", "--format=%H$Separador%at$Separador%ae$Separador%s"))) {
    $partes = $l -split "`t", 2   # (.Split([char]9, 2) en PowerShell 5.1 tomaría el 2 como otro separador)
    if (-not $mapa.ContainsKey($partes[1])) { $mapa[$partes[1]] = $partes[0] }
}

# ── 4. Reconstruir ramas ─────────────────────────────────────────────────
Escribir-Paso 'Reconstruyendo tus ramas sobre el historial limpio'
Invoke-Git @('checkout', '-f', '--detach', "refs/remotes/$Remoto/$($remotas[0])") | Out-Null
$pendientes = @()

foreach ($p in $plan) {
    $enRemoto = $remotas -contains $p.Rama
    if ($enRemoto) {
        $nuevaBase = "refs/remotes/$Remoto/$($p.Rama)"
    } elseif ($p.HuellaBase -and $mapa.ContainsKey($p.HuellaBase)) {
        $nuevaBase = $mapa[$p.HuellaBase]
    } else {
        Invoke-Git @('branch', '-D', $p.Rama) | Out-Null
        Escribir-Aviso "Rama $($p.Rama): no se encontró su punto de partida en el historial limpio. Se quitó; sus commits están en el respaldo."
        $pendientes += $p.Rama
        continue
    }

    Invoke-Git @('checkout', '-f', '-B', $p.Rama, $nuevaBase) | Out-Null
    $aplicados = 0
    $fallo = $false
    foreach ($c in $p.Unicos) {
        $r = Invoke-Git @('cherry-pick', '--no-commit', $c) -PermitirFallo
        if ($r.Codigo -ne 0) {
            # Si lo único que choca son los archivos retirados (el commit los modificaba y en el
            # historial limpio ya no existen), se quitan y se conserva el resto del commit.
            $conflictos = @(Lineas-Git @('diff', '--name-only', '--diff-filter=U'))
            $ajenos     = @($conflictos | Where-Object { $ArchivosRetirados -notcontains $_ })
            if ($conflictos.Count -eq 0 -or $ajenos.Count -gt 0) {
                Invoke-Git @('cherry-pick', '--abort') -PermitirFallo | Out-Null
                Invoke-Git @('reset', '--hard', 'HEAD') | Out-Null
                $fallo = $true
                break
            }
        }
        Invoke-Git (@('rm', '-q', '--cached', '--ignore-unmatch', '--') + $ArchivosRetirados) | Out-Null
        Invoke-Git @('cherry-pick', '--quit') -PermitirFallo | Out-Null
        foreach ($a in $ArchivosRetirados) {
            $ruta = Join-Path $script:Repo $a
            if (Test-Path $ruta) { Move-Item $ruta (Join-Path $respaldo $a) -Force }
        }
        if ((Invoke-Git @('diff', '--cached', '--quiet') -PermitirFallo).Codigo -ne 0) {
            Invoke-Git @('commit', '-q', '--no-verify', '-C', $c) | Out-Null
        }
        $aplicados++
    }
    if ($enRemoto) { Invoke-Git @('branch', '-q', "--set-upstream-to=$Remoto/$($p.Rama)", $p.Rama) -PermitirFallo | Out-Null }

    if ($fallo) {
        Escribir-Aviso "Rama $($p.Rama): se aplicaron $aplicados de $($p.Unicos.Count) commit(s); el siguiente choca con cambios nuevos. Aplícalos a mano desde $respaldo\parches."
        $pendientes += $p.Rama
    } elseif ($p.Unicos.Count -gt 0) {
        Escribir-Ok "Rama $($p.Rama): al día y con tus $aplicados commit(s) sin subir reaplicados."
    } else {
        Escribir-Ok "Rama $($p.Rama): al día."
    }
}

# ── 5. Dejar la rama más reciente activa ─────────────────────────────────
Escribir-Paso "Activando la rama $Rama"
if (@(Lineas-Git @('for-each-ref', '--format=%(refname:short)', "refs/heads/$Rama")).Count -gt 0) {
    Invoke-Git @('checkout', '-f', $Rama) | Out-Null
} else {
    Invoke-Git @('checkout', '-f', '-B', $Rama, "refs/remotes/$Remoto/$Rama") | Out-Null
    Invoke-Git @('branch', '-q', "--set-upstream-to=$Remoto/$Rama", $Rama) | Out-Null
}
Escribir-Ok ("Rama activa: $Rama · " + (Invoke-Git @('log', '-1', '--format=%h %s (%cr)')).Texto)

if ($guardeStash) {
    $pop = Invoke-Git @('stash', 'pop') -PermitirFallo
    if ($pop.Codigo -eq 0) {
        Escribir-Ok 'Tus cambios sin commit volvieron a su lugar.'
    } else {
        Invoke-Git @('reset', '--hard', 'HEAD') -PermitirFallo | Out-Null
        Escribir-Aviso "Tus cambios sin commit chocan con la versión nueva. Siguen guardados en el stash y en $respaldo\cambios_sin_commit.patch."
        $pendientes += 'cambios sin commit'
    }
}

foreach ($a in $ArchivosRetirados) {
    $ruta = Join-Path $script:Repo $a
    if (Test-Path $ruta) { Move-Item $ruta (Join-Path $respaldo $a) -Force; Escribir-Info "Se movió $a al respaldo." }
}

# ── 6. Borrar los objetos viejos de .git ─────────────────────────────────
Escribir-Paso 'Borrando de tu copia el historial viejo'
$quedanStash = @(Lineas-Git @('stash', 'list')).Count
Invoke-Git @('reflog', 'expire', '--expire=now', '--all') | Out-Null
Invoke-Git @('gc', '--prune=now', '--quiet') | Out-Null
$restos = @(Lineas-Git (@('rev-list', '--all', '--') + $ArchivosRetirados))
if ($restos.Count -eq 0) {
    Escribir-Ok 'Tu copia ya no contiene fruver.sql ni entrada.sql en ningún commit.'
} else {
    Escribir-Aviso "Quedan $($restos.Count) commit(s) viejos con esos archivos, retenidos por tus $quedanStash stash(es) anteriores. Revísalos ($respaldo\stash_*.patch) y bórralos con: git stash clear; git gc --prune=now"
}

# ── Resumen ──────────────────────────────────────────────────────────────
Write-Host ''
Write-Host '══════════════════════════════════════════════════════════════' -ForegroundColor Cyan
Write-Host " Listo. Estás en '$Rama', igual que GitHub." -ForegroundColor Green
if ($pendientes.Count -gt 0) {
    Write-Host " Revisa a mano: $($pendientes -join ', ')." -ForegroundColor Yellow
}
Write-Host " Respaldo: $respaldo" -ForegroundColor White
Write-Host ' El respaldo contiene el historial VIEJO (con los datos retirados). Cuando confirmes' -ForegroundColor White
Write-Host ' que no te falta nada, bórralo. No lo subas ni lo copies a htdocs.' -ForegroundColor White
if (-not (Test-Path (Join-Path $script:Repo 'vendor'))) { Write-Host ' Falta vendor/: corre "composer install".' -ForegroundColor Yellow }
Write-Host '══════════════════════════════════════════════════════════════' -ForegroundColor Cyan
