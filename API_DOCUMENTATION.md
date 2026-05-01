# Car Rental API Documentation

## Overview

Symfony 7.4 REST API for car rental reservations with JWT authentication, business rule enforcement (date coherence, overlap prevention), and owner-only privacy controls.

**Base URL**: `http://localhost/api`
**Authentication**: Bearer JWT token (stateless)
**Content-Type**: `application/ld+json` (API Platform 3)

---

## Authentication

### Login Endpoint

**POST** `/api/login_check`

Authenticate user and receive JWT token.

**Request**:

```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response** (200 OK):

```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

**Response** (401 Unauthorized):

```json
{
    "code": 401,
    "message": "Invalid credentials."
}
```

**Usage**:

```bash
TOKEN=$(curl -X POST http://localhost/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"pass1234"}' \
  | jq -r '.token')

curl -H "Authorization: Bearer $TOKEN" http://localhost/api/cars
```

---

## Cars API

### List All Available Cars

**GET** `/api/cars`

Retrieve all cars available for reservation (stateless, no authentication required for read).

**Response** (200 OK):

```json
[
    {
        "@context": "/api/contexts/Car",
        "@id": "/api/cars/1",
        "@type": "Car",
        "id": 1,
        "brand": "Toyota",
        "model": "Yaris",
        "unitPricePerDay": 50,
        "isAvailable": true
    },
    {
        "@id": "/api/cars/2",
        "@type": "Car",
        "id": 2,
        "brand": "Honda",
        "model": "Civic",
        "unitPricePerDay": 75,
        "isAvailable": true
    }
]
```

### Get Single Car

**GET** `/api/cars/{id}`

Retrieve details for a specific car.

**Path Parameters**:

- `id` (integer, required): Car identifier

**Response** (200 OK):

```json
{
    "@context": "/api/contexts/Car",
    "@id": "/api/cars/1",
    "@type": "Car",
    "id": 1,
    "brand": "Toyota",
    "model": "Yaris",
    "unitPricePerDay": 50,
    "isAvailable": true
}
```

**Response** (404 Not Found):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "status": 404
}
```

---

## Reservations API

### Create Reservation

**POST** `/api/reservations`

Create a new reservation for the authenticated user. Enforces:

- Mandatory fields: `startDate`, `endDate`, `car`
- Date coherence: `endDate >= startDate`
- Overlap prevention: no existing reservation for same car and overlapping date range
- Default status: `pending`

**Authentication**: Required (Bearer JWT)

**Request**:

```json
{
    "startDate": "2026-06-01",
    "endDate": "2026-06-10",
    "car": "/api/cars/1"
}
```

**Response** (201 Created):

```json
{
    "@context": "/api/contexts/Reservation",
    "@id": "/api/reservations/5",
    "@type": "Reservation",
    "id": 5,
    "startDate": "2026-06-01",
    "endDate": "2026-06-10",
    "status": "pending",
    "car": {
        "@id": "/api/cars/1",
        "@type": "Car",
        "id": 1,
        "brand": "Toyota",
        "model": "Yaris"
    }
}
```

**Response** (401 Unauthorized):

```json
{
    "code": 401,
    "message": "Expired JWT Token"
}
```

**Response** (422 Unprocessable Entity - Invalid Dates):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "detail": "The endDate cannot be earlier than startDate.",
    "status": 422
}
```

**Response** (422 Unprocessable Entity - Overlap Conflict):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "detail": "This car is already reserved for the requested date range.",
    "status": 422
}
```

### Get User's Reservations

**GET** `/api/users/{id}/reservations`

Retrieve all reservations for a specific user. Only accessible to the owner or admin.

**Authentication**: Required (Bearer JWT)

**Path Parameters**:

- `id` (integer, required): User identifier

**Response** (200 OK):

```json
{
    "@context": "/api/contexts/Reservation",
    "@id": "/api/users/1/reservations",
    "@type": "Collection",
    "totalItems": 2,
    "member": [
        {
            "@id": "/api/reservations/5",
            "@type": "Reservation",
            "id": 5,
            "startDate": "2026-06-01",
            "endDate": "2026-06-10",
            "status": "pending",
            "car": {
                "@id": "/api/cars/1",
                "id": 1,
                "brand": "Toyota",
                "model": "Yaris"
            }
        },
        {
            "@id": "/api/reservations/6",
            "@type": "Reservation",
            "id": 6,
            "startDate": "2026-07-01",
            "endDate": "2026-07-05",
            "status": "pending",
            "car": {
                "@id": "/api/cars/2",
                "id": 2,
                "brand": "Honda",
                "model": "Civic"
            }
        }
    ]
}
```

**Response** (403 Forbidden - Cross-user Access):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "detail": "You can only view your own reservations.",
    "status": 403
}
```

