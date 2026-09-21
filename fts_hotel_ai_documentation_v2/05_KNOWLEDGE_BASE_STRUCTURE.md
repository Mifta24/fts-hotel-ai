# FTS HOTEL AI — Knowledge Base Structure

## 1. Purpose
The knowledge base is the approved source of hotel facts used by the AI concierge.

## 2. Main Knowledge Groups

```text
knowledge/
├── hotel/
├── rooms/
├── facilities/
├── policies/
├── faq/
├── dining/
├── nearby_places/
├── reservation/
└── contact/
```

## 3. Hotel Profile
Recommended file:
`hotel/overview.md`

Include:
- hotel name
- description
- address
- city
- country
- phone
- WhatsApp
- email
- website
- map link
- check-in
- check-out
- languages
- hotel introduction text

## 4. Room Data
One file per room type.

Example:
`rooms/deluxe-king.md`

Fields:
- room code
- room name
- description
- room size
- bed type
- maximum occupancy
- view
- smoking rule
- breakfast
- price range
- facilities
- images
- reservation notes

## 5. Facilities
For each facility:
- name
- description
- opening hours
- location
- additional fee
- images
- reservation requirement

## 6. Policies
Include:
- check-in
- check-out
- cancellation
- no-show
- smoking
- pet
- child
- deposit
- payment
- visitor
- early check-in
- late check-out

## 7. FAQ
Examples:
- Is breakfast included?
- Is parking available?
- Is Wi-Fi free?
- Is airport pickup available?
- What time is check-in?
- Can I request early check-in?

## 8. Nearby Places
Include:
- place name
- category
- distance
- travel time
- transport method
- notes

Only use verified information.

## 9. Reservation Rules
Include:
- required reservation fields
- occupancy rules
- confirmation rules
- deposit rules
- cancellation rules
- escalation rules
- availability disclaimer

## 10. Contact
Include:
- front desk
- reservation team
- WhatsApp
- phone
- email
- contact hours

## 11. Metadata
Recommended:

```yaml
title:
category:
language:
hotel_id:
last_updated:
approved_by:
status: draft|approved|archived
```

## 12. Production Rule
Only approved knowledge should be used in production.

## 13. Update Flow

```text
Staff edits knowledge
    ↓
Review
    ↓
Approve
    ↓
Re-index / refresh
    ↓
AI validation test
    ↓
Production
```

## 14. Priority
If information conflicts:

1. current structured hotel data
2. approved current document
3. approved FAQ
4. legacy source

Never prefer old data over newer approved data.
