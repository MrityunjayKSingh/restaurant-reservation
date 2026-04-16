# API Code Explanation

This document explains how each API endpoint in the restaurant reservation project works at the code level.

Base API prefix: `/api/v1`

Route definitions live in [routes/api.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/routes/api.php:1).

## API Overview

The API is split into 3 areas:

1. `tables`
   Staff-facing endpoints for creating and viewing restaurant tables.
2. `availability`
   Public endpoint for checking open slots and available tables.
3. `reservations`
   Public endpoints for booking, viewing, and cancelling reservations.

## Request Flow

Most endpoints follow the same Laravel flow:

1. A route in [routes/api.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/routes/api.php:1) maps the HTTP request to a controller method.
2. A `FormRequest` class validates input for endpoints that accept request bodies.
3. The controller stays thin and delegates business logic to a service or model.
4. A resource class formats the JSON response.
5. Domain errors are converted into JSON messages with the correct HTTP status code.

## 1. Tables API

Controller: [app/Http/Controllers/Api/TableController.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Controllers/Api/TableController.php:1)

Resource: [app/Http/Resources/TableResource.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Resources/TableResource.php:1)

Model: [app/Models/Table.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Models/Table.php:1)

Validation: [app/Http/Requests/CreateTableRequest.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Requests/CreateTableRequest.php:1)

### `POST /api/v1/tables`

Method: `TableController::store()`

Purpose:
Creates a new table record in the `tables` table.

Code flow:

1. Laravel sends the request to `CreateTableRequest`.
2. `CreateTableRequest::rules()` validates:
   - `table_number` is required, unique, integer, and between `1` and `999`
   - `capacity` is required and between `1` and `20`
   - `location` must be one of the configured values from `config/reservation.php`
   - `notes` is optional and limited to 500 characters
3. `TableController::store()` calls `Table::create($request->validated())`.
4. The created model is passed through `TableResource`.
5. The response is returned with HTTP `201 Created`.

Important code details:

- Allowed locations come from `config('reservation.locations')`, currently `indoor` and `outdoor`.
- `is_active` is not required in the request because the database default sets it to `true`.
- `Table` uses `SoftDeletes`, so deleted tables are not permanently removed.

Response shape:

`TableResource` returns:

- `id`
- `table_number`
- `capacity`
- `location`
- `is_active`
- `notes`
- `created_at`

### `GET /api/v1/tables`

Method: `TableController::index()`

Purpose:
Lists all active tables, optionally filtered by location.

Code flow:

1. The controller starts with `Table::active()`.
2. `scopeActive()` in the `Table` model adds `where('is_active', true)`.
3. If the query string contains `location`, the controller applies `scopeByLocation($loc)`.
4. Results are ordered by `table_number`.
5. The collection is returned through `TableResource::collection($tables)`.

Important code details:

- Only active tables are returned.
- The endpoint uses `request('location')` directly in the controller.
- There is no pagination in the current implementation.

### `GET /api/v1/tables/{table}`

Method: `TableController::show()`

Purpose:
Returns one table by its database ID.

Code flow:

1. Laravel route model binding automatically resolves `{table}` into a `Table` model.
2. The model is wrapped in `TableResource`.
3. Laravel returns the formatted JSON response.

Important code details:

- If the table ID does not exist, Laravel automatically returns `404 Not Found`.

## 2. Availability API

Controller: [app/Http/Controllers/Api/AvailabilityController.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Controllers/Api/AvailabilityController.php:1)

Service: [app/Services/ReservationService.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Services/ReservationService.php:1)

Supporting services:

- [app/Services/SlotGeneratorService.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Services/SlotGeneratorService.php:1)
- [app/Services/TableAssignmentService.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Services/TableAssignmentService.php:1)

### `GET /api/v1/availability`

Expected query parameters:

- `date` required, format `Y-m-d`
- `guest_count` optional, integer `1` to `20`

Method: `AvailabilityController::index()`

Purpose:
Returns all valid reservation slots for a date and shows which tables are free in each slot.

Code flow:

1. The controller validates the query string using `$request->validate(...)`.
2. It calls `ReservationService::getAvailability($date, $guestCount)`.
3. `ReservationService::assertDateIsBookable()` checks:
   - the date is not in the past
   - the date is not beyond `max_advance_days`
