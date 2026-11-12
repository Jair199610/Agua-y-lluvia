<?php
// GET api/fugas.php
// Función 4 (detector colaborativo de fugas): grupos de reportes cercanos entre sí.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sesion.php';
require_once __DIR__ . '/../lib/fugas.php';

try {
    exigirPermiso('reportes');
    responderJson(['radio_metros' => RADIO_FUGA_METROS, 'fugas' => detectarFugas(reportesParaFugas())]);
} catch (Throwable $e) {
    responderJson(['error' => 'No se pudieron calcular las fugas'], 500);
}
