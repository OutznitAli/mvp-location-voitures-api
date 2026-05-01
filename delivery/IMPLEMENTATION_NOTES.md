# Implementation Notes

## Architecture Overview

### State Pattern (API Platform 3)

This implementation uses API Platform's **state provider/processor** pattern rather than traditional controllers:

```
Request → Operation Metadata (Entity Attribute)
        → Processor/Provider (Business Logic)
        → Serialization
        → Response
```

**Processors** handle write operations (POST/PUT/DELETE):

- Receive deserialized request body
- Access URI variables, query params, security context
- Execute business logic (validation, authorization, state changes)
- Return entity for serialization

**Providers** handle read operations (GET):

- Receive URI variables, query params
- Execute authorization checks
- Query repository for data
- Return entity/collection for serialization

### Business Rules Implementation

#### 1. Date Coherence

Enforced at entity validation + service layer:

```php
// Entity constraint
#[Assert\Callback]
public function validateDateRange(ExecutionContextInterface $context): void {
    if ($this->endDate < $this->startDate) {
        $context->buildViolation('The endDate cannot be earlier than startDate.')
            ->atPath('endDate')
            ->addViolation();
    }
}

// Service layer double-check
if ($endDate < $startDate) {
    throw new \InvalidArgumentException('...');
}
```

#### 2. Overlap Prevention

Repository query with strict comparison allowing touching ranges:

```php
// Overlaps if: existingStart < requestedEnd AND existingEnd > requestedStart
// Allows: [A,B] and [B,C] (touching at boundary)
SELECT COUNT(*) FROM reservation r
WHERE r.car_id = :car
  AND r.start_date < :requestedEnd
  AND r.end_date > :requestedStart
  AND r.id != :excludeId
```

Service layer enforces and throws 422:

```php
if ($this->hasOverlapConflict($data, $existingReservation)) {
    throw new UnprocessableEntityHttpException(
        'This car is already reserved for the requested date range.'
    );
}
```

#### 3. Owner Privacy

Enforced via processor security checks + provider filters:

```php
// Processor: Ownership guard
$user = $this->security->getUser();
if (!$user || $user->getId() !== $existingReservation->getReservations()->getId()) {
    throw new AccessDeniedHttpException('You can only update your own reservations.');
}

// Provider: Owner-only collection
$currentUser = $this->security->getUser();
if ((int)$uriVariables['id'] !== $currentUser->getId()) {
    throw new AccessDeniedHttpException('You can only view your own reservations.');
}
```

#### 4. Soft Cancellation

DELETE operation sets status rather than removing record:

```php
// ReservationCancellationProcessor
public function process(mixed $data, Operation $operation, array $uriVariables, array $context): mixed {
    // ... authorization checks ...
    $data->setStatus('cancelled');
    return $data; // Persist handler saves updated status
}
```

### Authentication Flow

1. **Token Generation**:
    - POST `/api/login_check` with email + password
    - Lexik JWT validates credentials
    - Returns JWT token (3600 sec default)

2. **Token Usage**:
    - Set `Authorization: Bearer <token>` header on requests
    - JWT firewall extracts + validates signature/expiry
    - `$this->security->getUser()` returns authenticated User

3. **Stateless Design**:
    - No sessions or cookies required
    - Each request independently validated
    - Suitable for distributed/microservice deployments

### Testing Strategy

#### Unit Tests (`tests/Unit/`)

- Mock repositories
- Test business logic in isolation
- Cover edge cases (date boundaries, exclusion logic)
- Fast execution, no database

#### Functional Tests (`tests/Functional/`)

- Real database (SQLite in-memory for speed)
- Test full request/response cycle
- Verify security constraints
- Validate serialization/deserialization

#### Test Infrastructure (`tests/Functional/ApiTestCase.php`)

```php
// Helper methods
- resetDatabase() - Clears entities between tests
- createUser(email, plainPassword) - Creates + hashes password
- getToken(email, password) - Calls login endpoint, extracts token
- jsonRequest(method, uri, body, token) - Sets LD+JSON + Bearer auth
```

### PSR-12 Compliance

Code follows PSR-12 Extended Coding Style:

✅ **File Structure**:

- 4-space indentation
- Unix line endings (LF)
- Explicit `<?php` tags without closing `?>`

✅ **Classes & Methods**:

- Opening braces on same line (class, function, if/for/while)
- Closing braces on new line
- One class per file, namespace matches directory structure

✅ **Naming**:

- `PascalCase` for classes (e.g., `ReservationCreationService`)
- `camelCase` for methods/variables (e.g., `prepareForCreation()`)
- `UPPER_CASE` for constants

✅ **Spacing**:

