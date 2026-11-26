<#
.SYNOPSIS
    Pone tu copia de fruver_2.0 al día con la rama y prepara la base de datos.

.DESCRIPTION
    En orden:
      1. Busca git y php (PATH o rutas habituales de XAMPP / Git for Windows).
      2. git fetch --all --prune  (trae lo último de GitHub sin tocar tu copia).
      3. Deja tu rama en "este punto de la rama": avanza (fast-forward) a
         origin/<rama>. Si tienes cambios sin commit o la rama ha divergido,
         NO fuerza nada: te avisa y sigue con los siguientes pasos.
      4. php spark migrate     (aplica TODAS las migraciones pendientes).
      5. php spark db:seed ...  (corre TODOS los seeders de app/Database/Seeds).
         Los seeders cargan datos de DEMOSTRACIÓN y pueden duplicar si se corren
         varias veces, por eso se piden confirmación (usa -Si para no preguntar
         o -SinSeeders para omitirlos).

    No toca .env, vendor/ ni los archivos ignorados por git.

.PARAMETER Ruta
    Carpeta del repositorio. Por omisión, la carpeta actual.

.PARAMETER Rama
    Rama a poner al día. Por omisión, la rama activa de tu copia.

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
    powershell -ExecutionPolicy Bypass -File .\herramientas\actualizar_y_migrar.ps1 -Rama mejoras-inventario-rutas-roles -Si
#>
[CmdletBinding()]
param(
    [string]$Ruta = (Get-Location).Path,
    [string]$Rama = '',
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

$script:Repo = (Resolve-Path $Ruta).Path
if ((Invoke-Git @('rev-parse', '--is-inside-work-tree') -PermitirFallo).Texto -ne 'true') {
    throw "La carpeta $script:Repo no es un repositorio de git."
}
$script:Repo = (Invoke-Git @('rev-parse', '--show-toplevel')).Texto -replace '/', '\'

if (-not (Test-Path (Join-Path $script:Repo 'spark'))) {
    throw "No se encontro 'spark' en $script:Repo. No parece la raiz de fruver_2.0."
}
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

    Escribir-Paso "Poniendo la rama '$Rama' en su ultimo punto"
    $sucio = @((Invoke-Git @('status', '--porcelain', '--untracked-files=no')).Lineas)
    $ramaActual = (Invoke-Git @('symbolic-ref', '--short', '-q', 'HEAD') -PermitirFallo).Texto

    if ($sucio.Count -gt 0 -and $ramaActual -eq $Rama) {
        Escribir-Aviso "Tienes $($sucio.Count) archivo(s) con cambios sin commit: no se cambia la rama para no pisarlos."
        Escribir-Info 'Haz commit o guardalos (git stash) y vuelve a correr el script, o usa -SinGit.'
    } else {
        # Asegurar que exista la rama de seguimiento del remoto.
        if (@((Invoke-Git @('rev-parse', '--verify', '-q', "$Remoto/$Rama") -PermitirFallo).Lineas).Count -eq 0) {
            throw "El remoto '$Remoto' no tiene la rama '$Rama'. Revisa el nombre."
        }
        if ($ramaActual -ne $Rama) {
            Invoke-Git @('checkout', $Rama) -PermitirFallo | Out-Null
            if ((Invoke-Git @('symbolic-ref', '--short', '-q', 'HEAD') -PermitirFallo).Texto -ne $Rama) {
                Escribir-Aviso "No se pudo cambiar a '$Rama' (quiza por cambios sin commit). Se continua en '$ramaActual'."
            }
        }
        # Fast-forward: solo avanza si no hay divergencia (no reescribe tu trabajo).
        $ff = Invoke-Git @('merge', '--ff-only', "$Remoto/$Rama") -PermitirFallo
        if ($ff.Codigo -eq 0) {
            Escribir-Ok ("Rama al dia: " + (Invoke-Git @('log', '-1', '--format=%h %s (%cr)')).Texto)
        } else {
            Escribir-Aviso "No se pudo avanzar a $Remoto/$Rama (la rama local diverge o hay cambios locales)."
            Escribir-Info 'Resuelvelo a mano (git pull / git rebase) y vuelve a correr. Se continua con migraciones y seeders.'
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
