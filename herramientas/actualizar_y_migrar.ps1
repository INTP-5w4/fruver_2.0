<#
.SYNOPSIS
    Pone tu copia de fruver_2.0 al día con la rama y prepara la base de datos.

.DESCRIPTION
    En orden:
      1. Busca git y php (PATH o rutas habituales de XAMPP / Git for Windows).
      2. git fetch --all --prune  (trae lo último de GitHub sin tocar tu copia).
      3. Pone tu rama al día con origin/<rama>:
           - Normal: avanza solo si es fast-forward (no pisa tu trabajo).
           - Con -Sincronizar: deja tu copia EXACTAMENTE igual a GitHub
             (git reset --hard). DESCARTA tus cambios locales en archivos
             versionados. NO borra archivos sin seguimiento (respeta .env y
             tus fotos en public/uploads).
      4. php spark migrate     (aplica TODAS las migraciones pendientes).
      5. php spark db:seed ...  (corre TODOS los seeders de app/Database/Seeds).
         Los seeders cargan datos de DEMOSTRACIÓN y pueden duplicar si se corren
         varias veces, por eso se piden confirmación (usa -Si para no preguntar
         o -SinSeeders para omitirlos).

    No toca .env, vendor/ ni los archivos ignorados por git.

.PARAMETER Ruta
    Carpeta del repositorio. Por omisión se busca sola: carpeta actual, carpeta
    del script (y su carpeta madre) y C:\xampp\htdocs\fruver_2.0.

.PARAMETER Rama
    Rama a poner al día. Por omisión, la rama activa de tu copia.

.PARAMETER Sincronizar
    Deja la copia IGUAL a GitHub (git reset --hard origin/<rama>). Descarta los
    cambios locales en archivos versionados. Pide confirmación (usa -Si para no
    preguntar).

.PARAMETER SinSeeders
    No corre ningún seeder (solo migraciones y actualización de git).

.PARAMETER SinGit
    No hace fetch ni actualiza la rama (solo migraciones y seeders).

.PARAMETER Si
    Responde "sí" automáticamente a la confirmación de los seeders.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\herramientas\actualizar_y_migrar.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\herramientas\actualizar_y_migrar.ps1 -SinSeeders

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\herramientas\actualizar_y_migrar.ps1 -Sincronizar -Si
#>
[CmdletBinding()]
param(
    [string]$Ruta = '',
    [string]$Rama = '',
    [switch]$Sincronizar,
    [switch]$SinSeeders,
    [switch]$SinGit,
    [switch]$Si
)

Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'

$Remoto = 'origin'

# ── Utilidades de salida ─────────────────────────────────────────────────
function Escribir-Paso([string]$Texto)  { Write-Host ''; Write-Host "> $Texto" -ForegroundColor Cyan }
function Escribir-Ok([string]$Texto)    { Write-Host "  [ok] $Texto" -ForegroundColor Green }
function Escribir-Aviso([string]$Texto) { Write-Host "  [!] $Texto" -ForegroundColor Yellow }
function Escribir-Info([string]$Texto)  { Write-Host "      $Texto" }

# ── Localizar git ────────────────────────────────────────────────────────
function Buscar-Git {
    $c = Get-Command git -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $candidatos = @(
        (Join-Path $env:ProgramFiles 'Git\cmd\git.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Git\cmd\git.exe'),
        (Join-Path $env:LOCALAPPDATA 'Programs\Git\cmd\git.exe')
    )
    return ($candidatos | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1)
}

# ── Localizar la carpeta del proyecto ────────────────────────────────────
# Es un repo de fruver_2.0 si tiene 'spark' y '.git'. Se prueba, en orden: lo que
# diga -Ruta, la carpeta actual, la del script (y su madre, por si esta en herramientas\)
# y la instalacion tipica de XAMPP.
function Buscar-Repo([string]$Preferida) {
    $candidatos = @()
    if ($Preferida) {
        $candidatos += $Preferida
    } else {
        $candidatos += (Get-Location).Path
        if ($PSScriptRoot) {
            $candidatos += $PSScriptRoot
            $candidatos += (Split-Path $PSScriptRoot -Parent)
        }
        $candidatos += 'C:\xampp\htdocs\fruver_2.0'
        $candidatos += (Join-Path $env:SystemDrive 'xampp\htdocs\fruver_2.0')
    }
    foreach ($c in $candidatos) {
        if ($c -and (Test-Path (Join-Path $c 'spark')) -and (Test-Path (Join-Path $c '.git'))) {
            return (Resolve-Path $c).Path
        }
    }
    return $null
}

