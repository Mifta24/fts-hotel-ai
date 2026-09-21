# FTS HOTEL AI — Database Schema

## 1. Core Tables

### hotels
- id
- name
- slug
- description
- address
- city
- country
- phone
- whatsapp
- email
- check_in_time
- check_out_time
- default_language
- active
- created_at
- updated_at

### rooms
- id
- hotel_id
- code
- name
- description
- size_sqm
- bed_type
- max_adults
- max_children
- view_type
- smoking_allowed
- breakfast_included
- price_min
- price_max
- currency
- active
- created_at
- updated_at

### room_images
- id
- room_id
- image_url
- alt_text
- sort_order
- active

### room_amenities
- id
- room_id
- name
- description
- icon
- sort_order

### facilities
- id
- hotel_id
- name
- slug
- description
- location
- opening_time
- closing_time
- fee_text
- image_url
- active

### hotel_policies
- id
- hotel_id
- policy_type
- title
- content
- language
- active
- effective_from

### faqs
- id
- hotel_id
- category
- question
- answer
- language
- sort_order
- active

### knowledge_documents
- id
- hotel_id
- title
- category
- language
- content
- source_type
- status
- version
- approved_by
- approved_at

### conversations
- id
- hotel_id
- session_id
- language
- current_scene
- selected_room_id
- status
- started_at
- ended_at

### messages
- id
- conversation_id
- role
- content
- intent
- model
- latency_ms
- metadata
- created_at

### reservation_requests
- id
- reference
- hotel_id
- conversation_id
- room_id
- check_in
- check_out
- adults
- children
- room_count
- guest_name
- contact_type
- contact_value
- special_request
- status
- submitted_at

### handovers
- id
- conversation_id
- reservation_request_id
- channel
- reason
- summary
- status
- staff_user_id
- created_at

## 2. Scene State
Store scene-related state in `conversations` or `sessions`.

Recommended:
- current_scene
- selected_room_id
- selected_facility_id
- reservation_state
- language

## 3. Optional Session Table
If needed:

### visitor_sessions
- id
- hotel_id
- session_uuid
- current_scene
- language
- selected_room_id
- reservation_state_json
- expires_at
- created_at
- updated_at

## 4. Admin Users
Use existing users table or:

- id
- hotel_id
- name
- email
- password_hash
- role
- active

## 5. Future Tables
- room_inventory
- room_rates
- bookings
- payments
- integrations
- promotions
- analytics_events
- guest_profiles
