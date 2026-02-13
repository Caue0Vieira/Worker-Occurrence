<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Authentication
    |--------------------------------------------------------------------------
    |
    | Configurações de autenticação via X-API-Key para acesso à API.
    | Suporta múltiplas chaves para diferentes clientes/sistemas.
    |
    */

    'keys' => [
        // Chave principal do sistema
        'main' => env('API_KEY_MAIN', 'dev-key-12345'),
        
        // Chave para sistema externo
        'external_system' => env('API_KEY_EXTERNAL', 'external-system-key'),
        
        // Chave para frontend interno
        'internal_frontend' => env('API_KEY_INTERNAL', 'internal-frontend-key'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configurações de limite de requisições por minuto para cada API Key.
    |
    */

    'rate_limit' => [
        'requests_per_minute' => env('API_RATE_LIMIT', 100),
        'enabled' => env('API_RATE_LIMIT_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para garantir idempotência nas requisições.
    |
    */

    'idempotency' => [
        'header_name' => 'Idempotency-Key',
        'ttl' => env('IDEMPOTENCY_TTL', 86400), // 24 horas em segundos
        'required_methods' => ['POST', 'PUT', 'PATCH'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações padrão para respostas da API.
    |
    */

    'response' => [
        'async_status_code' => 202, // Accepted
        'include_request_id' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Occurrences List Cache
    |--------------------------------------------------------------------------
    |
    | Configuração da chave compartilhada de invalidação do cache de listagem.
    |
    */
    'occurrences_cache' => [
        'redis_connection' => env('OCCURRENCES_CACHE_REDIS_CONNECTION', 'cache'),
        'key_prefix' => env('OCCURRENCES_CACHE_KEY_PREFIX', 'occurrences:list'),
    ],

];