### Update Reservation

**PUT** `/api/reservations/{id}`

Update an existing reservation owned by the authenticated user. Re-validates:

- Ownership: only the owner can update
- Date coherence: `endDate >= startDate`
- Overlap prevention (excluding self from conflict check)

**Authentication**: Required (Bearer JWT)

**Path Parameters**:

- `id` (integer, required): Reservation identifier

**Request**:

```json
{
    "startDate": "2026-06-02",
    "endDate": "2026-06-12",
    "car": "/api/cars/1"
}
```

**Response** (200 OK):

```json
{
    "@context": "/api/contexts/Reservation",
    "@id": "/api/reservations/5",
    "@type": "Reservation",
    "id": 5,
    "startDate": "2026-06-02",
    "endDate": "2026-06-12",
    "status": "pending",
    "car": {
        "@id": "/api/cars/1",
        "id": 1,
        "brand": "Toyota",
        "model": "Yaris"
    }
}
```

**Response** (403 Forbidden - Not Owner):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "detail": "You can only update your own reservations.",
    "status": 403
}
```

**Response** (422 Unprocessable Entity):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "detail": "The endDate cannot be earlier than startDate.",
    "status": 422
}
```

### Cancel Reservation

**DELETE** `/api/reservations/{id}`

Cancel (soft-delete) a reservation owned by the authenticated user. Sets status to `cancelled` for audit trail preservation.

**Authentication**: Required (Bearer JWT)

**Path Parameters**:

- `id` (integer, required): Reservation identifier

**Response** (204 No Content):

```
(empty body)
```

**Response** (403 Forbidden - Not Owner):

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "detail": "You can only cancel your own reservations.",
    "status": 403
}
```

---

## Error Handling

All error responses follow RFC 7807 Problem Details format:

```json
{
    "@context": "/api/contexts/Error",
    "@type": "Error",
    "title": "An error occurred",
    "detail": "Human-readable description",
    "status": 422,
    "type": "/errors/422"
}
```

**Common Status Codes**:

- `200 OK` - Successful GET, PUT
- `201 Created` - Successful POST (reservation created)
- `204 No Content` - Successful DELETE
- `400 Bad Request` - Malformed request
- `401 Unauthorized` - Missing or invalid JWT
- `403 Forbidden` - Insufficient permissions (cross-user access)
- `404 Not Found` - Resource does not exist
- `422 Unprocessable Entity` - Business rule violation (date/overlap)

---

## Business Rules Summary

1. **Date Coherence**: Reservation `endDate` must be >= `startDate`
2. **Overlap Prevention**: No two reservations for the same car with overlapping date ranges
3. **Owner Privacy**: Only reservation owner can view, update, or cancel their reservations
4. **Soft Deletion**: Canceled reservations retain audit trail (status = 'cancelled'), not physically deleted
5. **Boundary Behavior**: Touching date ranges (one ends, next starts same day) are allowed: `[A, B]` and `[B, C]` do not overlap

---

## Implementation Notes

- **Framework**: Symfony 7.4 with API Platform 3
- **Authentication**: Lexik JWT Bundle (stateless)
- **Database**: PostgreSQL (production) / SQLite (test)
- **Architecture**: State-based processors for business logic, service layer for coherence checks
- **Serialization**: JSON-LD with Hydra vocabulary for API documentation
- **Testing**: 21 tests (unit + functional) covering auth, privacy, business rules

---

## Delivery Contents

This package contains:

- `src/Controller/` - API controllers (currently empty, all logic in API Platform operations)
- `src/Entity/` - Domain entities with API metadata and validation
- `src/Service/` - Business logic services (ReservationCreationService, etc.)
- `src/State/` - API Platform processors and providers
- `src/Repository/` - Data access queries
- `tests/` - Full test suite (unit + functional)

**For production**: Deploy only Entity, Service, State, and Repository folders; API Platform handles routing.