# ── Localizar php (PATH o XAMPP) ─────────────────────────────────────────
function Buscar-Php {
    $c = Get-Command php -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $candidatos = @(
        'C:\xampp\php\php.exe',
        (Join-Path $env:SystemDrive 'xampp\php\php.exe'),
        'C:\php\php.exe'
    )
    return ($candidatos | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1)
}

# git con salida capturada: en Windows PowerShell lo que git manda a stderr no debe
# detener el script; se decide por el código de salida.
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
        throw "Fallo: git $($Argumentos -join ' ')`n$($lineas -join "`n")"
    }
    return [pscustomobject]@{ Codigo = $codigo; Lineas = $lineas; Texto = ($lineas -join "`n") }
}

# spark con salida EN VIVO (para ver el avance de migraciones/seeders).
function Invoke-Spark {
    param([string[]]$Argumentos, [switch]$PermitirFallo)
    $anterior = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & $script:Php (Join-Path $script:Repo 'spark') @Argumentos
        $codigo = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $anterior
    }
    if ($codigo -ne 0 -and -not $PermitirFallo) {
        throw "Fallo: php spark $($Argumentos -join ' ') (codigo $codigo)"
    }
    return $codigo
}

# ── 0. Comprobaciones ────────────────────────────────────────────────────
$script:Git = Buscar-Git
if (-not $script:Git) { throw 'No se encontro git. Instalalo (https://git-scm.com) y vuelve a intentar.' }

$script:Php = Buscar-Php
if (-not $script:Php) { throw 'No se encontro php. Arranca XAMPP o agrega php al PATH.' }

$script:Repo = Buscar-Repo $Ruta
if (-not $script:Repo) {
    throw "No encontre el proyecto fruver_2.0. Indica su carpeta con -Ruta C:\ruta\al\proyecto."
}
$script:Repo = $script:Repo -replace '/', '\'

if (-not (Test-Path (Join-Path $script:Repo 'vendor'))) {
    Escribir-Aviso 'Falta vendor/: corre "composer install" antes de migrar (spark lo necesita).'
}

Write-Host "Repositorio: $script:Repo" -ForegroundColor White
Write-Host "git:         $script:Git"  -ForegroundColor White
Write-Host "php:         $script:Php"  -ForegroundColor White

