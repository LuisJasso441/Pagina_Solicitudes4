<?php
/**
 * Cargador simple de variables de entorno desde .env
 * Portal de Solicitudes de Atención - Grupo Verden
 *
 * Lee la ruta $env_path (default: raíz del proyecto) y expone cada línea
 * KEY=VALUE como variable de entorno via putenv() para que getenv() la vea.
 *
 * Se ejecuta silenciosamente si el archivo no existe (en Docker desarrollo
 * las variables ya llegan por docker-compose.yml y no hay .env).
 *
 * NO reemplaza variables ya definidas (docker-compose gana sobre .env).
 */

function cargar_env(string $env_path = ''): void
{
    if ($env_path === '') {
        $env_path = DIR_ROOT . '.env';
    }

    if (!is_readable($env_path)) {
        return; // silencioso; en Docker no existe .env
    }

    $lineas = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        // Ignorar comentarios y líneas vacías
        if ($linea === '' || $linea[0] === '#') {
            continue;
        }

        // Debe tener formato KEY=VALUE
        $pos_igual = strpos($linea, '=');
        if ($pos_igual === false) {
            continue;
        }

        $clave = trim(substr($linea, 0, $pos_igual));
        $valor = trim(substr($linea, $pos_igual + 1));

        // Quitar comillas envolventes si las tiene
        if (strlen($valor) >= 2) {
            $primer = $valor[0];
            $ultimo = $valor[strlen($valor) - 1];
            if (($primer === '"' && $ultimo === '"') || ($primer === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        // No sobreescribir variables ya definidas (docker-compose gana)
        if (getenv($clave) === false) {
            putenv("$clave=$valor");
        }
    }
}