# FTS HOTEL AI — Project Concept

## 1. Project Name
**FTS HOTEL AI**

## 2. Product Vision
FTS HOTEL AI is a full-screen, one-page interactive hotel experience.

The website should feel like the customer is entering a real hotel lobby and being welcomed by an AI receptionist / concierge.

This is **not** a traditional hotel website with long scrolling sections.

The entire experience runs inside a fixed viewport:

- `height: 100vh`
- `overflow: hidden`
- no long vertical scrolling
- scene-based navigation
- chat, room information, reservation, facilities, and handover appear as overlays, panels, or scene transitions

## 3. Core Experience

```text
Open Website
    ↓
Opening Lobby
    ↓
[ Enter FTS HOTEL AI ]
    ↓
AI Receptionist
    ↓
Choose Need / Ask AI
    ↓
Room / Facilities / Reservation / Hotel Info
    ↓
Human Handover if needed
```

## 4. Main UX Principle
The customer should never feel like they are browsing many pages.

Instead, the website behaves like an interactive application.

Traditional structure to avoid:

```text
Home
↓
About
↓
Rooms
↓
Facilities
↓
Contact
↓
Footer
```

Target structure:

```text
Lobby
↕
Reception
↕
Room
↕
Facilities
↕
Reservation
↕
Staff Handover
```

## 5. Scene 0 — Opening Lobby
This is the first screen.

Purpose:
- establish the hotel atmosphere
- introduce the FTS HOTEL AI brand
- provide one clear action to enter the experience

Main elements:
- full-screen luxury hotel lobby
- FTS HOTEL AI logo
- short tagline
- one primary CTA

Suggested CTA:

**Enter FTS HOTEL AI**

Alternative:

**Enter Hotel**

Suggested tagline:

**AI Concierge for a Smarter Stay**

or

**Your Intelligent Hotel Experience**

## 6. Scene 1 — AI Receptionist
After pressing Enter, transition to a closer reception view.

Main elements:
- AI receptionist / concierge
- hotel branding
- AI greeting
- quick action menu
- chat input
- optional microphone
- no page scroll

Suggested quick actions:
- Check Rooms
- Reservation
- Facilities
- Hotel Information
- Talk to Staff

Example greeting:

> Welcome to FTS HOTEL AI. How can I assist you today?

## 7. Scene 2 — Room Experience
When the customer chooses rooms:

- scene changes without leaving the app
- room image / room visual becomes the main focus
- AI remains available
- room information appears in a panel
- customer can continue chatting

Information:
- room name
- room images
- room size
- bed type
- occupancy
- room facilities
- price range
- breakfast
- smoking policy
- availability status if connected

Actions:
- Ask AI
- Previous Room
- Next Room
- Reservation
- Back to Reception

## 8. Scene 3 — Facilities
Facilities appear as a full-screen scene or overlay.

Possible categories:
- Restaurant
- Swimming Pool
- Gym
- Spa
- Meeting Room
- Parking
- Wi-Fi
- Airport Transfer

Actions:
- Ask About Facility
- View Another Facility
- Back to Reception

## 9. Scene 4 — Reservation
Reservation should be guided, not a long form.

Steps:
1. Check-in
2. Check-out
3. Guests
4. Number of rooms
5. Preferred room
6. Customer name
7. Contact
8. Summary

Final actions:
- Submit Reservation Request
- Send to WhatsApp
- Contact Hotel Staff
- Back

## 10. Scene 5 — Human Handover
If the user needs staff support:

- AI explains that a hotel staff member can assist
- conversation summary is prepared
- user chooses a handover channel

Channels:
- WhatsApp
- Phone
- Email

## 11. AI Concierge Role
The AI acts like a professional hotel receptionist.

Responsibilities:
- welcome visitors
- answer hotel questions
- explain rooms
- explain facilities
- explain policies
- support reservation requests
- recommend suitable room types
- hand over to staff when necessary

The AI must never invent hotel facts.

## 12. Language
V1:
- English
- Indonesian

Future:
- Japanese
- Chinese
- Korean

## 13. V1 Scope

### Must Have
- FTS HOTEL AI branding
- 100vh full-screen layout
- no long vertical scroll
- opening lobby scene
- Enter button
- AI receptionist scene
- room scene
- facilities scene
- reservation scene
- AI chat
- hotel knowledge base
- WhatsApp / staff handover
- desktop + mobile responsive
- English + Indonesian

### Nice to Have
- voice input
- AI voice response
- animated receptionist
- advanced scene transitions
- chat history panel

### Not Required for V1
- direct payment
- PMS integration
- OTA integration
- full 3D hotel
- real-time avatar lip-sync
- autonomous final booking confirmation

## 14. Success Criteria
The V1 is successful when a visitor can:

1. Open the website and see a hotel lobby.
2. Enter FTS HOTEL AI from one clear button.
3. Meet the AI receptionist.
4. Ask questions naturally.
5. View room options.
6. Ask about facilities.
7. Start a reservation.
8. Contact human hotel staff.
9. Complete the whole journey without scrolling through a long webpage.
