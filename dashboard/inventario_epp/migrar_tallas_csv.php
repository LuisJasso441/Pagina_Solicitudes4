<?php
/**
 * Script de migración: Tallas CSV → registros individuales
 * Ejecutar UNA SOLA VEZ después de epp_tallas_migracion.sql
 * 
 * Uso: http://localhost/Pagina_Solicitudes4/dashboard/inventario_epp/migrar_tallas_csv.php
 * O desde CLI: php migrar_tallas_csv.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = conectarDB();

echo "<h2>Migración de Tallas CSV → Registros individuales</h2>";

// Obtener artículos con tallas separadas por coma
$stmt = $pdo->query("
    SELECT id, articulo, talla, stock 
    FROM inventario_epp 
    WHERE activo = 1 
      AND talla IS NOT NULL 
      AND talla != '' 
      AND talla LIKE '%,%'
");

$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($articulos)) {
    echo "<p style='color:green;'>✅ No hay artículos con tallas CSV pendientes de migrar.</p>";
    
    // Verificar artículos SIN ningún registro en tallas
    $stmt2 = $pdo->query("
        SELECT i.id, i.articulo, i.stock 
        FROM inventario_epp i 
        LEFT JOIN inventario_epp_tallas t ON i.id = t.inventario_epp_id 
        WHERE i.activo = 1 AND t.id IS NULL
    ");
    $sin_tallas = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sin_tallas)) {
        echo "<p>⚠️ Artículos sin registro de tallas (se crearán como 'Única'):</p><ul>";
        foreach ($sin_tallas as $art) {
            $stmtIns = $pdo->prepare("INSERT IGNORE INTO inventario_epp_tallas (inventario_epp_id, talla, stock) VALUES (?, 'Única', ?)");
            $stmtIns->execute([$art['id'], $art['stock']]);
            echo "<li>{$art['articulo']} → Talla: Única, Stock: {$art['stock']}</li>";
        }
        echo "</ul><p style='color:green;'>✅ Completado.</p>";
    }
    exit;
}

echo "<p>Encontrados " . count($articulos) . " artículos con tallas CSV:</p>";

$stmt_insert = $pdo->prepare("
    INSERT IGNORE INTO inventario_epp_tallas (inventario_epp_id, talla, stock) 
    VALUES (:epp_id, :talla, 0)
");

foreach ($articulos as $art) {
    $tallas = array_map('trim', explode(',', $art['talla']));
    echo "<p><strong>{$art['articulo']}</strong> (ID: {$art['id']}) — Tallas: " . implode(', ', $tallas) . "</p><ul>";
    
    foreach ($tallas as $talla) {
        if (empty($talla)) continue;
        $stmt_insert->execute([':epp_id' => $art['id'], ':talla' => $talla]);
        echo "<li>Talla: {$talla} → Stock: 0 (ingresar manualmente)</li>";
    }
    
    // Actualizar stock del artículo padre a 0 (se recalculará con las tallas)
    $pdo->prepare("UPDATE inventario_epp SET stock = 0 WHERE id = ?")->execute([$art['id']]);
    echo "</ul>";
}

echo "<p style='color:green;'>✅ Migración completada. Ahora ingresa el stock por talla desde el inventario.</p>";
echo "<p><a href='" . URL_BASE . "dashboard/inventario_epp/inventario_epp.php'>Ir al Inventario EPP</a></p>";
?>
