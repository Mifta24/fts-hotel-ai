# FTS HOTEL AI — Scene & Asset List

## 1. Scene 0 — Opening Lobby

Required:
- lobby desktop background
- lobby mobile background
- FTS HOTEL AI logo
- tagline
- Enter button
- optional language selector

Suggested filenames:

```text
lobby-desktop.webp
lobby-mobile.webp
fts-hotel-ai-logo.svg
```

## 2. Scene 1 — AI Reception
Required:
- reception desktop background
- reception mobile background
- AI receptionist / concierge image
- chat UI
- quick action icons

Suggested:

```text
reception-desktop.webp
reception-mobile.webp
ai-receptionist-default.webp
```

Optional AI states:
- greeting
- listening
- speaking
- thinking

## 3. Scene 2 — Rooms
For every room:
- hero image
- gallery
- thumbnail
- optional background crop

Example:

```text
rooms/
  deluxe-king/
    hero.webp
    01.webp
    02.webp
    03.webp
```

## 4. Scene 3 — Facilities
Assets:
- restaurant
- pool
- gym
- spa
- meeting room
- parking
- airport transfer

## 5. Scene 4 — Reservation
UI assets:
- date icon
- guest icon
- room icon
- contact icon
- confirmation icon

No separate large background is required if using modal / overlay.

## 6. Scene 5 — Human Handover
Assets:
- WhatsApp icon
- phone icon
- email icon
- optional staff / consultation visual

## 7. AI Receptionist Requirements
The character should:
- look like hotel staff
- remain visually consistent
- use the same uniform
- use similar lighting
- be suitable for desktop and mobile crop

Recommended:
- transparent WebP / PNG when layering
- high-resolution source retained separately

## 8. Brand
Required:
- FTS HOTEL AI logo
- favicon
- light logo
- dark logo
- approved typography
- brand color reference

## 9. UI Icons
- Enter
- Rooms
- Reservation
- Facilities
- Info
- Staff
- Microphone
- Send
- Back
- Close
- Language

Prefer SVG.

## 10. Image Size Guidance
Desktop source:
- minimum 1920×1080

Mobile source:
- minimum 1080×1920

## 11. Performance
- use WebP / AVIF where possible
- compress
- lazy-load non-current scenes
- preload lobby and reception
- do not load every room image at initial startup

## 12. Asset Checklist

### Opening Lobby
- [ ] Desktop lobby
- [ ] Mobile lobby
- [ ] FTS HOTEL AI logo
- [ ] Tagline
- [ ] Enter button styling

### Reception
- [ ] Desktop reception background
- [ ] Mobile reception background
- [ ] AI receptionist
- [ ] Action icons
- [ ] Chat UI

### Rooms
- [ ] Room 1
- [ ] Room 2
- [ ] Room 3

### Facilities
- [ ] Restaurant
- [ ] Pool
- [ ] Gym
- [ ] Other facilities

### Responsive
- [ ] Desktop crops
- [ ] Tablet crops
- [ ] Mobile crops
