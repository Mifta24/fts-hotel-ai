# FTS HOTEL AI — Admin CMS

## 1. Objective
The CMS manages all data used by FTS HOTEL AI without changing source code.

## 2. Main Admin Modules
- Hotel Profile
- Rooms
- Facilities
- Policies
- FAQ
- Knowledge Base
- Reservation Requests
- Conversation Logs
- Handover Queue
- Scene / Branding Settings

## 3. Scene / Branding Settings
New V2 requirement.

Admin should be able to manage:
- FTS HOTEL AI logo
- lobby background
- reception background
- AI receptionist image / avatar
- tagline
- Enter button text
- default language
- visual asset status

Optional:
- room scene background
- facility scene background
- loading image

## 4. Hotel Profile
Editable:
- name
- description
- address
- phone
- WhatsApp
- email
- check-in
- check-out
- supported languages

## 5. Rooms
Admin can:
- create room
- edit room
- upload images
- set occupancy
- set room size
- set bed type
- set price range
- set breakfast
- set smoking
- activate / deactivate

## 6. Facilities
Admin can:
- create
- edit
- upload image
- set opening hours
- set fee
- activate / deactivate

## 7. Policies
Manage:
- check-in
- check-out
- cancellation
- smoking
- pets
- children
- deposit
- payment

## 8. FAQ
Functions:
- create
- edit
- language
- category
- order
- status

## 9. Knowledge
Functions:
- create / upload
- edit
- categorize
- language
- draft
- approve
- archive
- re-index

Only approved content should reach production AI.

## 10. Reservation Requests
List:
- reference
- guest
- dates
- guests
- room
- contact
- status

Actions:
- open
- contact guest
- change status
- add note

## 11. Conversation Logs
View:
- messages
- selected scene
- selected room
- reservation state
- handover reason

## 12. Handover Queue
Statuses:
- New
- Assigned
- Contacted
- Closed

## 13. Media Rules
Allowed:
- JPG
- PNG
- WebP
- SVG for logo/icon if sanitized

Recommended:
- optimized image upload
- thumbnail generation
- file size limit
- malware / file validation

## 14. V1 Priority
Must Have:
- hotel
- rooms
- facilities
- policies
- FAQs
- knowledge
- reservation
- lobby / receptionist branding assets
