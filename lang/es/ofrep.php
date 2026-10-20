<?php

declare(strict_types=1);

// errorDetails for OFREP responses. The errorCode beside each is never translated (ADR-0002).
return [
    'flag_not_found' => 'No hay ningún flag :key en este entorno.',
    'targeting_key_missing' => 'El flag :key sirve un despliegue por porcentaje, que necesita un targetingKey en el contexto.',
    'invalid_context' => 'El contexto debe ser un objeto, y su targetingKey una cadena.',
    'parse_error' => 'El cuerpo de la petición no es JSON válido.',
    'general' => 'No se pudo evaluar el flag :key.',
    'server_error' => 'No se pudieron evaluar los flags. Inténtalo de nuevo en un momento.',
];
