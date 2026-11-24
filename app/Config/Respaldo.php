<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Respaldos de la base de datos (Aviso de Privacidad, sección 10: "Respaldos periódicos").
 * Uso: php spark respaldo:crear   (programarlo a diario; ver docs/PRIVACIDAD.md)
 *
 * Se puede cambiar desde .env:  respaldo.mysqldump, respaldo.carpeta, respaldo.diasConservar
 */
class Respaldo extends BaseConfig
{
    /** Ruta de mysqldump. Vacío = la de XAMPP en Windows, o "mysqldump" del PATH. */
    public string $mysqldump = '';

    /** Carpeta de respaldos. Vacío = writable/respaldos (fuera de public/, protegida por .htaccess). */
    public string $carpeta = '';

    /** Días que se conservan; los más viejos se borran al crear uno nuevo (mínimo 1). */
    public int $diasConservar = 30;

    public function rutaMysqldump(): string
    {
        if (trim($this->mysqldump) !== '') {
            return $this->mysqldump;
        }

        $xampp = 'C:\xampp\mysql\bin\mysqldump.exe';

        return is_file($xampp) ? $xampp : 'mysqldump';
    }

    public function rutaCarpeta(): string
    {
        return rtrim(trim($this->carpeta) !== '' ? $this->carpeta : WRITEPATH . 'respaldos', '\\/') . DIRECTORY_SEPARATOR;
    }
}
