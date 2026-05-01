# Car Rental API 

## Overview

Production-ready Symfony 7.4 + API Platform 3 REST API for car rental reservation management with JWT authentication and business rule enforcement.

## Package Contents

```
── IMPLEMENTATION_NOTES.md   # Architecture and deployment guide
```

## Key Features

✅ **JWT Authentication** - Stateless, bearer token-based auth
✅ **REST API** - API Platform 3 operations for CRUD endpoints  
✅ **Business Rules**:

- Date coherence validation (endDate >= startDate)
- Overlap prevention (no double-bookings)
- Owner-only access (privacy enforcement)
- Soft cancellation (audit trail preservation)

✅ **Testing** - 21 tests (unit + functional) covering all scenarios
✅ **Type Safety** - PHP 8.2+ with strict typing
✅ **PSR Standards** - PSR-12 code formatting, PSR-4 autoloading

## Deployment Instructions

### Prerequisites

- PHP 8.2+
- Symfony 7.4
- PostgreSQL 16+ or MySQL 8+
- Composer

### Setup

1. **Install dependencies**:

    ```bash
    composer install --no-dev
    ```

2. **Configure environment**:

    ```bash
    cp .env.local.example .env.local
    # Edit .env.local with your database and JWT credentials
    DATABASE_URL="postgresql://user:pass@host:5432/location_voitures"
    JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
    JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
    JWT_PASSPHRASE=your_passphrase
    ```

3. **Generate JWT keys** (if not present):

    ```bash
    php bin/console lexik:jwt:generate-keypair
    ```

4. **Setup database**:

    ```bash
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate
    ```

5. **Start the server**:
    ```bash
    php bin/console server:start
    # or
    symfony serve
    ```

### API Endpoints

- **Login**: `POST /api/login_check`
- **Cars**: `GET /api/cars`, `GET /api/cars/{id}`
- **Reservations**:
    - `POST /api/reservations` (create)
    - `GET /api/users/{id}/reservations` (list own)
    - `PUT /api/reservations/{id}` (update own)
    - `DELETE /api/reservations/{id}` (cancel own)

### Testing

Run the full test suite:

```bash
APP_ENV=test php bin/console doctrine:schema:create
php bin/phpunit
```

Expected output: **21 tests, 45 assertions, OK**

## Architecture

### Entities (`src/Entity/`)

- **User**: AuthenticatedUserInterface, supports roles
- **Car**: Vehicle inventory with pricing and availability flag
- **Reservation**: Booking with date range, status, and ownership

### Services (`src/Service/`)

- **ReservationCreationService**: Validates dates, checks overlaps, sets defaults
    - `prepareForCreation()` - Enforces business rules for POST
    - `prepareForUpdate()` - Ownership + re-validation for PUT

### API Platform Operations

Driven via metadata in Entity class attributes:

```php
#[ApiResource(
    operations: [
        new Post('/reservations', processor: ReservationCreationProcessor::class),
        new Put('/reservations/{id}', processor: ReservationUpdateProcessor::class),
        new Delete('/reservations/{id}', processor: ReservationCancellationProcessor::class),
        // ...
    ]
)]
```

**Processors** (`src/State/`):

- `ReservationCreationProcessor` - Validates auth, calls service, persists
- `ReservationUpdateProcessor` - Fetches existing, merges, re-validates, persists
- `ReservationCancellationProcessor` - Sets status to 'cancelled', persists

**Providers** (`src/State/`):

- `CarCollectionProvider` - Returns available cars
- `CarItemProvider` - Returns single available car
- `UserReservationsProvider` - Returns user's reservations (owner-only)

## Security

### Authentication

- JWT tokens issued at `/api/login_check`
- Stateless bearer auth on protected routes
- Default expiry: 3600 seconds (configurable)

### Authorization

- Owner-only access enforced via processors/providers
- 403 Forbidden returned for cross-user access
- Service layer validates ownership before mutations

### Data Validation

- Symfony Validator constraints on entities
- Callback validators for complex rules (date coherence)
- Business service layer double-checks before persistence

## Compliance

✅ **PSR-12** - Code style consistency  
✅ **SOLID Principles**:

- Single Responsibility: Services focus on business logic
- Open/Closed: Processors/Providers extend API Platform interfaces
- Liskov Substitution: Interchangeable processor implementations
- Interface Segregation: Focused service methods
- Dependency Inversion: Constructor injection, contract-based design

✅ **API Platform Best Practices**:

- Metadata-driven operations (no custom controllers needed)
- State providers/processors for business logic separation
- JSON-LD serialization with Hydra vocabulary
- Automatic OpenAPI/Swagger documentation

## Support

For issues or questions about the implementation:

1. Review `API_DOCUMENTATION.md` for endpoint reference
2. Check `tests/` for usage examples
3. Consult `src/Service/ReservationCreationService.php` for business rules

## License

Proprietary - Symfony Car Rental API
