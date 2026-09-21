# FTS HOTEL AI — API & Backend Specification

## 1. Main Principle
Frontend is a scene-based single-page experience.

Backend provides:
- session state
- chat
- hotel data
- rooms
- facilities
- reservation
- handover
- admin data

Base:
`/api/v1`

## 2. Session

### POST `/sessions`
Creates a visitor session.

Suggested response:

```json
{
  "session_id": "uuid",
  "language": "en",
  "current_scene": "lobby"
}
```

### PATCH `/sessions/{id}`
Update:
- language
- current_scene
- selected_room
- reservation_state

## 3. Scene State
Suggested scene values:
- lobby
- reception
- rooms
- room_detail
- facilities
- facility_detail
- reservation
- handover

The backend may persist current scene so the session can resume.

## 4. Chat

### POST `/chat/messages`

Request:

```json
{
  "session_id": "uuid",
  "message": "Show me a room for two people",
  "language": "en",
  "current_scene": "reception",
  "selected_room_id": null
}
```

Response:

```json
{
  "reply": "I can help you with that.",
  "intent": "room_search",
  "next_scene": "rooms",
  "suggested_actions": [
    "View Rooms",
    "Start Reservation"
  ],
  "handover_required": false
}
```

## 5. Hotel

### GET `/hotel`
Returns:
- name
- logo
- contact
- check-in / check-out
- supported languages

## 6. Rooms

### GET `/rooms`

Optional filters:
- guests
- check_in
- check_out
- bed_type
- smoking

### GET `/rooms/{room}`

Return:
- details
- images
- amenities
- price range
- availability if connected

## 7. Facilities

### GET `/facilities`
### GET `/facilities/{facility}`

## 8. Reservation

### POST `/reservation-requests`

Data:
- session_id
- check_in
- check_out
- adults
- children
- rooms
- room_type_id
- guest_name
- contact
- special_request

### GET `/reservation-requests/{reference}`
Customer-safe status only.

## 9. Handover

### POST `/handovers`

Request:

```json
{
  "session_id": "uuid",
  "channel": "whatsapp",
  "reason": "customer_requested_staff"
}
```

Response:
- handover URL
- summary
- reference

## 10. General Response
Success:

```json
{
  "success": true,
  "data": {},
  "message": null
}
```

Error:

```json
{
  "success": false,
  "data": null,
  "message": "Human readable error",
  "error_code": "VALIDATION_ERROR"
}
```

## 11. AI Flow

```text
Receive Chat Message
    ↓
Validate
    ↓
Load Session + Current Scene
    ↓
Retrieve Relevant Knowledge
    ↓
Build AI Context
    ↓
Call Model
    ↓
Validate Response
    ↓
Save Conversation
    ↓
Return Reply + Suggested UI Action + Optional Next Scene
```

## 12. Admin Endpoints
Suggested:
- CRUD hotel
- CRUD rooms
- CRUD facilities
- CRUD policies
- CRUD FAQs
- CRUD knowledge
- list conversations
- list reservations
- list handovers

## 13. Security
- admin auth
- role-based authorization
- rate limiting
- server-side validation
- server-side AI keys only
- no secrets in frontend
- secure file upload validation

## 14. Logging
Log:
- request ID
- session ID
- current scene
- selected room
- AI latency
- model
- errors
- reservation reference

Do not log unnecessary sensitive information.
