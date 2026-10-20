<?php

declare(strict_types=1);

// errorDetails for OFREP responses. The errorCode beside each is never translated (ADR-0002).
return [
    'flag_not_found' => 'There is no flag :key in this environment.',
    'targeting_key_missing' => 'The flag :key serves a percentage rollout, which needs a targetingKey in the context.',
    'invalid_context' => 'The context must be an object, and its targetingKey a string.',
    'parse_error' => 'The request body is not valid JSON.',
    'general' => 'The flag :key could not be evaluated.',
    'server_error' => 'Flags could not be evaluated. Try again shortly.',
];