- 2 blank lines before class definitions
- 1 blank line before method definitions
- Control structure keywords separated from parentheses by space: `if (...)` not `if(...)`

### Dependency Injection

All services use constructor injection for:

- Loose coupling (easy to mock/test)
- Type safety (IDE autocomplete)
- Explicit dependencies (visible in signature)

Example:

```php
public function __construct(
    private ReservationRepository $repository,
    private UserPasswordHasherInterface $hasher,
    private Security $security
) {}
```

### Error Handling

Exceptions map to HTTP responses via Symfony kernel exception listener:

```php
throw new UnprocessableEntityHttpException('...')  // 422
throw new AccessDeniedHttpException('...')         // 403
throw new NotFoundHttpException('...')             // 404
throw new BadRequestHttpException('...')           // 400
throw new UnauthorizedHttpException('...')         // 401
```

Serialized to RFC 7807 Problem Details format by API Platform.

### Database Migrations

Two migrations provided:

1. **Version20260429230129** - Initial schema
    - User, Car, Reservation tables
    - Foreign keys + constraints
    - Indexes on frequently-queried columns

2. **Version20260429230655** - Audit preparation
    - Added columns for future soft-delete implementation
    - Backward compatible

Apply with:

```bash
php bin/console doctrine:migrations:migrate
```

### Configuration

Key Symfony configuration in `config/`:

- **security.yaml**: JWT firewall, login endpoint, access control
- **packages/api_platform.yaml**: JSON-LD vocabulary, cache settings
- **packages/framework.yaml**: HTTP cache, serializer defaults
- **packages/doctrine.yaml**: ORM mapping, connection pooling
- **packages/validator.yaml**: Constraint validation

Production adjustments:

```yaml
# .env.prod
APP_ENV=prod
DEBUG=0
DATABASE_URL=postgresql://...
JWT_PASSPHRASE=<strong-secret>
CORS_ALLOW_ORIGIN=^https://yourdomain\.com$
```

### Performance Considerations

1. **Query Optimization**:
    - Overlap check uses indexed car_id + date range
    - User reservations query orders by startDate (supports pagination)

2. **Caching**:
    - Car collections could be cached (rarely change)
    - Reservations typically not cached (user-specific)

3. **Pagination** (future):
    - API Platform supports ItemsPerPage, pagination via `api_resources`
    - Implement for user reservations if exceeding 100+ records

4. **Authentication**:
    - JWT stateless (no session storage overhead)
    - Token validation via cryptographic signature (fast)

## Future Enhancements

1. **Admin Dashboard**:
    - ROLE_ADMIN with full CRUD access to all reservations
    - Car inventory management (create/update/delete)

2. **Filters & Search**:
    - Search reservations by date range, car brand, status
    - Implement API Platform `filters` configuration

3. **Webhooks**:
    - Event listeners on reservation state changes
    - Notify external systems (payment, notifications)

4. **Rate Limiting**:
    - Symfony RateLimiter to prevent API abuse
    - Configurable per user/IP

5. **Audit Logging**:
    - Track all mutations with user + timestamp
    - Doctrinal Loggable behavior integration

6. **Field Encryption**:
    - Encrypt sensitive data (email, passwords) at rest
    - Gedmo Doctrine encryption extension

## Troubleshooting

**Issue**: Tests fail with "Undefined type" errors

- **Cause**: IDE cache, stale language server
- **Fix**: Clear VS Code cache: `cmd+shift+p` → "Developer: Reload Window"

**Issue**: 415 Unsupported Media Type on POST

- **Cause**: Wrong Content-Type header
- **Fix**: Ensure `Content-Type: application/ld+json` in request

**Issue**: 401 Unauthorized on protected endpoints

- **Cause**: Missing/expired JWT token
- **Fix**: Call `/api/login_check` first, use returned token in Authorization header

**Issue**: 404 Not Found on existing reservation

- **Cause**: API Platform URL case-sensitive, UUID format issue
- **Fix**: Verify URI format: `/api/reservations/5` not `/api/reservations/5/`

**Issue**: Database connection refused

- **Cause**: PostgreSQL not running, wrong credentials
- **Fix**: Check `.env.local`, verify `psql -U user -h localhost` works

## Verification Checklist

Before deployment:

- [ ] `php bin/console lint:container` passes (service wiring)
- [ ] `php bin/console lint:yaml config/` passes (config syntax)
- [ ] `php bin/phpunit` all tests pass (functionality)
- [ ] `doctrine:schema:validate` passes (DB matches entities)
- [ ] JWT keys generated and readable
- [ ] `.env.local` configured with production values
- [ ] Database migrations applied successfully
- [ ] CORS_ALLOW_ORIGIN set to production domain
