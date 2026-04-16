# Restaurant Table Reservation API

A production-grade Laravel 11 REST API for managing restaurant table reservations, with smart table assignment and full test coverage.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 11 (PHP 8.2+) |
| Database | MySQL 8 (SQLite for tests) |
| Auth | None — public API (Sanctum ready) |
| Testing | PHPUnit 11 |

---

## Setup

```bash
# 1. Clone & install
git clone <repo>
cd restaurant-reservation
composer install

# 2. Configure
cp .env.example .env
php artisan key:generate

# 3. Create database, then run migrations + seed
php artisan migrate --seed

# 4. Run the dev server
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`.

---

## Business Rules & Assumptions

| Rule | Value | Config key |
|---|---|---|
| Slot duration | 90 minutes | `RESERVATION_SLOT_DURATION_MINUTES` |
| Operating hours | 11:00 – 22:00 | `RESTAURANT_OPEN_TIME` / `CLOSE_TIME` |
| Max advance booking | 30 days | `RESERVATION_MAX_ADVANCE_DAYS` |
| Min advance booking | 2 hours | `RESERVATION_MIN_ADVANCE_HOURS` |
| Cancellation cutoff | 2 hours before slot | `CANCELLATION_CUTOFF_HOURS` |

**All values are environment-driven** — no code changes needed to tune the restaurant's schedule.

**Slot boundaries** are fixed windows (11:00, 12:30, 14:00 … 20:00). A booking occupies a complete slot; partial slots are not supported.

---

## API Reference

Detailed implementation notes are available in [docs/API_CODE_EXPLANATION.md](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/docs/API_CODE_EXPLANATION.md:1).

### Base URL
```
/api/v1
```

---

### 1. Create Table
`POST /api/v1/tables`

Add a table to the restaurant floor.

**Request body**
```json
{
  "table_number": 5,
  "capacity": 4,
  "location": "indoor",
  "notes": "Near window"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `table_number` | integer | ✅ | Unique, 1–999 |
| `capacity` | integer | ✅ | 1–20 |
| `location` | string | ✅ | `indoor` or `outdoor` |
| `notes` | string | ❌ | Max 500 chars |

**201 Response**
```json
{
  "data": {
    "id": 1,
    "table_number": 5,
    "capacity": 4,
    "location": "indoor",
    "is_active": true,
    "notes": "Near window",
    "created_at": "2024-06-01T10:00:00+00:00"
  }
}
```

---

### 2. List Tables
`GET /api/v1/tables`

Returns all active tables. Optional `?location=indoor|outdoor` filter.

---

### 3. View Available Slots
`GET /api/v1/availability?date=YYYY-MM-DD&guest_count=2`

Returns every time slot for the date, each with its available tables.

| Param | Required | Notes |
|---|---|---|
| `date` | ✅ | `YYYY-MM-DD`, today → +30 days |
| `guest_count` | ❌ | Filters tables by minimum capacity |

**200 Response**
```json
{
  "data": [
    {
      "slot_start": "12:30",
      "slot_end": "14:00",
      "label": "12:30 PM – 02:00 PM",
      "available_count": 3,
      "available_tables": [
        { "id": 2, "table_number": 2, "capacity": 2, "location": "indoor" }
      ]
    }
  ],
  "meta": {
    "date": "2024-06-15",
    "guest_count": 2,
    "total_slots": 7
  }
}
```

---

### 4. Book a Table
`POST /api/v1/reservations`

Creates a reservation. Supports **smart assignment** (omit `table_id`) or explicit table selection.

**Request body**
```json
{
  "customer_name": "Jane Doe",
  "customer_email": "jane@example.com",
  "customer_phone": "+91-9876543210",
  "guest_count": 3,
  "reservation_date": "2024-06-15",
  "slot_start": "19:00",
  "preferred_location": "outdoor",
  "special_requests": "High chair needed"
}
```

| Field | Required | Notes |
|---|---|---|
| `table_id` | ❌ | Explicit table; triggers smart assignment if omitted |
| `preferred_location` | ❌ | `indoor`/`outdoor` hint for smart assignment |
| `customer_name` | ✅ | |
| `customer_email` | ✅ | |
| `customer_phone` | ✅ | |
| `guest_count` | ✅ | |
| `reservation_date` | ✅ | Today → +30 days |
| `slot_start` | ✅ | `HH:MM` 24h, must be a valid slot boundary |
| `special_requests` | ❌ | |

**201 Response**
```json
{
  "data": {
    "id": 42,
    "reference_code": "K7PMXQBT",
    "status": "confirmed",
    "table": {
      "id": 3,
      "table_number": 9,
      "capacity": 4,
      "location": "outdoor"
    },
    "customer": {
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+91-9876543210"
    },
    "guest_count": 3,
    "special_requests": "High chair needed",
    "slot": {
      "date": "2024-06-15",
      "start": "19:00",
      "end": "20:30"
    },
    "can_cancel": true,
    "created_at": "2024-06-01T10:00:00+00:00"
  }
}
```

---

### 5. View Reservation
`GET /api/v1/reservations/{referenceCode}`

Retrieve full details of any reservation by its reference code (case-insensitive).

---

### 6. Cancel Reservation
`DELETE /api/v1/reservations/{referenceCode}`

**Request body** (optional)
```json
{ "reason": "Change of plans" }
```

Returns `422` if the slot is within the cancellation cutoff window.

---

## Smart Table Assignment

When `table_id` is **not** provided, the system automatically selects the best table:

1. **Capacity match** — exclude all tables smaller than `guest_count`
2. **Availability check** — exclude tables already confirmed for the same date + slot
3. **Location preference** — if `preferred_location` is given, try that location first; fall back to any if none free
4. **Smallest first** — among qualifying tables, pick the one with the lowest capacity (then lowest table_number as tiebreaker)

This maximises seating capacity by not assigning a large table to a small party.

---

## Error Responses

All errors follow a consistent envelope:

```json
{ "message": "Human-readable description of the error" }
```

| HTTP Status | When |
|---|---|
| 422 | Validation failure, business rule violation |
| 409 | Table double-booking attempt |
| 404 | Resource not found |

Validation errors additionally include:
```json
{
  "message": "The given data was invalid.",
  "errors": { "field": ["Error detail"] }
}
```

---

## Running Tests

```bash
# All tests
php artisan test