# ── 1. Actualizar la rama (fetch + fast-forward) ─────────────────────────
if ($SinGit) {
    Escribir-Paso 'Actualizacion de git OMITIDA (-SinGit)'
} else {
    Escribir-Paso 'Trayendo lo ultimo de GitHub (fetch)'
    Invoke-Git @('fetch', '--all', '--prune') | Out-Null
    Escribir-Ok 'fetch completado.'

    if (-not $Rama) {
        $Rama = (Invoke-Git @('symbolic-ref', '--short', '-q', 'HEAD') -PermitirFallo).Texto
        if (-not $Rama) { throw 'HEAD esta separado (sin rama). Indica la rama con -Rama <nombre>.' }
    }

    # La rama tiene que existir en GitHub para poder alinear la copia con ella.
    if (@((Invoke-Git @('rev-parse', '--verify', '-q', "$Remoto/$Rama") -PermitirFallo).Lineas).Count -eq 0) {
        throw "El remoto '$Remoto' no tiene la rama '$Rama'. Revisa el nombre con -Rama."
    }
    $ramaActual = (Invoke-Git @('symbolic-ref', '--short', '-q', 'HEAD') -PermitirFallo).Texto

    if ($Sincronizar) {
        # ── Dejar la copia IGUAL a GitHub (descarta cambios locales versionados) ──
        Escribir-Paso "Dejando tu copia IGUAL a $Remoto/$Rama"
        $sucio = @((Invoke-Git @('status', '--porcelain')).Lineas)
        if ($sucio.Count -gt 0) {
            Escribir-Aviso "Se DESCARTARAN $($sucio.Count) cambio(s) local(es) sin guardar para quedar igual a GitHub."
        }
        $ok = $Si
        if (-not $ok) {
            $resp = Read-Host '  Esto borra tus cambios locales (en archivos versionados). Continuar? (s/N)'
            $ok = ($resp -match '^(s|si|y|yes)$')
        }
        if (-not $ok) {
            Escribir-Info 'Sincronizacion cancelada: no se cambio el codigo.'
        } else {
            if (@((Invoke-Git @('rev-parse', '--verify', '-q', "refs/heads/$Rama") -PermitirFallo).Lineas).Count -gt 0) {
                Invoke-Git @('checkout', '-f', $Rama) | Out-Null
            } else {
                Invoke-Git @('checkout', '-f', '-B', $Rama, "$Remoto/$Rama") | Out-Null
            }
            Invoke-Git @('reset', '--hard', "$Remoto/$Rama") | Out-Null
            Invoke-Git @('branch', '-q', "--set-upstream-to=$Remoto/$Rama", $Rama) -PermitirFallo | Out-Null
            Escribir-Ok ("Copia igual a GitHub: " + (Invoke-Git @('log', '-1', '--format=%h %s (%cr)')).Texto)
            Escribir-Info 'No se borraron archivos sin seguimiento (p. ej. .env ni tus fotos en public/uploads).'
        }
    } else {
        # ── Avance seguro (fast-forward): no pisa el trabajo local ──
        Escribir-Paso "Poniendo la rama '$Rama' en su ultimo punto (solo si es seguro)"
        $sucio = @((Invoke-Git @('status', '--porcelain', '--untracked-files=no')).Lineas)
        if ($sucio.Count -gt 0 -and $ramaActual -eq $Rama) {
            Escribir-Aviso "Tienes $($sucio.Count) archivo(s) con cambios sin commit: no se cambia la rama para no pisarlos."
            Escribir-Info 'Haz commit, guardalos (git stash), o corre con -Sincronizar para quedar igual a GitHub.'
        } else {
            if ($ramaActual -ne $Rama) {
                Invoke-Git @('checkout', $Rama) -PermitirFallo | Out-Null
                if ((Invoke-Git @('symbolic-ref', '--short', '-q', 'HEAD') -PermitirFallo).Texto -ne $Rama) {
                    Escribir-Aviso "No se pudo cambiar a '$Rama' (quiza por cambios sin commit). Se continua en '$ramaActual'."
                }
            }
            $ff = Invoke-Git @('merge', '--ff-only', "$Remoto/$Rama") -PermitirFallo
            if ($ff.Codigo -eq 0) {
                Escribir-Ok ("Rama al dia: " + (Invoke-Git @('log', '-1', '--format=%h %s (%cr)')).Texto)
            } else {
                Escribir-Aviso "No se pudo avanzar a $Remoto/$Rama (la rama local diverge o hay cambios locales)."
                Escribir-Info 'Corre con -Sincronizar para quedar igual a GitHub (descarta cambios locales).'
            }
        }
    }
}

# ── 2. Migraciones ───────────────────────────────────────────────────────
Escribir-Paso 'Aplicando migraciones (php spark migrate)'
Invoke-Spark @('migrate')
Escribir-Ok 'Migraciones al dia.'

# ── 3. Seeders ───────────────────────────────────────────────────────────
if ($SinSeeders) {
    Escribir-Paso 'Seeders OMITIDOS (-SinSeeders)'
} else {
    $seeders = @(Get-ChildItem (Join-Path $script:Repo 'app\Database\Seeds') -Filter '*.php' -ErrorAction SilentlyContinue |
        Sort-Object Name | ForEach-Object { [IO.Path]::GetFileNameWithoutExtension($_.Name) })

    if ($seeders.Count -eq 0) {
        Escribir-Paso 'No hay seeders que correr.'
    } else {
        Escribir-Paso "Seeders encontrados: $($seeders -join ', ')"
        Escribir-Aviso 'Los seeders cargan datos de DEMOSTRACION; correrlos otra vez puede duplicarlos.'

        $correr = $Si
        if (-not $correr) {
            $resp = Read-Host '  Correr los seeders? (s/N)'
            $correr = ($resp -match '^(s|si|y|yes)$')
        }

        if (-not $correr) {
            Escribir-Info 'Seeders omitidos por tu respuesta.'
        } else {
            foreach ($s in $seeders) {
                Escribir-Paso "db:seed $s"
                Invoke-Spark @('db:seed', $s)
                Escribir-Ok "$s listo."
            }
        }
    }
}

# ── Resumen ──────────────────────────────────────────────────────────────
Write-Host ''
Write-Host '==============================================================' -ForegroundColor Cyan
Write-Host ' Listo.' -ForegroundColor Green
if (-not $SinGit) { Write-Host (" Rama: " + (Invoke-Git @('log', '-1', '--format=%h %s')).Texto) -ForegroundColor White }
Write-Host '==============================================================' -ForegroundColor Cyan
