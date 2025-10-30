<?php
/**
 * Prevenir acceso directo a uploads
 */
header('HTTP/1.0 403 Forbidden');
die('Acceso denegado');
?>