4. `SlotGeneratorService::generateForDate()` builds the available slot list.
5. For each slot, `TableAssignmentService::getAvailableTables($date, $slotStart)` loads active tables that do not already have a confirmed reservation for that date and start time.
6. If `guest_count` is present, tables smaller than the guest count are filtered out.
7. The controller returns:
   - `data`: all slots
   - `meta`: input date, guest count, and slot count

How slot generation works:

- Slot duration comes from `config('reservation.slot_duration_minutes')`, currently `90` minutes.
- Open time comes from `config('reservation.open_time')`, currently `11:00`.
- Close time comes from `config('reservation.close_time')`, currently `22:00`.
- The last start time is calculated as `close_time - slot_duration`.
- With the current config, the valid starts are:
  `11:00`, `12:30`, `14:00`, `15:30`, `17:00`, `18:30`, `20:00`

Response fields per slot:

- `slot_start`
- `slot_end`
- `label`
- `available_tables`
- `available_count`

Why `ReservationException` is used here:

- If the date violates business rules, the service throws `ReservationException`.
- The controller catches it and returns:
  `{ "message": "..." }`
  with the exception's HTTP status code.

## 3. Reservations API

Controller: [app/Http/Controllers/Api/ReservationController.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Controllers/Api/ReservationController.php:1)

Service: [app/Services/ReservationService.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Services/ReservationService.php:1)

Request classes:

- [app/Http/Requests/BookTableRequest.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Requests/BookTableRequest.php:1)
- [app/Http/Requests/CancelReservationRequest.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Requests/CancelReservationRequest.php:1)

Resource: [app/Http/Resources/ReservationResource.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Http/Resources/ReservationResource.php:1)

Model: [app/Models/Reservation.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Models/Reservation.php:1)

### `POST /api/v1/reservations`

Method: `ReservationController::store()`

Purpose:
Creates a reservation by either booking a specific table or automatically choosing the best available table.

Input validation:

`BookTableRequest::rules()` validates:

- `table_id` optional, must exist in `tables`
- `preferred_location` optional, must be one of the configured locations
- `customer_name` required
- `customer_email` required and must be a valid email
- `customer_phone` required
- `guest_count` required, integer `1` to `20`
- `special_requests` optional
- `reservation_date` required, `Y-m-d`, today or later, and not after the configured max date
- `slot_start` required, `H:i`

Business logic flow:

1. `ReservationController::store()` calls `ReservationService::book($request->validated())`.
2. The service computes `slot_end` through `SlotGeneratorService::resolveSlotEnd($slotStart)`.
3. The service checks:
   - the date is bookable
   - the slot start is a valid slot boundary
4. The booking is wrapped in `DB::transaction(...)`.
5. If `table_id` is present:
   - `resolveExplicitTable()` loads the active table
   - verifies table capacity is enough
   - verifies the table is not already booked for that date and slot
6. If `table_id` is not present:
   - `resolveSmartTable()` calls `TableAssignmentService::findBestTable(...)`
   - that service finds the smallest active table that fits the party and is not already confirmed for the same date and slot
   - if `preferred_location` is provided, that location is tried first and then it falls back to any location
7. `Reservation::create(...)` inserts the reservation with status `confirmed`.
8. The controller returns `ReservationResource` with HTTP `201 Created`.

Smart assignment strategy:

`TableAssignmentService::findBestTable()` applies this order:

1. active tables only
2. capacity must be `>= guest_count`
3. no confirmed reservation for the same `reservation_date` and `slot_start`
4. lowest `capacity` first
5. lowest `table_number` as the tie-breaker

This minimizes wasted seating capacity.

Reference code generation:

- `Reservation::boot()` hooks into the model `creating` event.
- If `reference_code` is missing, `generateReferenceCode()` creates an 8-character uppercase random string.
- It checks uniqueness using `withTrashed()` so even soft-deleted reservations cannot reuse the same code.

Response shape:

`ReservationResource` returns:

- `id`
- `reference_code`
- `status`
- `table`
- `customer`
- `guest_count`
- `special_requests`
- `slot`
- `cancellation` only when the reservation is cancelled
- `can_cancel`
- `created_at`
- `updated_at`

