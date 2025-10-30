<?php
// Generar hash para contraseña
$password = 'labora1234567890';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash para '$password': <br>";
echo $hash;
?>