-- Datos de ejemplo para la base aqua_sentinel (colonias y puntos seguros).
-- Importar UNA sola vez, DESPUÉS de aqua_sentinel.sql y estando DENTRO de la base aqua_sentinel.
--
-- DATOS SINTÉTICOS: las coordenadas son APROXIMADAS y la vulnerabilidad es INVENTADA.
-- Verifiquen cada coordenada en openstreetmap.org y declárenlo en el README.

INSERT INTO colonias (nombre, municipio, latitud, longitud, nivel_vulnerabilidad, descripcion) VALUES
('Centro',             'Veracruz',         19.2000, -96.1380, 'medio',    'Dato de ejemplo'),
('Flores Magón',       'Veracruz',         19.1900, -96.1300, 'bajo',     'Dato de ejemplo'),
('Ignacio Zaragoza',   'Veracruz',         19.1830, -96.1330, 'medio',    'Dato de ejemplo'),
('Formando Hogar',     'Veracruz',         19.1850, -96.1450, 'alto',     'Dato de ejemplo'),
('El Coyol',           'Veracruz',         19.1640, -96.1500, 'alto',     'Dato de ejemplo'),
('Floresta',           'Veracruz',         19.1550, -96.1350, 'muy_alto', 'Dato de ejemplo'),
('Las Bajadas',        'Veracruz',         19.1560, -96.1900, 'alto',     'Dato de ejemplo'),
('Lomas de Río Medio', 'Veracruz',         19.2100, -96.1900, 'medio',    'Dato de ejemplo'),
('Playa Linda',        'Veracruz',         19.2100, -96.1600, 'bajo',     'Dato de ejemplo'),
('Puente Moreno',      'Medellín de Bravo', 19.1100, -96.1600, 'muy_alto', 'Dato de ejemplo');

-- PUNTOS SEGUROS DE EJEMPLO: no son refugios reales. Antes de la demo cámbienlos por
-- lugares tomados de una fuente pública de Protección Civil, o preséntenlos como ejemplo.
INSERT INTO puntos_seguros (id_colonia, nombre, tipo, direccion, latitud, longitud, capacidad, estado) VALUES
((SELECT id_colonia FROM colonias WHERE nombre = 'Centro'),             'Punto seguro de ejemplo A', 'refugio',       'Dirección de ejemplo, zona Centro',        19.1960, -96.1420, 150, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'Ignacio Zaragoza'),   'Punto seguro de ejemplo B', 'punto_reunion', 'Dirección de ejemplo, zona Zaragoza',      19.1790, -96.1280, 200, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'El Coyol'),           'Punto seguro de ejemplo C', 'centro_salud',  'Dirección de ejemplo, zona Coyol',         19.1700, -96.1560, 120, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'Lomas de Río Medio'), 'Punto seguro de ejemplo D', 'refugio',       'Dirección de ejemplo, zona Río Medio',     19.2040, -96.1820, 180, 'disponible'),
((SELECT id_colonia FROM colonias WHERE nombre = 'Puente Moreno'),      'Punto seguro de ejemplo E', 'refugio',       'Dirección de ejemplo, zona Puente Moreno', 19.1180, -96.1520, 100, 'cerrado');
