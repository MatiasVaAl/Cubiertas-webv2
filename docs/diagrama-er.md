# Entidades y relaciones

```mermaid
erDiagram
    USERS ||--o{ QUOTE_VERSIONS : emite
    USERS ||--o{ PAYMENTS : verifica
    USERS ||--o{ STATE_EVENTS : registra
    PRODUCTS ||--o{ PRODUCT_IMAGES : muestra
    PRODUCTS ||--o{ WIDTH_RATES : tarifa
    PRODUCTS o|--o{ QUOTE_LINES : referencia
    CUSTOMERS ||--o{ QUOTES : solicita
    QUOTES ||--|{ QUOTE_LINES : contiene
    QUOTES ||--o{ QUOTE_VERSIONS : conserva
    QUOTES ||--o{ TRACKING_CREDENTIALS : protege
    QUOTES ||--o{ STATE_EVENTS : historial
    QUOTES ||--o{ ORDERS : conserva_un_vigente
    QUOTE_VERSIONS ||--o| ORDERS : aceptada
    ORDERS ||--o{ PAYMENTS : comprobantes
    ORDERS ||--o{ STATE_EVENTS : operaciones
    ADDITIONAL_WORKS {
        bigint id PK
        string pricing_mode
        bigint price_clp
        boolean confirmed
    }
    WORKSHOP_SETTINGS {
        bigint id PK
        boolean formula_confirmed
        text bank_details_encrypted
        json dimension_limits
    }
    QUOTE_LINES {
        bigint id PK
        bigint quote_id FK
        json configuration_snapshot
        integer quantity
        bigint unit_price_clp
        json pending_reasons
    }
    QUOTE_VERSIONS {
        bigint id PK
        bigint quote_id FK
        integer version
        json snapshot
        bigint total_clp
    }
    ORDERS {
        bigint id PK
        bigint quote_id FK
        bigint quote_version_id FK
        bigint total_clp
        string payment_status
        string manufacturing_status
        string delivery_status
        timestamp superseded_at
    }
    USERS ||--o{ OPERATION_AUDITS : modifica_configuracion
```

Los adicionales seleccionados están embebidos en las configuraciones/snapshots comerciales; no hay FK desde el histórico que pueda alterar condiciones aceptadas. El token autoriza la cotización y el pedido asociado, no al cliente completo.
