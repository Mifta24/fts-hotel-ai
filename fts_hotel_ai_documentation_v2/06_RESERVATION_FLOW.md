# FTS HOTEL AI — Reservation Flow

## 1. UX Principle
Reservation must happen inside the fixed full-screen experience.

Do not redirect the customer to a long standalone form.

Use:
- modal
- right-side panel
- scene change
- step-by-step wizard

## 2. Entry Points
Reservation can start from:
- Reception Scene
- Room Scene
- AI conversation
- Facility / event conversation
- direct reservation quick action

## 3. Required Fields
- Check-in
- Check-out
- Guests
- Number of rooms
- Preferred room
- Customer name
- Contact method

Optional:
- children
- special request
- smoking preference
- breakfast
- accessibility needs

## 4. Step Flow

### Step 1 — Dates
Ask:
- check-in
- check-out

### Step 2 — Guests
Ask:
- adults
- children if needed
- number of rooms

### Step 3 — Room
Use selected room if already chosen.

Otherwise:
- ask preference
or
- recommend from known data

### Step 4 — Customer
Ask:
- name
- contact method

### Step 5 — Summary
Show:
- room
- dates
- guests
- rooms
- special request
- contact

Then:
- Confirm Request
- Edit
- Back

## 5. Date Validation
Rules:
- check-in cannot be in the past
- check-out must be after check-in
- use hotel timezone
- invalid input should be corrected immediately

## 6. Occupancy Validation
Check:
- room capacity
- number of guests
- number of rooms

If capacity is exceeded:
- suggest another room type
- suggest multiple rooms
- offer staff assistance

## 7. Availability

### If real-time system is connected
Show actual availability.

### If not connected
Use:

> This is a reservation request. Final availability will be confirmed by hotel staff.

Never fake availability.

## 8. Summary Example

```text
Reservation Request

Room: Deluxe King Room
Check-in: 10 October 2026
Check-out: 13 October 2026
Guests: 2
Rooms: 1
Name: John Tan
Contact: WhatsApp
Status: Awaiting Hotel Confirmation
```

## 9. WhatsApp Handover
Suggested prefilled message:

```text
Hello, I would like to request a reservation.

Name: {{name}}
Check-in: {{check_in}}
Check-out: {{check_out}}
Guests: {{guests}}
Rooms: {{rooms}}
Room Type: {{room_type}}
Special Request: {{special_request}}

Reference: {{reference}}
```

## 10. Statuses
Suggested backend statuses:
- draft
- awaiting_customer_confirmation
- submitted
- contacted
- confirmed
- rejected
- cancelled

## 11. Exit / Back
At every reservation step:
- Back
- Close
- Return to Reception

Closing should not automatically delete progress.

## 12. V1 Payment Rule
Do not collect card data in AI chat.

If payment is needed:
- open official hotel payment process
or
- hand over to staff
