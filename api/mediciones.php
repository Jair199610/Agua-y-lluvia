<?php
// GET api/mediciones.php?colonia_id=3
// Historial del pronóstico de lluvia guardado para una colonia (permiso "monitoreo").
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/mediciones.php';

try {
    exigirPermiso('monitoreo');
    responderJson(['unidad' => 'mm en 6 h', 'mediciones' => historialDeLluvia((int) ($_GET['colonia_id'] ?? 0))]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudo cargar el historial de lluvia'], 500);
}
