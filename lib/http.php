<?php
// Peticiones HTTP con cURL. Devuelven el arreglo decodificado o null si algo falla.

function httpJson(string $url, ?array $cuerpo = null, array $cabeceras = [], int $segundos = 10): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $segundos,
        CURLOPT_USERAGENT => 'hackathon-vtw-agua/1.0',
    ]);
    if ($cuerpo !== null) {
        $cabeceras[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }
    if ($cabeceras) curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);
    $respuesta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($respuesta === false || $codigo < 200 || $codigo >= 300) return null;
    $datos = json_decode($respuesta, true);
    return is_array($datos) ? $datos : null;
}

// Pide texto a Gemini. Devuelve el texto o null si no hay llave o falla.
function preguntarGemini(string $instruccion, bool $comoJson = false): ?string {
    $llave = env('GEMINI_API_KEY');
    if ($llave === '') return null;
    $modelo = env('GEMINI_MODEL', 'gemini-2.5-flash');
    $config = ['temperature' => 0];
    if ($comoJson) $config['responseMimeType'] = 'application/json';
    $respuesta = httpJson(
        "https://generativelanguage.googleapis.com/v1beta/models/$modelo:generateContent",
        ['contents' => [['parts' => [['text' => $instruccion]]]], 'generationConfig' => $config],
        ["x-goog-api-key: $llave"],
        15
    );
    $texto = $respuesta['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return is_string($texto) && trim($texto) !== '' ? trim($texto) : null;
}