### `GET /api/v1/reservations/{referenceCode}`

Method: `ReservationController::show()`

Purpose:
Fetches a reservation using the user-facing reference code instead of the numeric database ID.

Code flow:

1. The controller converts the input to uppercase with `strtoupper($referenceCode)`.
2. It queries `Reservation::with('table')`.
3. It filters by `reference_code`.
4. It returns the result through `ReservationResource`.

Important code details:

- The lookup is case-insensitive because the code is normalized to uppercase.
- `firstOrFail()` returns `404 Not Found` if the code does not exist.

### `DELETE /api/v1/reservations/{referenceCode}`

Method: `ReservationController::cancel()`

Purpose:
Cancels a confirmed reservation if it is still within the allowed cancellation window.

Input validation:

`CancelReservationRequest::rules()` only validates:

- `reason` optional, string, max 300 characters

Code flow:

1. The controller loads the reservation by uppercase `reference_code`.
2. It calls `ReservationService::cancel($reservation, $reason)`.
3. The service checks `Reservation::isCancellable()`.
4. `isCancellable()` returns `true` only when:
   - the current status is `confirmed`
   - current time plus `cancellation_cutoff_hours` is still earlier than the slot datetime
5. If cancellation is not allowed, `ReservationException::cancellationNotAllowed()` is thrown.
6. If allowed, `Reservation::cancel($reason)` updates:
   - `status` to `cancelled`
   - `cancelled_at` to `now()`
   - `cancellation_reason`
7. A fresh copy of the reservation with `table` is returned and formatted with `ReservationResource`.

Important code details:

- Cancellation does not delete the reservation.
- The row remains in the database and the status changes to `cancelled`.
- The endpoint returns the updated reservation object, not just a success message.

## Exception Handling

Custom exception class: [app/Exceptions/ReservationException.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/app/Exceptions/ReservationException.php:1)

This class centralizes business-rule failures and pairs each message with an HTTP status code.

Defined domain errors:

- `tableCapacityExceeded()` -> `422 Unprocessable Entity`
- `tableAlreadyBooked()` -> `409 Conflict`
- `noTableAvailable()` -> `422 Unprocessable Entity`
- `cancellationNotAllowed()` -> `422 Unprocessable Entity`
- `dateInPast()` -> `422 Unprocessable Entity`
- `dateTooFarAhead()` -> `422 Unprocessable Entity`
- `invalidSlot()` -> `422 Unprocessable Entity`

Why this design is useful:

- Controllers remain small.
- Business messages stay consistent.
- HTTP status codes are attached close to the rule that failed.

## Database-Level Protection

Schema files:

- [database/migrations/2024_01_01_000001_create_tables_table.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/database/migrations/2024_01_01_000001_create_tables_table.php:1)
- [database/migrations/2024_01_01_000002_create_reservations_table.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/database/migrations/2024_01_01_000002_create_reservations_table.php:1)

Important database rules:

- `tables.table_number` is unique.
- `reservations.reference_code` is unique.
- `reservations.table_id` is a foreign key to `tables.id`.
- `reservations` has a unique constraint on:
  `table_id + reservation_date + slot_start`

This means even if two requests race each other, the database still protects against double booking of the same table for the same slot.

## Configuration-Driven Behavior

Configuration file: [config/reservation.php](/abs/c:/Coding%20Playground%20Self/restaurant-reservation/restaurant-reservation/config/reservation.php:1)

The API behavior depends on config values instead of hard-coded constants:

- `slot_duration_minutes`
- `max_advance_days`
- `min_advance_hours`
- `cancellation_cutoff_hours`
- `open_time`
- `close_time`
- `statuses`
- `locations`

This makes the code easy to adapt for another restaurant without changing the service logic.

## Summary

The API is designed with a clean separation of concerns:

- routes define the public contract
- controllers handle HTTP input and output
- requests validate incoming data
- services contain business logic
- resources shape JSON responses
- models hold relationships and status helpers
- exceptions represent business-rule failures

If you want, this file can also be turned into:

- a client-facing API document with sample requests and responses only
- a developer handoff document with sequence diagrams
- a Postman-style endpoint guide