# Unit only
php artisan test --testsuite=Unit

# Feature only
php artisan test --testsuite=Feature

# With coverage (requires Xdebug / PCOV)
php artisan test --coverage
```

### Test breakdown

| Suite | File | What it covers |
|---|---|---|
| Unit | `SlotGeneratorServiceTest` | Slot generation, duration, boundaries |
| Unit | `TableAssignmentServiceTest` | Smart assignment logic, all edge cases |
| Unit | `ReservationModelTest` | Reference code gen, cancellability, cancel() |
| Feature | `TableApiTest` | CRUD + validation for tables endpoint |
| Feature | `AvailabilityApiTest` | Slot listing, count changes after booking |
| Feature | `ReservationApiTest` | Booking, retrieval, cancellation flows |

---

## Project Structure

```
app/
├── Exceptions/
│   └── ReservationException.php       # Named domain exceptions
├── Http/
│   ├── Controllers/Api/
│   │   ├── TableController.php
│   │   ├── AvailabilityController.php
│   │   └── ReservationController.php
│   ├── Requests/
│   │   ├── CreateTableRequest.php
│   │   ├── BookTableRequest.php
│   │   └── CancelReservationRequest.php
│   └── Resources/
│       ├── TableResource.php
│       └── ReservationResource.php
├── Models/
│   ├── Table.php
│   └── Reservation.php
└── Services/
    ├── SlotGeneratorService.php        # Pure slot calculation
    ├── TableAssignmentService.php      # Smart assignment queries
    └── ReservationService.php          # Orchestration + business rules

config/
└── reservation.php                     # All tunable business constants

database/
├── migrations/
│   ├── ..._create_tables_table.php
│   └── ..._create_reservations_table.php
├── factories/
│   ├── TableFactory.php
│   └── ReservationFactory.php
└── seeders/
    ├── DatabaseSeeder.php
    └── TableSeeder.php

routes/
└── api.php

tests/
├── Feature/
│   ├── TableApiTest.php
│   ├── AvailabilityApiTest.php
│   └── ReservationApiTest.php
└── Unit/
    ├── SlotGeneratorServiceTest.php
    ├── TableAssignmentServiceTest.php
    └── ReservationModelTest.php
```

---

## Design Decisions

**Service layer** — Controllers are thin; all business logic lives in `ReservationService`, `SlotGeneratorService`, and `TableAssignmentService`. Each service has a single responsibility (SRP).

**Named exceptions** — `ReservationException` uses static factory methods so call sites read like plain English (`ReservationException::tableAlreadyBooked()`). The HTTP status code is embedded in the exception, keeping controllers free of logic.

**Config-driven rules** — Every business constant (slot size, cutoff hours, operating hours) is in `config/reservation.php` and driven by `.env`. Changing the cancellation window is a deploy-config change, not a code change.

**Double-booking prevention** — Enforced at two levels: a unique database constraint on `(table_id, reservation_date, slot_start)` AND a service-layer check before insert. The DB constraint is the safety net; the service check gives a clean user-facing error.

**Smart assignment** — Entirely query-based with a single SQL call; no application-side looping. `ORDER BY capacity ASC, table_number ASC` with `LIMIT 1` makes it O(1) at the DB level.

**Reference codes** — 8-character alphanumeric codes are URL-safe, human-readable, and collision-resistant at restaurant scale. Lookup is case-insensitive.
