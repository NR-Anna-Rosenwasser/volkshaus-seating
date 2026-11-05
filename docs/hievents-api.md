Event Types
In Event Types you pick them by name. The event_type value is what arrives in the request.

Name	event_type	Sent when
Order Created	order.created	A new order is placed
Order Updated	order.updated	An order is changed
Order Marked as Paid	order.marked_as_paid	An order is marked as paid
Order Refunded	order.refunded	An order is refunded
Order Cancelled	order.cancelled	An order is cancelled
Attendee Created	attendee.created	A new attendee is added
Attendee Updated	attendee.updated	An attendee is changed
Attendee Cancelled	attendee.cancelled	An attendee is cancelled
Product Created	product.created	A product is created
Product Updated	product.updated	A product is changed
Product Deleted	product.deleted	A product is deleted
Event Created	event.created	An event is created (organizer level only)
Event Updated	event.updated	An event is changed (organizer level only)
Event Archived	event.archived	An event is archived (organizer level only)
Check-in Created	checkin.created	An attendee is checked in
Check-in Deleted	checkin.deleted	A check-in is undone
Occurrence Cancelled	occurrence.cancelled	A date is cancelled on a recurring event



# Get Order

- **Method:** `GET`
- **Path:** `/events/{eventId}/orders/{orderId}`
- **Operation ID:** `orders.getOrderAction`
- **Tags:** Orders

## Effective servers

- `/`

## Authentication

- **http**: HTTP bearer

  An API key (`hie_…`) or a session token from `POST /auth/login`.

## Path parameters

- **`eventId` (required)**: `integer`
- **`orderId` (required)**: `integer`

## Responses

### 200 \`OrderResource\`

**Content type:** `application/json`

- **`data` (required)**: `object`, schema: `OrderResource`
  - **`address` (required)**: `null | (array of any) | string | object`
  - **`box_office_id` (required)**: `integer | null`
  - **`created_at` (required)**: `string`
  - **`currency` (required)**: `string`
  - **`email` (required)**: `string | null`
  - **`event_id` (required)**: `integer`
  - **`first_name` (required)**: `string | null`
  - **`id` (required)**: `integer`
  - **`is_free_order` (required)**: `boolean`
  - **`is_fully_refunded` (required)**: `boolean`
  - **`is_manually_created` (required)**: `boolean`
  - **`is_partially_refunded` (required)**: `boolean`
  - **`last_name` (required)**: `string | null`
  - **`notes` (required)**: `string | null`
  - **`payment_provider` (required)**: `string | null`
  - **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
  - **`promo_code` (required)**: `string | null`
  - **`public_id` (required)**: `string`
  - **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
  - **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
  - **`attendees`**: `array | null`

    **Items:**

    schema: `AttendeeResource`
    - **`created_at` (required)**: `string`
    - **`email` (required)**: `string | null`
    - **`event_id` (required)**: `integer`
    - **`event_occurrence_id` (required)**: `integer | null`
    - **`first_name` (required)**: `string`
    - **`id` (required)**: `integer`
    - **`last_name` (required)**: `string`
    - **`locale` (required)**: `string`
    - **`notes` (required)**: `string | null`
    - **`order_id` (required)**: `integer`
    - **`product_id` (required)**: `integer`
    - **`product_price_id` (required)**: `integer`
    - **`public_id` (required)**: `string`
    - **`seat_label` (required)**: `string | null`
    - **`seat_uid` (required)**: `string | null`
    - **`short_id` (required)**: `string`
    - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
    - **`updated_at` (required)**: `string`
    - **`check_ins`**: `array | null`

      **Items:**

      schema: `AttendeeCheckInResource`
      - **`attendee_id` (required)**: `integer`
      - **`check_in_list_id` (required)**: `integer`
      - **`created_at` (required)**: `string | null`
      - **`event_id` (required)**: `integer`
      - **`event_occurrence_id` (required)**: `integer | null`
      - **`id` (required)**: `integer`
      - **`product_id` (required)**: `integer`
      - **`short_id` (required)**: `string`
      - **`attendee`**: `object`
        - **`created_at` (required)**: `string`
        - **`email` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`event_occurrence_id` (required)**: `integer | null`
        - **`first_name` (required)**: `string`
        - **`id` (required)**: `integer`
        - **`last_name` (required)**: `string`
        - **`locale` (required)**: `string`
        - **`notes` (required)**: `string | null`
        - **`order_id` (required)**: `integer`
        - **`product_id` (required)**: `integer`
        - **`product_price_id` (required)**: `integer`
        - **`public_id` (required)**: `string`
        - **`seat_label` (required)**: `string | null`
        - **`seat_uid` (required)**: `string | null`
        - **`short_id` (required)**: `string`
        - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
        - **`updated_at` (required)**: `string`
        - **`check_ins`**: `array | null`

          **Items:**

          *\[Circular Reference]*
        - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`
          - **`available_capacity` (required)**: `integer | null`
          - **`capacity` (required)**: `integer | null`
          - **`created_at` (required)**: `string | null`
          - **`end_date` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`id` (required)**: `integer`
          - **`is_active` (required)**: `boolean`
          - **`is_future` (required)**: `boolean`
          - **`is_overridden` (required)**: `boolean`
          - **`is_past` (required)**: `boolean`
          - **`label` (required)**: `string | null`
          - **`short_id` (required)**: `string`
          - **`show_available_capacity` (required)**: `boolean | null`
          - **`start_date` (required)**: `string`
          - **`status` (required)**: `string`
          - **`updated_at` (required)**: `string | null`
          - **`used_capacity` (required)**: `integer`
          - **`booking_limits`**: `object`
            - **`allocation_total` (required)**: `integer | null`
            - **`allocations` (required)**: `array of any`
            - **`capacity` (required)**: `integer | null`
            - **`sellable` (required)**: `integer | null`
          - **`event_location`**: `object | null`, schema: `EventLocationResource`
            - **`id` (required)**: `integer`
            - **`location_id` (required)**: `integer | null`
            - **`type` (required)**: `string`
            - **`location`**: `object | null`, schema: `LocationResource`
              - **`created_at` (required)**: `string | null`
              - **`id` (required)**: `integer`
              - **`latitude` (required)**: `number | null`
              - **`longitude` (required)**: `number | null`
              - **`name` (required)**: `string | null`
              - **`organizer_id` (required)**: `integer`
              - **`provider` (required)**: `string | null`
              - **`provider_place_id` (required)**: `string | null`
              - **`structured_address` (required)**: `null | (array of any) | string | object`
              - **`updated_at` (required)**: `string | null`
            - **`online_event_connection_details`**: `string | null`
          - **`statistics`**: `object`
            - **`attendees_registered` (required)**: `integer`
            - **`orders_created` (required)**: `integer`
            - **`products_sold` (required)**: `integer`
            - **`total_fee`**: `number`
            - **`total_gross_sales`**: `number`
            - **`total_refunded`**: `number`
            - **`total_tax`**: `number`
        - **`order`**: `object | null`, schema: `OrderResource`
          - **`address` (required)**: `null | (array of any) | string | object`
          - **`box_office_id` (required)**: `integer | null`
          - **`created_at` (required)**: `string`
          - **`currency` (required)**: `string`
          - **`email` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`first_name` (required)**: `string | null`
          - **`id` (required)**: `integer`
          - **`is_free_order` (required)**: `boolean`
          - **`is_fully_refunded` (required)**: `boolean`
          - **`is_manually_created` (required)**: `boolean`
          - **`is_partially_refunded` (required)**: `boolean`
          - **`last_name` (required)**: `string | null`
          - **`notes` (required)**: `string | null`
          - **`payment_provider` (required)**: `string | null`
          - **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
          - **`promo_code` (required)**: `string | null`
          - **`public_id` (required)**: `string`
          - **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
          - **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
          - **`attendees`**: `array | null`

            **Items:**

            schema: `AttendeeResource`
            - **`created_at` (required)**: `string`
            - **`email` (required)**: `string | null`
            - **`event_id` (required)**: `integer`
            - **`event_occurrence_id` (required)**: `integer | null`
            - **`first_name` (required)**: `string`
            - **`id` (required)**: `integer`
            - **`last_name` (required)**: `string`
            - **`locale` (required)**: `string`
            - **`notes` (required)**: `string | null`
            - **`order_id` (required)**: `integer`
            - **`product_id` (required)**: `integer`
            - **`product_price_id` (required)**: `integer`
            - **`public_id` (required)**: `string`
            - **`seat_label` (required)**: `string | null`
            - **`seat_uid` (required)**: `string | null`
            - **`short_id` (required)**: `string`
            - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
            - **`updated_at` (required)**: `string`
            - **`check_ins`**: `array | null`

              **Items:**

              schema: `AttendeeCheckInResource`
              - **`attendee_id` (required)**: `integer`
              - **`check_in_list_id` (required)**: `integer`
              - **`created_at` (required)**: `string | null`
              - **`event_id` (required)**: `integer`
              - **`event_occurrence_id` (required)**: `integer | null`
              - **`id` (required)**: `integer`
              - **`product_id` (required)**: `integer`
              - **`short_id` (required)**: `string`
              - **`attendee`**: `object`
                - **`created_at` (required)**: `string`
                - **`email` (required)**: `string | null`
                - **`event_id` (required)**: `integer`
                - **`event_occurrence_id` (required)**: `integer | null`
                - **`first_name` (required)**: `string`
                - **`id` (required)**: `integer`
                - **`last_name` (required)**: `string`
                - **`locale` (required)**: `string`
                - **`notes` (required)**: `string | null`
                - **`order_id` (required)**: `integer`
                - **`product_id` (required)**: `integer`
                - **`product_price_id` (required)**: `integer`
                - **`public_id` (required)**: `string`
                - **`seat_label` (required)**: `string | null`
                - **`seat_uid` (required)**: `string | null`
                - **`short_id` (required)**: `string`
                - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
                - **`updated_at` (required)**: `string`
                - **`check_ins`**: `array | null`

                  **Items:**

                  *\[Circular Reference]*
                - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                  *Schema `EventOccurrenceResource` is shown above.*
                - **`order`**: `object | null`, schema: `OrderResource`

                  *\[Circular Reference]*
                - **`product`**: `object | null`, schema: `ProductResource`
                  - **`description` (required)**: `string | null`
                  - **`event_id` (required)**: `integer`
                  - **`hide_after_sale_end_date` (required)**: `boolean`
                  - **`hide_before_sale_start_date` (required)**: `boolean`
                  - **`hide_when_sold_out` (required)**: `boolean`
                  - **`highlight_message` (required)**: `string | null`
                  - **`id` (required)**: `integer`
                  - **`initial_quantity_available` (required)**: `integer | null`
                  - **`is_addon_only` (required)**: `boolean`
                  - **`is_after_sale_end_date` (required)**: `boolean`
                  - **`is_available` (required)**: `boolean`
                  - **`is_before_sale_start_date` (required)**: `boolean`
                  - **`is_hidden` (required)**: `boolean | null`
                  - **`is_hidden_without_promo_code` (required)**: `boolean`
                  - **`is_highlighted` (required)**: `boolean`
                  - **`max_per_order` (required)**: `integer`
                  - **`min_per_order` (required)**: `integer`
                  - **`order` (required)**: `integer`
                  - **`product_category_id` (required)**: `integer | null`
                  - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
                  - **`quantity_sold` (required)**: `integer`
                  - **`sale_end_date` (required)**: `string | null`
                  - **`sale_start_date` (required)**: `string | null`
                  - **`sequential_tier_release_enabled` (required)**: `boolean`
                  - **`show_quantity_remaining` (required)**: `boolean`
                  - **`start_collapsed` (required)**: `boolean`
                  - **`title` (required)**: `string`
                  - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
                  - **`waitlist_enabled` (required)**: `boolean | null`
                  - **`addon_product_ids`**: `(array of any) | null`
                  - **`addons`**: `array`

                    **Items:**
                    - **`id` (required)**: `integer`
                    - **`title` (required)**: `string`
                  - **`is_sold_out`**: `boolean`
                  - **`price`**: `number | null`
                  - **`prices`**: `array | null`

                    **Items:**

                    schema: `ProductPriceResource`
                    - **`id` (required)**: `integer`
                    - **`initial_quantity_available` (required)**: `integer | null`
                    - **`is_after_sale_end_date` (required)**: `boolean`
                    - **`is_available` (required)**: `boolean | null`
                    - **`is_before_sale_start_date` (required)**: `boolean`
                    - **`is_hidden` (required)**: `boolean | null`
                    - **`is_locked_behind_earlier_tier` (required)**: `boolean`
                    - **`is_sold_out` (required)**: `boolean`
                    - **`label` (required)**: `string | null`
                    - **`off_sale_reason` (required)**: `string | null`
                    - **`price` (required)**: `number`
                    - **`price_including_taxes_and_fees` (required)**: `number`
                    - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
                    - **`quantity_sold` (required)**: `integer`
                    - **`sale_end_date` (required)**: `string | null`
                    - **`sale_start_date` (required)**: `string | null`
                  - **`taxes_and_fees`**: `array | null`

                    **Items:**

                    schema: `TaxAndFeeResource`
                    - **`account_id` (required)**: `integer`
                    - **`calculation_type` (required)**: `string`
                    - **`description` (required)**: `string | null`
                    - **`id` (required)**: `integer`
                    - **`is_active` (required)**: `boolean | null`
                    - **`is_default` (required)**: `boolean`
                    - **`name` (required)**: `string`
                    - **`rate` (required)**: `number`
                    - **`type` (required)**: `string`
                - **`question_answers`**: `array | null`

                  **Items:**

                  schema: `QuestionAnswerViewResource`
                  - **`answer` (required)**: `(array of any) | string`
                  - **`belongs_to` (required)**: `string`
                  - **`event_id` (required)**: `integer`
                  - **`order_id` (required)**: `integer | null`
                  - **`product_id` (required)**: `integer | null`
                  - **`product_title` (required)**: `string | null`
                  - **`question_answer_id` (required)**: `integer`
                  - **`question_description` (required)**: `string | null`
                  - **`question_id` (required)**: `integer`
                  - **`question_options` (required)**: `(array of any) | null`
                  - **`question_required` (required)**: `boolean`
                  - **`question_type` (required)**: `string`
                  - **`text_answer` (required)**: `string`
                  - **`title` (required)**: `string`
                  - **`attendee_id`**: `integer | null`
                  - **`attendee_public_id`**: `string | null`
                  - **`first_name`**: `string | null`
                  - **`last_name`**: `string | null`
              - **`check_in_list`**: `object`
                - **`activates_at` (required)**: `string | null`
                - **`checked_in_attendees` (required)**: `integer | null`
                - **`description` (required)**: `string | null`
                - **`event_occurrence_id` (required)**: `integer | null`
                - **`expires_at` (required)**: `string | null`
                - **`id` (required)**: `integer`
                - **`is_system_default` (required)**: `boolean`
                - **`name` (required)**: `string`
                - **`public_show_attendee_notes` (required)**: `boolean`
                - **`public_show_order_details` (required)**: `boolean`
                - **`public_show_question_answers` (required)**: `boolean`
                - **`short_id` (required)**: `string`
                - **`total_attendees` (required)**: `integer | null`
                - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                  *Schema `EventOccurrenceResource` is shown above.*
                - **`is_active`**: `boolean`
                - **`is_expired`**: `boolean`
                - **`products`**: `array | null`

                  **Items:**

                  *Schema `ProductResource` is shown above.*
            - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

              *Schema `EventOccurrenceResource` is shown above.*
            - **`order`**: `object | null`, schema: `OrderResource`

              *\[Circular Reference]*
            - **`product`**: `object | null`, schema: `ProductResource`

              *Schema `ProductResource` is shown above.*
            - **`question_answers`**: `array | null`

              **Items:**

              *Schema `QuestionAnswerViewResource` is shown above.*
          - **`box_office_amount_tendered`**: `number | null`
          - **`box_office_change_due`**: `number | null`
          - **`box_office_operator_name`**: `string | null`
          - **`box_office_reference`**: `string | null`
          - **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
          - **`latest_invoice`**: `object`
            - **`id` (required)**: `integer`
            - **`invoice_number` (required)**: `string`
            - **`order_id` (required)**: `integer`
            - **`status` (required)**: `string`
          - **`order_items`**: `array | null`

            **Items:**

            schema: `OrderItemResource`
            - **`band_key` (required)**: `string | null`
            - **`event_occurrence_id` (required)**: `integer | null`
            - **`id` (required)**: `integer`
            - **`item_name` (required)**: `string | null`
            - **`order_id` (required)**: `integer`
            - **`product_id` (required)**: `integer`
            - **`quantity` (required)**: `integer`
            - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

              *Schema `EventOccurrenceResource` is shown above.*
            - **`price`**: `number`
            - **`price_before_discount`**: `number | null`
            - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
            - **`total_before_additions`**: `number`
          - **`question_answers`**: `array | null`

            **Items:**

            *Schema `QuestionAnswerViewResource` is shown above.*
          - **`short_id`**: `string`
          - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
          - **`total_before_additions`**: `number`
          - **`total_fee`**: `number`
          - **`total_gross`**: `number`
          - **`total_refunded`**: `number`
          - **`total_tax`**: `number`
        - **`product`**: `object | null`, schema: `ProductResource`

          *Schema `ProductResource` is shown above.*
        - **`question_answers`**: `array | null`

          **Items:**

          schema: `QuestionAnswerViewResource`
          - **`answer` (required)**: `(array of any) | string`
          - **`belongs_to` (required)**: `string`
          - **`event_id` (required)**: `integer`
          - **`order_id` (required)**: `integer | null`
          - **`product_id` (required)**: `integer | null`
          - **`product_title` (required)**: `string | null`
          - **`question_answer_id` (required)**: `integer`
          - **`question_description` (required)**: `string | null`
          - **`question_id` (required)**: `integer`
          - **`question_options` (required)**: `(array of any) | null`
          - **`question_required` (required)**: `boolean`
          - **`question_type` (required)**: `string`
          - **`text_answer` (required)**: `string`
          - **`title` (required)**: `string`
          - **`attendee_id`**: `integer | null`
          - **`attendee_public_id`**: `string | null`
          - **`first_name`**: `string | null`
          - **`last_name`**: `string | null`
      - **`check_in_list`**: `object`
        - **`activates_at` (required)**: `string | null`
        - **`checked_in_attendees` (required)**: `integer | null`
        - **`description` (required)**: `string | null`
        - **`event_occurrence_id` (required)**: `integer | null`
        - **`expires_at` (required)**: `string | null`
        - **`id` (required)**: `integer`
        - **`is_system_default` (required)**: `boolean`
        - **`name` (required)**: `string`
        - **`public_show_attendee_notes` (required)**: `boolean`
        - **`public_show_order_details` (required)**: `boolean`
        - **`public_show_question_answers` (required)**: `boolean`
        - **`short_id` (required)**: `string`
        - **`total_attendees` (required)**: `integer | null`
        - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

          *Schema `EventOccurrenceResource` is shown above.*
        - **`is_active`**: `boolean`
        - **`is_expired`**: `boolean`
        - **`products`**: `array | null`

          **Items:**

          schema: `ProductResource`
          - **`description` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`hide_after_sale_end_date` (required)**: `boolean`
          - **`hide_before_sale_start_date` (required)**: `boolean`
          - **`hide_when_sold_out` (required)**: `boolean`
          - **`highlight_message` (required)**: `string | null`
          - **`id` (required)**: `integer`
          - **`initial_quantity_available` (required)**: `integer | null`
          - **`is_addon_only` (required)**: `boolean`
          - **`is_after_sale_end_date` (required)**: `boolean`
          - **`is_available` (required)**: `boolean`
          - **`is_before_sale_start_date` (required)**: `boolean`
          - **`is_hidden` (required)**: `boolean | null`
          - **`is_hidden_without_promo_code` (required)**: `boolean`
          - **`is_highlighted` (required)**: `boolean`
          - **`max_per_order` (required)**: `integer`
          - **`min_per_order` (required)**: `integer`
          - **`order` (required)**: `integer`
          - **`product_category_id` (required)**: `integer | null`
          - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
          - **`quantity_sold` (required)**: `integer`
          - **`sale_end_date` (required)**: `string | null`
          - **`sale_start_date` (required)**: `string | null`
          - **`sequential_tier_release_enabled` (required)**: `boolean`
          - **`show_quantity_remaining` (required)**: `boolean`
          - **`start_collapsed` (required)**: `boolean`
          - **`title` (required)**: `string`
          - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
          - **`waitlist_enabled` (required)**: `boolean | null`
          - **`addon_product_ids`**: `(array of any) | null`
          - **`addons`**: `array`

            **Items:**
            - **`id` (required)**: `integer`
            - **`title` (required)**: `string`
          - **`is_sold_out`**: `boolean`
          - **`price`**: `number | null`
          - **`prices`**: `array | null`

            **Items:**

            schema: `ProductPriceResource`
            - **`id` (required)**: `integer`
            - **`initial_quantity_available` (required)**: `integer | null`
            - **`is_after_sale_end_date` (required)**: `boolean`
            - **`is_available` (required)**: `boolean | null`
            - **`is_before_sale_start_date` (required)**: `boolean`
            - **`is_hidden` (required)**: `boolean | null`
            - **`is_locked_behind_earlier_tier` (required)**: `boolean`
            - **`is_sold_out` (required)**: `boolean`
            - **`label` (required)**: `string | null`
            - **`off_sale_reason` (required)**: `string | null`
            - **`price` (required)**: `number`
            - **`price_including_taxes_and_fees` (required)**: `number`
            - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
            - **`quantity_sold` (required)**: `integer`
            - **`sale_end_date` (required)**: `string | null`
            - **`sale_start_date` (required)**: `string | null`
          - **`taxes_and_fees`**: `array | null`

            **Items:**

            schema: `TaxAndFeeResource`
            - **`account_id` (required)**: `integer`
            - **`calculation_type` (required)**: `string`
            - **`description` (required)**: `string | null`
            - **`id` (required)**: `integer`
            - **`is_active` (required)**: `boolean | null`
            - **`is_default` (required)**: `boolean`
            - **`name` (required)**: `string`
            - **`rate` (required)**: `number`
            - **`type` (required)**: `string`
    - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

      *Schema `EventOccurrenceResource` is shown above.*
    - **`order`**: `object | null`, schema: `OrderResource`

      *Schema `OrderResource` is shown above.*
    - **`product`**: `object | null`, schema: `ProductResource`

      *Schema `ProductResource` is shown above.*
    - **`question_answers`**: `array | null`

      **Items:**

      *Schema `QuestionAnswerViewResource` is shown above.*
  - **`box_office_amount_tendered`**: `number | null`
  - **`box_office_change_due`**: `number | null`
  - **`box_office_operator_name`**: `string | null`
  - **`box_office_reference`**: `string | null`
  - **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
  - **`latest_invoice`**: `object`
    - **`id` (required)**: `integer`
    - **`invoice_number` (required)**: `string`
    - **`order_id` (required)**: `integer`
    - **`status` (required)**: `string`
  - **`order_items`**: `array | null`

    **Items:**

    schema: `OrderItemResource`
    - **`band_key` (required)**: `string | null`
    - **`event_occurrence_id` (required)**: `integer | null`
    - **`id` (required)**: `integer`
    - **`item_name` (required)**: `string | null`
    - **`order_id` (required)**: `integer`
    - **`product_id` (required)**: `integer`
    - **`quantity` (required)**: `integer`
    - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

      *Schema `EventOccurrenceResource` is shown above.*
    - **`price`**: `number`
    - **`price_before_discount`**: `number | null`
    - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
    - **`total_before_additions`**: `number`
  - **`question_answers`**: `array | null`

    **Items:**

    *Schema `QuestionAnswerViewResource` is shown above.*
  - **`short_id`**: `string`
  - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
  - **`total_before_additions`**: `number`
  - **`total_fee`**: `number`
  - **`total_gross`**: `number`
  - **`total_refunded`**: `number`
  - **`total_tax`**: `number`

**Example:**

```json
{
  "data": {
    "id": 1,
    "short_id": "",
    "total_before_additions": 1,
    "total_gross": 1,
    "total_tax": 1,
    "total_fee": 1,
    "total_refunded": 1,
    "taxes_and_fees_rollup": [],
    "status": "RESERVED",
    "refund_status": "REFUND_PENDING",
    "payment_status": "NO_PAYMENT_REQUIRED",
    "currency": "",
    "first_name": null,
    "last_name": null,
    "email": null,
    "created_at": "",
    "public_id": "",
    "is_partially_refunded": true,
    "is_fully_refunded": true,
    "is_free_order": true,
    "is_manually_created": true,
    "box_office_id": null,
    "box_office_operator_name": null,
    "box_office_tender": "CASH",
    "box_office_amount_tendered": null,
    "box_office_change_due": null,
    "box_office_reference": null,
    "address": [],
    "notes": null,
    "payment_provider": null,
    "promo_code": null,
    "event_id": 1,
    "order_items": [],
    "attendees": [],
    "question_answers": [],
    "latest_invoice": {
      "id": 1,
      "invoice_number": "",
      "order_id": 1,
      "status": ""
    }
  }
}
```

### 401 Unauthenticated

**Content type:** `application/json`

- **`message` (required)**: `string`

  Error overview.

**Example:**

```json
{
  "message": ""
}
```

### 403 The authenticated user is not allowed to perform this action.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

### 404 The requested resource was not found.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

## Schemas

- `AttendeeCheckInResource` — shown above.
- `AttendeeResource` — shown above.

### EventLocationResource

**Type:** `object`

- **`id` (required)**: `integer`
- **`location_id` (required)**: `integer | null`
- **`type` (required)**: `string`
- **`location`**: `object | null`, schema: `LocationResource`

  *Schema `LocationResource` is shown above.*
- **`online_event_connection_details`**: `string | null`

**Example:**

```json
{
  "id": 1,
  "type": "",
  "location_id": null,
  "online_event_connection_details": null,
  "location": {
    "id": 1,
    "organizer_id": 1,
    "name": null,
    "structured_address": [],
    "latitude": null,
    "longitude": null,
    "provider": null,
    "provider_place_id": null,
    "created_at": null,
    "updated_at": null
  }
}
```

### EventOccurrenceResource

**Type:** `object`

- **`available_capacity` (required)**: `integer | null`
- **`capacity` (required)**: `integer | null`
- **`created_at` (required)**: `string | null`
- **`end_date` (required)**: `string | null`
- **`event_id` (required)**: `integer`
- **`id` (required)**: `integer`
- **`is_active` (required)**: `boolean`
- **`is_future` (required)**: `boolean`
- **`is_overridden` (required)**: `boolean`
- **`is_past` (required)**: `boolean`
- **`label` (required)**: `string | null`
- **`short_id` (required)**: `string`
- **`show_available_capacity` (required)**: `boolean | null`
- **`start_date` (required)**: `string`
- **`status` (required)**: `string`
- **`updated_at` (required)**: `string | null`
- **`used_capacity` (required)**: `integer`
- **`booking_limits`**: `object`
  - **`allocation_total` (required)**: `integer | null`
  - **`allocations` (required)**: `array of any`
  - **`capacity` (required)**: `integer | null`
  - **`sellable` (required)**: `integer | null`
- **`event_location`**: `object | null`, schema: `EventLocationResource`

  *Schema `EventLocationResource` is shown above.*
- **`statistics`**: `object`
  - **`attendees_registered` (required)**: `integer`
  - **`orders_created` (required)**: `integer`
  - **`products_sold` (required)**: `integer`
  - **`total_fee`**: `number`
  - **`total_gross_sales`**: `number`
  - **`total_refunded`**: `number`
  - **`total_tax`**: `number`

**Example:**

```json
{
  "id": 1,
  "event_id": 1,
  "short_id": "",
  "start_date": "",
  "end_date": null,
  "status": "",
  "capacity": null,
  "used_capacity": 1,
  "available_capacity": null,
  "label": null,
  "show_available_capacity": null,
  "is_overridden": true,
  "is_past": true,
  "is_future": true,
  "is_active": true,
  "event_location": {
    "id": 1,
    "type": "",
    "location_id": null,
    "online_event_connection_details": null,
    "location": null
  },
  "booking_limits": {
    "capacity": null,
    "allocation_total": null,
    "sellable": null,
    "allocations": []
  },
  "statistics": {
    "orders_created": 1,
    "attendees_registered": 1,
    "products_sold": 1,
    "total_gross_sales": 1,
    "total_tax": 1,
    "total_fee": 1,
    "total_refunded": 1
  },
  "created_at": null,
  "updated_at": null
}
```

### LocationResource

**Type:** `object`

- **`created_at` (required)**: `string | null`
- **`id` (required)**: `integer`
- **`latitude` (required)**: `number | null`
- **`longitude` (required)**: `number | null`
- **`name` (required)**: `string | null`
- **`organizer_id` (required)**: `integer`
- **`provider` (required)**: `string | null`
- **`provider_place_id` (required)**: `string | null`
- **`structured_address` (required)**: `null | (array of any) | string | object`
- **`updated_at` (required)**: `string | null`

**Example:**

```json
{
  "id": 1,
  "organizer_id": 1,
  "name": null,
  "structured_address": [],
  "latitude": null,
  "longitude": null,
  "provider": null,
  "provider_place_id": null,
  "created_at": null,
  "updated_at": null
}
```

- `OrderItemResource` — shown above.
- `OrderResource` — shown above.
- `ProductPriceResource` — shown above.
- `ProductResource` — shown above.
- `QuestionAnswerViewResource` — shown above.
- `TaxAndFeeResource` — shown above.

# Get Orders

- **Method:** `GET`
- **Path:** `/events/{eventId}/orders`
- **Operation ID:** `orders.getOrdersAction`
- **Tags:** Orders

## Effective servers

- `/`

## Authentication

- **http**: HTTP bearer

  An API key (`hie_…`) or a session token from `POST /auth/login`.

## Path parameters

- **`eventId` (required)**: `integer`

## Query parameters

- **`page`**: `integer`, default: `1`
- **`per_page`**: `integer`, default: `25`
- **`sort_by`**: `string`

  Sortable fields are listed in the response `meta.allowed_sorts`.
- **`sort_direction`**: `string`, possible values: `"asc", "desc"`
- **`query`**: `string`

  Search term used to filter the results.
- **`filter_fields`**: `object`, style: `deepObject`, explode: `true`

  Filters in the form `filter_fields[field][operator]=value`. Filterable fields are listed in the response `meta.allowed_filter_fields`.

  **Additional properties:**

  `object`

  **Additional properties:**

  `string`

## Responses

### 200 Array of \`OrderResource\`

**Content type:** `application/json`

- **`data` (required)**: `array`

  **Items:**

  schema: `OrderResource`
  - **`address` (required)**: `null | (array of any) | string | object`
  - **`box_office_id` (required)**: `integer | null`
  - **`created_at` (required)**: `string`
  - **`currency` (required)**: `string`
  - **`email` (required)**: `string | null`
  - **`event_id` (required)**: `integer`
  - **`first_name` (required)**: `string | null`
  - **`id` (required)**: `integer`
  - **`is_free_order` (required)**: `boolean`
  - **`is_fully_refunded` (required)**: `boolean`
  - **`is_manually_created` (required)**: `boolean`
  - **`is_partially_refunded` (required)**: `boolean`
  - **`last_name` (required)**: `string | null`
  - **`notes` (required)**: `string | null`
  - **`payment_provider` (required)**: `string | null`
  - **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
  - **`promo_code` (required)**: `string | null`
  - **`public_id` (required)**: `string`
  - **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
  - **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
  - **`attendees`**: `array | null`

    **Items:**

    schema: `AttendeeResource`
    - **`created_at` (required)**: `string`
    - **`email` (required)**: `string | null`
    - **`event_id` (required)**: `integer`
    - **`event_occurrence_id` (required)**: `integer | null`
    - **`first_name` (required)**: `string`
    - **`id` (required)**: `integer`
    - **`last_name` (required)**: `string`
    - **`locale` (required)**: `string`
    - **`notes` (required)**: `string | null`
    - **`order_id` (required)**: `integer`
    - **`product_id` (required)**: `integer`
    - **`product_price_id` (required)**: `integer`
    - **`public_id` (required)**: `string`
    - **`seat_label` (required)**: `string | null`
    - **`seat_uid` (required)**: `string | null`
    - **`short_id` (required)**: `string`
    - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
    - **`updated_at` (required)**: `string`
    - **`check_ins`**: `array | null`

      **Items:**

      schema: `AttendeeCheckInResource`
      - **`attendee_id` (required)**: `integer`
      - **`check_in_list_id` (required)**: `integer`
      - **`created_at` (required)**: `string | null`
      - **`event_id` (required)**: `integer`
      - **`event_occurrence_id` (required)**: `integer | null`
      - **`id` (required)**: `integer`
      - **`product_id` (required)**: `integer`
      - **`short_id` (required)**: `string`
      - **`attendee`**: `object`
        - **`created_at` (required)**: `string`
        - **`email` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`event_occurrence_id` (required)**: `integer | null`
        - **`first_name` (required)**: `string`
        - **`id` (required)**: `integer`
        - **`last_name` (required)**: `string`
        - **`locale` (required)**: `string`
        - **`notes` (required)**: `string | null`
        - **`order_id` (required)**: `integer`
        - **`product_id` (required)**: `integer`
        - **`product_price_id` (required)**: `integer`
        - **`public_id` (required)**: `string`
        - **`seat_label` (required)**: `string | null`
        - **`seat_uid` (required)**: `string | null`
        - **`short_id` (required)**: `string`
        - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
        - **`updated_at` (required)**: `string`
        - **`check_ins`**: `array | null`

          **Items:**

          *\[Circular Reference]*
        - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`
          - **`available_capacity` (required)**: `integer | null`
          - **`capacity` (required)**: `integer | null`
          - **`created_at` (required)**: `string | null`
          - **`end_date` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`id` (required)**: `integer`
          - **`is_active` (required)**: `boolean`
          - **`is_future` (required)**: `boolean`
          - **`is_overridden` (required)**: `boolean`
          - **`is_past` (required)**: `boolean`
          - **`label` (required)**: `string | null`
          - **`short_id` (required)**: `string`
          - **`show_available_capacity` (required)**: `boolean | null`
          - **`start_date` (required)**: `string`
          - **`status` (required)**: `string`
          - **`updated_at` (required)**: `string | null`
          - **`used_capacity` (required)**: `integer`
          - **`booking_limits`**: `object`
            - **`allocation_total` (required)**: `integer | null`
            - **`allocations` (required)**: `array of any`
            - **`capacity` (required)**: `integer | null`
            - **`sellable` (required)**: `integer | null`
          - **`event_location`**: `object | null`, schema: `EventLocationResource`
            - **`id` (required)**: `integer`
            - **`location_id` (required)**: `integer | null`
            - **`type` (required)**: `string`
            - **`location`**: `object | null`, schema: `LocationResource`
              - **`created_at` (required)**: `string | null`
              - **`id` (required)**: `integer`
              - **`latitude` (required)**: `number | null`
              - **`longitude` (required)**: `number | null`
              - **`name` (required)**: `string | null`
              - **`organizer_id` (required)**: `integer`
              - **`provider` (required)**: `string | null`
              - **`provider_place_id` (required)**: `string | null`
              - **`structured_address` (required)**: `null | (array of any) | string | object`
              - **`updated_at` (required)**: `string | null`
            - **`online_event_connection_details`**: `string | null`
          - **`statistics`**: `object`
            - **`attendees_registered` (required)**: `integer`
            - **`orders_created` (required)**: `integer`
            - **`products_sold` (required)**: `integer`
            - **`total_fee`**: `number`
            - **`total_gross_sales`**: `number`
            - **`total_refunded`**: `number`
            - **`total_tax`**: `number`
        - **`order`**: `object | null`, schema: `OrderResource`
          - **`address` (required)**: `null | (array of any) | string | object`
          - **`box_office_id` (required)**: `integer | null`
          - **`created_at` (required)**: `string`
          - **`currency` (required)**: `string`
          - **`email` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`first_name` (required)**: `string | null`
          - **`id` (required)**: `integer`
          - **`is_free_order` (required)**: `boolean`
          - **`is_fully_refunded` (required)**: `boolean`
          - **`is_manually_created` (required)**: `boolean`
          - **`is_partially_refunded` (required)**: `boolean`
          - **`last_name` (required)**: `string | null`
          - **`notes` (required)**: `string | null`
          - **`payment_provider` (required)**: `string | null`
          - **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
          - **`promo_code` (required)**: `string | null`
          - **`public_id` (required)**: `string`
          - **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
          - **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
          - **`attendees`**: `array | null`

            **Items:**

            schema: `AttendeeResource`
            - **`created_at` (required)**: `string`
            - **`email` (required)**: `string | null`
            - **`event_id` (required)**: `integer`
            - **`event_occurrence_id` (required)**: `integer | null`
            - **`first_name` (required)**: `string`
            - **`id` (required)**: `integer`
            - **`last_name` (required)**: `string`
            - **`locale` (required)**: `string`
            - **`notes` (required)**: `string | null`
            - **`order_id` (required)**: `integer`
            - **`product_id` (required)**: `integer`
            - **`product_price_id` (required)**: `integer`
            - **`public_id` (required)**: `string`
            - **`seat_label` (required)**: `string | null`
            - **`seat_uid` (required)**: `string | null`
            - **`short_id` (required)**: `string`
            - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
            - **`updated_at` (required)**: `string`
            - **`check_ins`**: `array | null`

              **Items:**

              schema: `AttendeeCheckInResource`
              - **`attendee_id` (required)**: `integer`
              - **`check_in_list_id` (required)**: `integer`
              - **`created_at` (required)**: `string | null`
              - **`event_id` (required)**: `integer`
              - **`event_occurrence_id` (required)**: `integer | null`
              - **`id` (required)**: `integer`
              - **`product_id` (required)**: `integer`
              - **`short_id` (required)**: `string`
              - **`attendee`**: `object`
                - **`created_at` (required)**: `string`
                - **`email` (required)**: `string | null`
                - **`event_id` (required)**: `integer`
                - **`event_occurrence_id` (required)**: `integer | null`
                - **`first_name` (required)**: `string`
                - **`id` (required)**: `integer`
                - **`last_name` (required)**: `string`
                - **`locale` (required)**: `string`
                - **`notes` (required)**: `string | null`
                - **`order_id` (required)**: `integer`
                - **`product_id` (required)**: `integer`
                - **`product_price_id` (required)**: `integer`
                - **`public_id` (required)**: `string`
                - **`seat_label` (required)**: `string | null`
                - **`seat_uid` (required)**: `string | null`
                - **`short_id` (required)**: `string`
                - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
                - **`updated_at` (required)**: `string`
                - **`check_ins`**: `array | null`

                  **Items:**

                  *\[Circular Reference]*
                - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                  *Schema `EventOccurrenceResource` is shown above.*
                - **`order`**: `object | null`, schema: `OrderResource`

                  *\[Circular Reference]*
                - **`product`**: `object | null`, schema: `ProductResource`
                  - **`description` (required)**: `string | null`
                  - **`event_id` (required)**: `integer`
                  - **`hide_after_sale_end_date` (required)**: `boolean`
                  - **`hide_before_sale_start_date` (required)**: `boolean`
                  - **`hide_when_sold_out` (required)**: `boolean`
                  - **`highlight_message` (required)**: `string | null`
                  - **`id` (required)**: `integer`
                  - **`initial_quantity_available` (required)**: `integer | null`
                  - **`is_addon_only` (required)**: `boolean`
                  - **`is_after_sale_end_date` (required)**: `boolean`
                  - **`is_available` (required)**: `boolean`
                  - **`is_before_sale_start_date` (required)**: `boolean`
                  - **`is_hidden` (required)**: `boolean | null`
                  - **`is_hidden_without_promo_code` (required)**: `boolean`
                  - **`is_highlighted` (required)**: `boolean`
                  - **`max_per_order` (required)**: `integer`
                  - **`min_per_order` (required)**: `integer`
                  - **`order` (required)**: `integer`
                  - **`product_category_id` (required)**: `integer | null`
                  - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
                  - **`quantity_sold` (required)**: `integer`
                  - **`sale_end_date` (required)**: `string | null`
                  - **`sale_start_date` (required)**: `string | null`
                  - **`sequential_tier_release_enabled` (required)**: `boolean`
                  - **`show_quantity_remaining` (required)**: `boolean`
                  - **`start_collapsed` (required)**: `boolean`
                  - **`title` (required)**: `string`
                  - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
                  - **`waitlist_enabled` (required)**: `boolean | null`
                  - **`addon_product_ids`**: `(array of any) | null`
                  - **`addons`**: `array`

                    **Items:**
                    - **`id` (required)**: `integer`
                    - **`title` (required)**: `string`
                  - **`is_sold_out`**: `boolean`
                  - **`price`**: `number | null`
                  - **`prices`**: `array | null`

                    **Items:**

                    schema: `ProductPriceResource`
                    - **`id` (required)**: `integer`
                    - **`initial_quantity_available` (required)**: `integer | null`
                    - **`is_after_sale_end_date` (required)**: `boolean`
                    - **`is_available` (required)**: `boolean | null`
                    - **`is_before_sale_start_date` (required)**: `boolean`
                    - **`is_hidden` (required)**: `boolean | null`
                    - **`is_locked_behind_earlier_tier` (required)**: `boolean`
                    - **`is_sold_out` (required)**: `boolean`
                    - **`label` (required)**: `string | null`
                    - **`off_sale_reason` (required)**: `string | null`
                    - **`price` (required)**: `number`
                    - **`price_including_taxes_and_fees` (required)**: `number`
                    - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
                    - **`quantity_sold` (required)**: `integer`
                    - **`sale_end_date` (required)**: `string | null`
                    - **`sale_start_date` (required)**: `string | null`
                  - **`taxes_and_fees`**: `array | null`

                    **Items:**

                    schema: `TaxAndFeeResource`
                    - **`account_id` (required)**: `integer`
                    - **`calculation_type` (required)**: `string`
                    - **`description` (required)**: `string | null`
                    - **`id` (required)**: `integer`
                    - **`is_active` (required)**: `boolean | null`
                    - **`is_default` (required)**: `boolean`
                    - **`name` (required)**: `string`
                    - **`rate` (required)**: `number`
                    - **`type` (required)**: `string`
                - **`question_answers`**: `array | null`

                  **Items:**

                  schema: `QuestionAnswerViewResource`
                  - **`answer` (required)**: `(array of any) | string`
                  - **`belongs_to` (required)**: `string`
                  - **`event_id` (required)**: `integer`
                  - **`order_id` (required)**: `integer | null`
                  - **`product_id` (required)**: `integer | null`
                  - **`product_title` (required)**: `string | null`
                  - **`question_answer_id` (required)**: `integer`
                  - **`question_description` (required)**: `string | null`
                  - **`question_id` (required)**: `integer`
                  - **`question_options` (required)**: `(array of any) | null`
                  - **`question_required` (required)**: `boolean`
                  - **`question_type` (required)**: `string`
                  - **`text_answer` (required)**: `string`
                  - **`title` (required)**: `string`
                  - **`attendee_id`**: `integer | null`
                  - **`attendee_public_id`**: `string | null`
                  - **`first_name`**: `string | null`
                  - **`last_name`**: `string | null`
              - **`check_in_list`**: `object`
                - **`activates_at` (required)**: `string | null`
                - **`checked_in_attendees` (required)**: `integer | null`
                - **`description` (required)**: `string | null`
                - **`event_occurrence_id` (required)**: `integer | null`
                - **`expires_at` (required)**: `string | null`
                - **`id` (required)**: `integer`
                - **`is_system_default` (required)**: `boolean`
                - **`name` (required)**: `string`
                - **`public_show_attendee_notes` (required)**: `boolean`
                - **`public_show_order_details` (required)**: `boolean`
                - **`public_show_question_answers` (required)**: `boolean`
                - **`short_id` (required)**: `string`
                - **`total_attendees` (required)**: `integer | null`
                - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                  *Schema `EventOccurrenceResource` is shown above.*
                - **`is_active`**: `boolean`
                - **`is_expired`**: `boolean`
                - **`products`**: `array | null`

                  **Items:**

                  *Schema `ProductResource` is shown above.*
            - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

              *Schema `EventOccurrenceResource` is shown above.*
            - **`order`**: `object | null`, schema: `OrderResource`

              *\[Circular Reference]*
            - **`product`**: `object | null`, schema: `ProductResource`

              *Schema `ProductResource` is shown above.*
            - **`question_answers`**: `array | null`

              **Items:**

              *Schema `QuestionAnswerViewResource` is shown above.*
          - **`box_office_amount_tendered`**: `number | null`
          - **`box_office_change_due`**: `number | null`
          - **`box_office_operator_name`**: `string | null`
          - **`box_office_reference`**: `string | null`
          - **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
          - **`latest_invoice`**: `object`
            - **`id` (required)**: `integer`
            - **`invoice_number` (required)**: `string`
            - **`order_id` (required)**: `integer`
            - **`status` (required)**: `string`
          - **`order_items`**: `array | null`

            **Items:**

            schema: `OrderItemResource`
            - **`band_key` (required)**: `string | null`
            - **`event_occurrence_id` (required)**: `integer | null`
            - **`id` (required)**: `integer`
            - **`item_name` (required)**: `string | null`
            - **`order_id` (required)**: `integer`
            - **`product_id` (required)**: `integer`
            - **`quantity` (required)**: `integer`
            - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

              *Schema `EventOccurrenceResource` is shown above.*
            - **`price`**: `number`
            - **`price_before_discount`**: `number | null`
            - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
            - **`total_before_additions`**: `number`
          - **`question_answers`**: `array | null`

            **Items:**

            *Schema `QuestionAnswerViewResource` is shown above.*
          - **`short_id`**: `string`
          - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
          - **`total_before_additions`**: `number`
          - **`total_fee`**: `number`
          - **`total_gross`**: `number`
          - **`total_refunded`**: `number`
          - **`total_tax`**: `number`
        - **`product`**: `object | null`, schema: `ProductResource`

          *Schema `ProductResource` is shown above.*
        - **`question_answers`**: `array | null`

          **Items:**

          schema: `QuestionAnswerViewResource`
          - **`answer` (required)**: `(array of any) | string`
          - **`belongs_to` (required)**: `string`
          - **`event_id` (required)**: `integer`
          - **`order_id` (required)**: `integer | null`
          - **`product_id` (required)**: `integer | null`
          - **`product_title` (required)**: `string | null`
          - **`question_answer_id` (required)**: `integer`
          - **`question_description` (required)**: `string | null`
          - **`question_id` (required)**: `integer`
          - **`question_options` (required)**: `(array of any) | null`
          - **`question_required` (required)**: `boolean`
          - **`question_type` (required)**: `string`
          - **`text_answer` (required)**: `string`
          - **`title` (required)**: `string`
          - **`attendee_id`**: `integer | null`
          - **`attendee_public_id`**: `string | null`
          - **`first_name`**: `string | null`
          - **`last_name`**: `string | null`
      - **`check_in_list`**: `object`
        - **`activates_at` (required)**: `string | null`
        - **`checked_in_attendees` (required)**: `integer | null`
        - **`description` (required)**: `string | null`
        - **`event_occurrence_id` (required)**: `integer | null`
        - **`expires_at` (required)**: `string | null`
        - **`id` (required)**: `integer`
        - **`is_system_default` (required)**: `boolean`
        - **`name` (required)**: `string`
        - **`public_show_attendee_notes` (required)**: `boolean`
        - **`public_show_order_details` (required)**: `boolean`
        - **`public_show_question_answers` (required)**: `boolean`
        - **`short_id` (required)**: `string`
        - **`total_attendees` (required)**: `integer | null`
        - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

          *Schema `EventOccurrenceResource` is shown above.*
        - **`is_active`**: `boolean`
        - **`is_expired`**: `boolean`
        - **`products`**: `array | null`

          **Items:**

          schema: `ProductResource`
          - **`description` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`hide_after_sale_end_date` (required)**: `boolean`
          - **`hide_before_sale_start_date` (required)**: `boolean`
          - **`hide_when_sold_out` (required)**: `boolean`
          - **`highlight_message` (required)**: `string | null`
          - **`id` (required)**: `integer`
          - **`initial_quantity_available` (required)**: `integer | null`
          - **`is_addon_only` (required)**: `boolean`
          - **`is_after_sale_end_date` (required)**: `boolean`
          - **`is_available` (required)**: `boolean`
          - **`is_before_sale_start_date` (required)**: `boolean`
          - **`is_hidden` (required)**: `boolean | null`
          - **`is_hidden_without_promo_code` (required)**: `boolean`
          - **`is_highlighted` (required)**: `boolean`
          - **`max_per_order` (required)**: `integer`
          - **`min_per_order` (required)**: `integer`
          - **`order` (required)**: `integer`
          - **`product_category_id` (required)**: `integer | null`
          - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
          - **`quantity_sold` (required)**: `integer`
          - **`sale_end_date` (required)**: `string | null`
          - **`sale_start_date` (required)**: `string | null`
          - **`sequential_tier_release_enabled` (required)**: `boolean`
          - **`show_quantity_remaining` (required)**: `boolean`
          - **`start_collapsed` (required)**: `boolean`
          - **`title` (required)**: `string`
          - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
          - **`waitlist_enabled` (required)**: `boolean | null`
          - **`addon_product_ids`**: `(array of any) | null`
          - **`addons`**: `array`

            **Items:**
            - **`id` (required)**: `integer`
            - **`title` (required)**: `string`
          - **`is_sold_out`**: `boolean`
          - **`price`**: `number | null`
          - **`prices`**: `array | null`

            **Items:**

            schema: `ProductPriceResource`
            - **`id` (required)**: `integer`
            - **`initial_quantity_available` (required)**: `integer | null`
            - **`is_after_sale_end_date` (required)**: `boolean`
            - **`is_available` (required)**: `boolean | null`
            - **`is_before_sale_start_date` (required)**: `boolean`
            - **`is_hidden` (required)**: `boolean | null`
            - **`is_locked_behind_earlier_tier` (required)**: `boolean`
            - **`is_sold_out` (required)**: `boolean`
            - **`label` (required)**: `string | null`
            - **`off_sale_reason` (required)**: `string | null`
            - **`price` (required)**: `number`
            - **`price_including_taxes_and_fees` (required)**: `number`
            - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
            - **`quantity_sold` (required)**: `integer`
            - **`sale_end_date` (required)**: `string | null`
            - **`sale_start_date` (required)**: `string | null`
          - **`taxes_and_fees`**: `array | null`

            **Items:**

            schema: `TaxAndFeeResource`
            - **`account_id` (required)**: `integer`
            - **`calculation_type` (required)**: `string`
            - **`description` (required)**: `string | null`
            - **`id` (required)**: `integer`
            - **`is_active` (required)**: `boolean | null`
            - **`is_default` (required)**: `boolean`
            - **`name` (required)**: `string`
            - **`rate` (required)**: `number`
            - **`type` (required)**: `string`
    - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

      *Schema `EventOccurrenceResource` is shown above.*
    - **`order`**: `object | null`, schema: `OrderResource`

      *Schema `OrderResource` is shown above.*
    - **`product`**: `object | null`, schema: `ProductResource`

      *Schema `ProductResource` is shown above.*
    - **`question_answers`**: `array | null`

      **Items:**

      *Schema `QuestionAnswerViewResource` is shown above.*
  - **`box_office_amount_tendered`**: `number | null`
  - **`box_office_change_due`**: `number | null`
  - **`box_office_operator_name`**: `string | null`
  - **`box_office_reference`**: `string | null`
  - **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
  - **`latest_invoice`**: `object`
    - **`id` (required)**: `integer`
    - **`invoice_number` (required)**: `string`
    - **`order_id` (required)**: `integer`
    - **`status` (required)**: `string`
  - **`order_items`**: `array | null`

    **Items:**

    schema: `OrderItemResource`
    - **`band_key` (required)**: `string | null`
    - **`event_occurrence_id` (required)**: `integer | null`
    - **`id` (required)**: `integer`
    - **`item_name` (required)**: `string | null`
    - **`order_id` (required)**: `integer`
    - **`product_id` (required)**: `integer`
    - **`quantity` (required)**: `integer`
    - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

      *Schema `EventOccurrenceResource` is shown above.*
    - **`price`**: `number`
    - **`price_before_discount`**: `number | null`
    - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
    - **`total_before_additions`**: `number`
  - **`question_answers`**: `array | null`

    **Items:**

    *Schema `QuestionAnswerViewResource` is shown above.*
  - **`short_id`**: `string`
  - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
  - **`total_before_additions`**: `number`
  - **`total_fee`**: `number`
  - **`total_gross`**: `number`
  - **`total_refunded`**: `number`
  - **`total_tax`**: `number`

**Example:**

```json
{
  "data": [
    {
      "id": 1,
      "short_id": "",
      "total_before_additions": 1,
      "total_gross": 1,
      "total_tax": 1,
      "total_fee": 1,
      "total_refunded": 1,
      "taxes_and_fees_rollup": [],
      "status": "RESERVED",
      "refund_status": "REFUND_PENDING",
      "payment_status": "NO_PAYMENT_REQUIRED",
      "currency": "",
      "first_name": null,
      "last_name": null,
      "email": null,
      "created_at": "",
      "public_id": "",
      "is_partially_refunded": true,
      "is_fully_refunded": true,
      "is_free_order": true,
      "is_manually_created": true,
      "box_office_id": null,
      "box_office_operator_name": null,
      "box_office_tender": "CASH",
      "box_office_amount_tendered": null,
      "box_office_change_due": null,
      "box_office_reference": null,
      "address": [],
      "notes": null,
      "payment_provider": null,
      "promo_code": null,
      "event_id": 1,
      "order_items": [],
      "attendees": [],
      "question_answers": [],
      "latest_invoice": {
        "id": 1,
        "invoice_number": "",
        "order_id": 1,
        "status": ""
      }
    }
  ]
}
```

### 401 Unauthenticated

**Content type:** `application/json`

- **`message` (required)**: `string`

  Error overview.

**Example:**

```json
{
  "message": ""
}
```

### 403 The authenticated user is not allowed to perform this action.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

### 404 The requested resource was not found.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

## Schemas

- `AttendeeCheckInResource` — shown above.
- `AttendeeResource` — shown above.

### EventLocationResource

**Type:** `object`

- **`id` (required)**: `integer`
- **`location_id` (required)**: `integer | null`
- **`type` (required)**: `string`
- **`location`**: `object | null`, schema: `LocationResource`

  *Schema `LocationResource` is shown above.*
- **`online_event_connection_details`**: `string | null`

**Example:**

```json
{
  "id": 1,
  "type": "",
  "location_id": null,
  "online_event_connection_details": null,
  "location": {
    "id": 1,
    "organizer_id": 1,
    "name": null,
    "structured_address": [],
    "latitude": null,
    "longitude": null,
    "provider": null,
    "provider_place_id": null,
    "created_at": null,
    "updated_at": null
  }
}
```

### EventOccurrenceResource

**Type:** `object`

- **`available_capacity` (required)**: `integer | null`
- **`capacity` (required)**: `integer | null`
- **`created_at` (required)**: `string | null`
- **`end_date` (required)**: `string | null`
- **`event_id` (required)**: `integer`
- **`id` (required)**: `integer`
- **`is_active` (required)**: `boolean`
- **`is_future` (required)**: `boolean`
- **`is_overridden` (required)**: `boolean`
- **`is_past` (required)**: `boolean`
- **`label` (required)**: `string | null`
- **`short_id` (required)**: `string`
- **`show_available_capacity` (required)**: `boolean | null`
- **`start_date` (required)**: `string`
- **`status` (required)**: `string`
- **`updated_at` (required)**: `string | null`
- **`used_capacity` (required)**: `integer`
- **`booking_limits`**: `object`
  - **`allocation_total` (required)**: `integer | null`
  - **`allocations` (required)**: `array of any`
  - **`capacity` (required)**: `integer | null`
  - **`sellable` (required)**: `integer | null`
- **`event_location`**: `object | null`, schema: `EventLocationResource`

  *Schema `EventLocationResource` is shown above.*
- **`statistics`**: `object`
  - **`attendees_registered` (required)**: `integer`
  - **`orders_created` (required)**: `integer`
  - **`products_sold` (required)**: `integer`
  - **`total_fee`**: `number`
  - **`total_gross_sales`**: `number`
  - **`total_refunded`**: `number`
  - **`total_tax`**: `number`

**Example:**

```json
{
  "id": 1,
  "event_id": 1,
  "short_id": "",
  "start_date": "",
  "end_date": null,
  "status": "",
  "capacity": null,
  "used_capacity": 1,
  "available_capacity": null,
  "label": null,
  "show_available_capacity": null,
  "is_overridden": true,
  "is_past": true,
  "is_future": true,
  "is_active": true,
  "event_location": {
    "id": 1,
    "type": "",
    "location_id": null,
    "online_event_connection_details": null,
    "location": null
  },
  "booking_limits": {
    "capacity": null,
    "allocation_total": null,
    "sellable": null,
    "allocations": []
  },
  "statistics": {
    "orders_created": 1,
    "attendees_registered": 1,
    "products_sold": 1,
    "total_gross_sales": 1,
    "total_tax": 1,
    "total_fee": 1,
    "total_refunded": 1
  },
  "created_at": null,
  "updated_at": null
}
```

### LocationResource

**Type:** `object`

- **`created_at` (required)**: `string | null`
- **`id` (required)**: `integer`
- **`latitude` (required)**: `number | null`
- **`longitude` (required)**: `number | null`
- **`name` (required)**: `string | null`
- **`organizer_id` (required)**: `integer`
- **`provider` (required)**: `string | null`
- **`provider_place_id` (required)**: `string | null`
- **`structured_address` (required)**: `null | (array of any) | string | object`
- **`updated_at` (required)**: `string | null`

**Example:**

```json
{
  "id": 1,
  "organizer_id": 1,
  "name": null,
  "structured_address": [],
  "latitude": null,
  "longitude": null,
  "provider": null,
  "provider_place_id": null,
  "created_at": null,
  "updated_at": null
}
```

- `OrderItemResource` — shown above.
- `OrderResource` — shown above.
- `ProductPriceResource` — shown above.
- `ProductResource` — shown above.
- `QuestionAnswerViewResource` — shown above.
- `TaxAndFeeResource` — shown above.


# Get Attendees

- **Method:** `GET`
- **Path:** `/events/{eventId}/attendees`
- **Operation ID:** `attendees.getAttendeesAction`
- **Tags:** Attendees

## Effective servers

- `/`

## Authentication

- **http**: HTTP bearer

  An API key (`hie_…`) or a session token from `POST /auth/login`.

## Path parameters

- **`eventId` (required)**: `integer`

## Responses

### 200 Paginated set of \`AttendeeResource\`

**Content type:** `application/json`

- **`data` (required)**: `array`

  **Items:**

  schema: `AttendeeResource`
  - **`created_at` (required)**: `string`
  - **`email` (required)**: `string | null`
  - **`event_id` (required)**: `integer`
  - **`event_occurrence_id` (required)**: `integer | null`
  - **`first_name` (required)**: `string`
  - **`id` (required)**: `integer`
  - **`last_name` (required)**: `string`
  - **`locale` (required)**: `string`
  - **`notes` (required)**: `string | null`
  - **`order_id` (required)**: `integer`
  - **`product_id` (required)**: `integer`
  - **`product_price_id` (required)**: `integer`
  - **`public_id` (required)**: `string`
  - **`seat_label` (required)**: `string | null`
  - **`seat_uid` (required)**: `string | null`
  - **`short_id` (required)**: `string`
  - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
  - **`updated_at` (required)**: `string`
  - **`check_ins`**: `array | null`

    **Items:**

    schema: `AttendeeCheckInResource`
    - **`attendee_id` (required)**: `integer`
    - **`check_in_list_id` (required)**: `integer`
    - **`created_at` (required)**: `string | null`
    - **`event_id` (required)**: `integer`
    - **`event_occurrence_id` (required)**: `integer | null`
    - **`id` (required)**: `integer`
    - **`product_id` (required)**: `integer`
    - **`short_id` (required)**: `string`
    - **`attendee`**: `object`
      - **`created_at` (required)**: `string`
      - **`email` (required)**: `string | null`
      - **`event_id` (required)**: `integer`
      - **`event_occurrence_id` (required)**: `integer | null`
      - **`first_name` (required)**: `string`
      - **`id` (required)**: `integer`
      - **`last_name` (required)**: `string`
      - **`locale` (required)**: `string`
      - **`notes` (required)**: `string | null`
      - **`order_id` (required)**: `integer`
      - **`product_id` (required)**: `integer`
      - **`product_price_id` (required)**: `integer`
      - **`public_id` (required)**: `string`
      - **`seat_label` (required)**: `string | null`
      - **`seat_uid` (required)**: `string | null`
      - **`short_id` (required)**: `string`
      - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
      - **`updated_at` (required)**: `string`
      - **`check_ins`**: `array | null`

        **Items:**

        *\[Circular Reference]*
      - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`
        - **`available_capacity` (required)**: `integer | null`
        - **`capacity` (required)**: `integer | null`
        - **`created_at` (required)**: `string | null`
        - **`end_date` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`id` (required)**: `integer`
        - **`is_active` (required)**: `boolean`
        - **`is_future` (required)**: `boolean`
        - **`is_overridden` (required)**: `boolean`
        - **`is_past` (required)**: `boolean`
        - **`label` (required)**: `string | null`
        - **`short_id` (required)**: `string`
        - **`show_available_capacity` (required)**: `boolean | null`
        - **`start_date` (required)**: `string`
        - **`status` (required)**: `string`
        - **`updated_at` (required)**: `string | null`
        - **`used_capacity` (required)**: `integer`
        - **`booking_limits`**: `object`
          - **`allocation_total` (required)**: `integer | null`
          - **`allocations` (required)**: `array of any`
          - **`capacity` (required)**: `integer | null`
          - **`sellable` (required)**: `integer | null`
        - **`event_location`**: `object | null`, schema: `EventLocationResource`
          - **`id` (required)**: `integer`
          - **`location_id` (required)**: `integer | null`
          - **`type` (required)**: `string`
          - **`location`**: `object | null`, schema: `LocationResource`
            - **`created_at` (required)**: `string | null`
            - **`id` (required)**: `integer`
            - **`latitude` (required)**: `number | null`
            - **`longitude` (required)**: `number | null`
            - **`name` (required)**: `string | null`
            - **`organizer_id` (required)**: `integer`
            - **`provider` (required)**: `string | null`
            - **`provider_place_id` (required)**: `string | null`
            - **`structured_address` (required)**: `null | (array of any) | string | object`
            - **`updated_at` (required)**: `string | null`
          - **`online_event_connection_details`**: `string | null`
        - **`statistics`**: `object`
          - **`attendees_registered` (required)**: `integer`
          - **`orders_created` (required)**: `integer`
          - **`products_sold` (required)**: `integer`
          - **`total_fee`**: `number`
          - **`total_gross_sales`**: `number`
          - **`total_refunded`**: `number`
          - **`total_tax`**: `number`
      - **`order`**: `object | null`, schema: `OrderResource`
        - **`address` (required)**: `null | (array of any) | string | object`
        - **`box_office_id` (required)**: `integer | null`
        - **`created_at` (required)**: `string`
        - **`currency` (required)**: `string`
        - **`email` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`first_name` (required)**: `string | null`
        - **`id` (required)**: `integer`
        - **`is_free_order` (required)**: `boolean`
        - **`is_fully_refunded` (required)**: `boolean`
        - **`is_manually_created` (required)**: `boolean`
        - **`is_partially_refunded` (required)**: `boolean`
        - **`last_name` (required)**: `string | null`
        - **`notes` (required)**: `string | null`
        - **`payment_provider` (required)**: `string | null`
        - **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
        - **`promo_code` (required)**: `string | null`
        - **`public_id` (required)**: `string`
        - **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
        - **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
        - **`attendees`**: `array | null`

          **Items:**

          schema: `AttendeeResource`
          - **`created_at` (required)**: `string`
          - **`email` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`event_occurrence_id` (required)**: `integer | null`
          - **`first_name` (required)**: `string`
          - **`id` (required)**: `integer`
          - **`last_name` (required)**: `string`
          - **`locale` (required)**: `string`
          - **`notes` (required)**: `string | null`
          - **`order_id` (required)**: `integer`
          - **`product_id` (required)**: `integer`
          - **`product_price_id` (required)**: `integer`
          - **`public_id` (required)**: `string`
          - **`seat_label` (required)**: `string | null`
          - **`seat_uid` (required)**: `string | null`
          - **`short_id` (required)**: `string`
          - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
          - **`updated_at` (required)**: `string`
          - **`check_ins`**: `array | null`

            **Items:**

            schema: `AttendeeCheckInResource`
            - **`attendee_id` (required)**: `integer`
            - **`check_in_list_id` (required)**: `integer`
            - **`created_at` (required)**: `string | null`
            - **`event_id` (required)**: `integer`
            - **`event_occurrence_id` (required)**: `integer | null`
            - **`id` (required)**: `integer`
            - **`product_id` (required)**: `integer`
            - **`short_id` (required)**: `string`
            - **`attendee`**: `object`
              - **`created_at` (required)**: `string`
              - **`email` (required)**: `string | null`
              - **`event_id` (required)**: `integer`
              - **`event_occurrence_id` (required)**: `integer | null`
              - **`first_name` (required)**: `string`
              - **`id` (required)**: `integer`
              - **`last_name` (required)**: `string`
              - **`locale` (required)**: `string`
              - **`notes` (required)**: `string | null`
              - **`order_id` (required)**: `integer`
              - **`product_id` (required)**: `integer`
              - **`product_price_id` (required)**: `integer`
              - **`public_id` (required)**: `string`
              - **`seat_label` (required)**: `string | null`
              - **`seat_uid` (required)**: `string | null`
              - **`short_id` (required)**: `string`
              - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
              - **`updated_at` (required)**: `string`
              - **`check_ins`**: `array | null`

                **Items:**

                *\[Circular Reference]*
              - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                *Schema `EventOccurrenceResource` is shown above.*
              - **`order`**: `object | null`, schema: `OrderResource`

                *\[Circular Reference]*
              - **`product`**: `object | null`, schema: `ProductResource`
                - **`description` (required)**: `string | null`
                - **`event_id` (required)**: `integer`
                - **`hide_after_sale_end_date` (required)**: `boolean`
                - **`hide_before_sale_start_date` (required)**: `boolean`
                - **`hide_when_sold_out` (required)**: `boolean`
                - **`highlight_message` (required)**: `string | null`
                - **`id` (required)**: `integer`
                - **`initial_quantity_available` (required)**: `integer | null`
                - **`is_addon_only` (required)**: `boolean`
                - **`is_after_sale_end_date` (required)**: `boolean`
                - **`is_available` (required)**: `boolean`
                - **`is_before_sale_start_date` (required)**: `boolean`
                - **`is_hidden` (required)**: `boolean | null`
                - **`is_hidden_without_promo_code` (required)**: `boolean`
                - **`is_highlighted` (required)**: `boolean`
                - **`max_per_order` (required)**: `integer`
                - **`min_per_order` (required)**: `integer`
                - **`order` (required)**: `integer`
                - **`product_category_id` (required)**: `integer | null`
                - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
                - **`quantity_sold` (required)**: `integer`
                - **`sale_end_date` (required)**: `string | null`
                - **`sale_start_date` (required)**: `string | null`
                - **`sequential_tier_release_enabled` (required)**: `boolean`
                - **`show_quantity_remaining` (required)**: `boolean`
                - **`start_collapsed` (required)**: `boolean`
                - **`title` (required)**: `string`
                - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
                - **`waitlist_enabled` (required)**: `boolean | null`
                - **`addon_product_ids`**: `(array of any) | null`
                - **`addons`**: `array`

                  **Items:**
                  - **`id` (required)**: `integer`
                  - **`title` (required)**: `string`
                - **`is_sold_out`**: `boolean`
                - **`price`**: `number | null`
                - **`prices`**: `array | null`

                  **Items:**

                  schema: `ProductPriceResource`
                  - **`id` (required)**: `integer`
                  - **`initial_quantity_available` (required)**: `integer | null`
                  - **`is_after_sale_end_date` (required)**: `boolean`
                  - **`is_available` (required)**: `boolean | null`
                  - **`is_before_sale_start_date` (required)**: `boolean`
                  - **`is_hidden` (required)**: `boolean | null`
                  - **`is_locked_behind_earlier_tier` (required)**: `boolean`
                  - **`is_sold_out` (required)**: `boolean`
                  - **`label` (required)**: `string | null`
                  - **`off_sale_reason` (required)**: `string | null`
                  - **`price` (required)**: `number`
                  - **`price_including_taxes_and_fees` (required)**: `number`
                  - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
                  - **`quantity_sold` (required)**: `integer`
                  - **`sale_end_date` (required)**: `string | null`
                  - **`sale_start_date` (required)**: `string | null`
                - **`taxes_and_fees`**: `array | null`

                  **Items:**

                  schema: `TaxAndFeeResource`
                  - **`account_id` (required)**: `integer`
                  - **`calculation_type` (required)**: `string`
                  - **`description` (required)**: `string | null`
                  - **`id` (required)**: `integer`
                  - **`is_active` (required)**: `boolean | null`
                  - **`is_default` (required)**: `boolean`
                  - **`name` (required)**: `string`
                  - **`rate` (required)**: `number`
                  - **`type` (required)**: `string`
              - **`question_answers`**: `array | null`

                **Items:**

                schema: `QuestionAnswerViewResource`
                - **`answer` (required)**: `(array of any) | string`
                - **`belongs_to` (required)**: `string`
                - **`event_id` (required)**: `integer`
                - **`order_id` (required)**: `integer | null`
                - **`product_id` (required)**: `integer | null`
                - **`product_title` (required)**: `string | null`
                - **`question_answer_id` (required)**: `integer`
                - **`question_description` (required)**: `string | null`
                - **`question_id` (required)**: `integer`
                - **`question_options` (required)**: `(array of any) | null`
                - **`question_required` (required)**: `boolean`
                - **`question_type` (required)**: `string`
                - **`text_answer` (required)**: `string`
                - **`title` (required)**: `string`
                - **`attendee_id`**: `integer | null`
                - **`attendee_public_id`**: `string | null`
                - **`first_name`**: `string | null`
                - **`last_name`**: `string | null`
            - **`check_in_list`**: `object`
              - **`activates_at` (required)**: `string | null`
              - **`checked_in_attendees` (required)**: `integer | null`
              - **`description` (required)**: `string | null`
              - **`event_occurrence_id` (required)**: `integer | null`
              - **`expires_at` (required)**: `string | null`
              - **`id` (required)**: `integer`
              - **`is_system_default` (required)**: `boolean`
              - **`name` (required)**: `string`
              - **`public_show_attendee_notes` (required)**: `boolean`
              - **`public_show_order_details` (required)**: `boolean`
              - **`public_show_question_answers` (required)**: `boolean`
              - **`short_id` (required)**: `string`
              - **`total_attendees` (required)**: `integer | null`
              - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                *Schema `EventOccurrenceResource` is shown above.*
              - **`is_active`**: `boolean`
              - **`is_expired`**: `boolean`
              - **`products`**: `array | null`

                **Items:**

                *Schema `ProductResource` is shown above.*
          - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

            *Schema `EventOccurrenceResource` is shown above.*
          - **`order`**: `object | null`, schema: `OrderResource`

            *\[Circular Reference]*
          - **`product`**: `object | null`, schema: `ProductResource`

            *Schema `ProductResource` is shown above.*
          - **`question_answers`**: `array | null`

            **Items:**

            *Schema `QuestionAnswerViewResource` is shown above.*
        - **`box_office_amount_tendered`**: `number | null`
        - **`box_office_change_due`**: `number | null`
        - **`box_office_operator_name`**: `string | null`
        - **`box_office_reference`**: `string | null`
        - **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
        - **`latest_invoice`**: `object`
          - **`id` (required)**: `integer`
          - **`invoice_number` (required)**: `string`
          - **`order_id` (required)**: `integer`
          - **`status` (required)**: `string`
        - **`order_items`**: `array | null`

          **Items:**

          schema: `OrderItemResource`
          - **`band_key` (required)**: `string | null`
          - **`event_occurrence_id` (required)**: `integer | null`
          - **`id` (required)**: `integer`
          - **`item_name` (required)**: `string | null`
          - **`order_id` (required)**: `integer`
          - **`product_id` (required)**: `integer`
          - **`quantity` (required)**: `integer`
          - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

            *Schema `EventOccurrenceResource` is shown above.*
          - **`price`**: `number`
          - **`price_before_discount`**: `number | null`
          - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
          - **`total_before_additions`**: `number`
        - **`question_answers`**: `array | null`

          **Items:**

          *Schema `QuestionAnswerViewResource` is shown above.*
        - **`short_id`**: `string`
        - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
        - **`total_before_additions`**: `number`
        - **`total_fee`**: `number`
        - **`total_gross`**: `number`
        - **`total_refunded`**: `number`
        - **`total_tax`**: `number`
      - **`product`**: `object | null`, schema: `ProductResource`

        *Schema `ProductResource` is shown above.*
      - **`question_answers`**: `array | null`

        **Items:**

        schema: `QuestionAnswerViewResource`
        - **`answer` (required)**: `(array of any) | string`
        - **`belongs_to` (required)**: `string`
        - **`event_id` (required)**: `integer`
        - **`order_id` (required)**: `integer | null`
        - **`product_id` (required)**: `integer | null`
        - **`product_title` (required)**: `string | null`
        - **`question_answer_id` (required)**: `integer`
        - **`question_description` (required)**: `string | null`
        - **`question_id` (required)**: `integer`
        - **`question_options` (required)**: `(array of any) | null`
        - **`question_required` (required)**: `boolean`
        - **`question_type` (required)**: `string`
        - **`text_answer` (required)**: `string`
        - **`title` (required)**: `string`
        - **`attendee_id`**: `integer | null`
        - **`attendee_public_id`**: `string | null`
        - **`first_name`**: `string | null`
        - **`last_name`**: `string | null`
    - **`check_in_list`**: `object`
      - **`activates_at` (required)**: `string | null`
      - **`checked_in_attendees` (required)**: `integer | null`
      - **`description` (required)**: `string | null`
      - **`event_occurrence_id` (required)**: `integer | null`
      - **`expires_at` (required)**: `string | null`
      - **`id` (required)**: `integer`
      - **`is_system_default` (required)**: `boolean`
      - **`name` (required)**: `string`
      - **`public_show_attendee_notes` (required)**: `boolean`
      - **`public_show_order_details` (required)**: `boolean`
      - **`public_show_question_answers` (required)**: `boolean`
      - **`short_id` (required)**: `string`
      - **`total_attendees` (required)**: `integer | null`
      - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

        *Schema `EventOccurrenceResource` is shown above.*
      - **`is_active`**: `boolean`
      - **`is_expired`**: `boolean`
      - **`products`**: `array | null`

        **Items:**

        schema: `ProductResource`
        - **`description` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`hide_after_sale_end_date` (required)**: `boolean`
        - **`hide_before_sale_start_date` (required)**: `boolean`
        - **`hide_when_sold_out` (required)**: `boolean`
        - **`highlight_message` (required)**: `string | null`
        - **`id` (required)**: `integer`
        - **`initial_quantity_available` (required)**: `integer | null`
        - **`is_addon_only` (required)**: `boolean`
        - **`is_after_sale_end_date` (required)**: `boolean`
        - **`is_available` (required)**: `boolean`
        - **`is_before_sale_start_date` (required)**: `boolean`
        - **`is_hidden` (required)**: `boolean | null`
        - **`is_hidden_without_promo_code` (required)**: `boolean`
        - **`is_highlighted` (required)**: `boolean`
        - **`max_per_order` (required)**: `integer`
        - **`min_per_order` (required)**: `integer`
        - **`order` (required)**: `integer`
        - **`product_category_id` (required)**: `integer | null`
        - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
        - **`quantity_sold` (required)**: `integer`
        - **`sale_end_date` (required)**: `string | null`
        - **`sale_start_date` (required)**: `string | null`
        - **`sequential_tier_release_enabled` (required)**: `boolean`
        - **`show_quantity_remaining` (required)**: `boolean`
        - **`start_collapsed` (required)**: `boolean`
        - **`title` (required)**: `string`
        - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
        - **`waitlist_enabled` (required)**: `boolean | null`
        - **`addon_product_ids`**: `(array of any) | null`
        - **`addons`**: `array`

          **Items:**
          - **`id` (required)**: `integer`
          - **`title` (required)**: `string`
        - **`is_sold_out`**: `boolean`
        - **`price`**: `number | null`
        - **`prices`**: `array | null`

          **Items:**

          schema: `ProductPriceResource`
          - **`id` (required)**: `integer`
          - **`initial_quantity_available` (required)**: `integer | null`
          - **`is_after_sale_end_date` (required)**: `boolean`
          - **`is_available` (required)**: `boolean | null`
          - **`is_before_sale_start_date` (required)**: `boolean`
          - **`is_hidden` (required)**: `boolean | null`
          - **`is_locked_behind_earlier_tier` (required)**: `boolean`
          - **`is_sold_out` (required)**: `boolean`
          - **`label` (required)**: `string | null`
          - **`off_sale_reason` (required)**: `string | null`
          - **`price` (required)**: `number`
          - **`price_including_taxes_and_fees` (required)**: `number`
          - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
          - **`quantity_sold` (required)**: `integer`
          - **`sale_end_date` (required)**: `string | null`
          - **`sale_start_date` (required)**: `string | null`
        - **`taxes_and_fees`**: `array | null`

          **Items:**

          schema: `TaxAndFeeResource`
          - **`account_id` (required)**: `integer`
          - **`calculation_type` (required)**: `string`
          - **`description` (required)**: `string | null`
          - **`id` (required)**: `integer`
          - **`is_active` (required)**: `boolean | null`
          - **`is_default` (required)**: `boolean`
          - **`name` (required)**: `string`
          - **`rate` (required)**: `number`
          - **`type` (required)**: `string`
  - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

    *Schema `EventOccurrenceResource` is shown above.*
  - **`order`**: `object | null`, schema: `OrderResource`

    *Schema `OrderResource` is shown above.*
  - **`product`**: `object | null`, schema: `ProductResource`

    *Schema `ProductResource` is shown above.*
  - **`question_answers`**: `array | null`

    **Items:**

    *Schema `QuestionAnswerViewResource` is shown above.*
- **`links` (required)**: `object`
  - **`first` (required)**: `string | null`
  - **`last` (required)**: `string | null`
  - **`next` (required)**: `string | null`
  - **`prev` (required)**: `string | null`
- **`meta` (required)**: `object`
  - **`current_page` (required)**: `integer`, minimum: `1`
  - **`from` (required)**: `integer | null`, minimum: `1`
  - **`last_page` (required)**: `integer`, minimum: `1`
  - **`links` (required)**: `array`

    Generated paginator links.

    **Items:**
    - **`active` (required)**: `boolean`
    - **`label` (required)**: `string`
    - **`url` (required)**: `string | null`
  - **`path` (required)**: `string | null`

    Base path for paginator generated URLs.
  - **`per_page` (required)**: `integer`, minimum: `0`

    Number of items shown per page.
  - **`to` (required)**: `integer | null`, minimum: `1`

    Number of the last item in the slice.
  - **`total` (required)**: `integer`, minimum: `0`

    Total number of items being paginated.

**Example:**

```json
{
  "data": [
    {
      "id": 1,
      "order_id": 1,
      "product_id": 1,
      "product_price_id": 1,
      "event_id": 1,
      "email": null,
      "status": "ACTIVE",
      "first_name": "",
      "last_name": "",
      "public_id": "",
      "short_id": "",
      "event_occurrence_id": null,
      "seat_uid": null,
      "seat_label": null,
      "locale": "",
      "notes": null,
      "event_occurrence": null,
      "product": null,
      "check_ins": [],
      "order": null,
      "question_answers": [],
      "created_at": "",
      "updated_at": ""
    }
  ],
  "links": {
    "first": null,
    "last": null,
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": null,
    "last_page": 1,
    "links": [
      {
        "url": null,
        "label": "",
        "active": true
      }
    ],
    "path": null,
    "per_page": 0,
    "to": null,
    "total": 0
  }
}
```

### 401 Unauthenticated

**Content type:** `application/json`

- **`message` (required)**: `string`

  Error overview.

**Example:**

```json
{
  "message": ""
}
```

### 403 The authenticated user is not allowed to perform this action.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

### 404 The requested resource was not found.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

## Schemas

- `AttendeeCheckInResource` — shown above.
- `AttendeeResource` — shown above.

### EventLocationResource

**Type:** `object`

- **`id` (required)**: `integer`
- **`location_id` (required)**: `integer | null`
- **`type` (required)**: `string`
- **`location`**: `object | null`, schema: `LocationResource`

  *Schema `LocationResource` is shown above.*
- **`online_event_connection_details`**: `string | null`

**Example:**

```json
{
  "id": 1,
  "type": "",
  "location_id": null,
  "online_event_connection_details": null,
  "location": {
    "id": 1,
    "organizer_id": 1,
    "name": null,
    "structured_address": [],
    "latitude": null,
    "longitude": null,
    "provider": null,
    "provider_place_id": null,
    "created_at": null,
    "updated_at": null
  }
}
```

### EventOccurrenceResource

**Type:** `object`

- **`available_capacity` (required)**: `integer | null`
- **`capacity` (required)**: `integer | null`
- **`created_at` (required)**: `string | null`
- **`end_date` (required)**: `string | null`
- **`event_id` (required)**: `integer`
- **`id` (required)**: `integer`
- **`is_active` (required)**: `boolean`
- **`is_future` (required)**: `boolean`
- **`is_overridden` (required)**: `boolean`
- **`is_past` (required)**: `boolean`
- **`label` (required)**: `string | null`
- **`short_id` (required)**: `string`
- **`show_available_capacity` (required)**: `boolean | null`
- **`start_date` (required)**: `string`
- **`status` (required)**: `string`
- **`updated_at` (required)**: `string | null`
- **`used_capacity` (required)**: `integer`
- **`booking_limits`**: `object`
  - **`allocation_total` (required)**: `integer | null`
  - **`allocations` (required)**: `array of any`
  - **`capacity` (required)**: `integer | null`
  - **`sellable` (required)**: `integer | null`
- **`event_location`**: `object | null`, schema: `EventLocationResource`

  *Schema `EventLocationResource` is shown above.*
- **`statistics`**: `object`
  - **`attendees_registered` (required)**: `integer`
  - **`orders_created` (required)**: `integer`
  - **`products_sold` (required)**: `integer`
  - **`total_fee`**: `number`
  - **`total_gross_sales`**: `number`
  - **`total_refunded`**: `number`
  - **`total_tax`**: `number`

**Example:**

```json
{
  "id": 1,
  "event_id": 1,
  "short_id": "",
  "start_date": "",
  "end_date": null,
  "status": "",
  "capacity": null,
  "used_capacity": 1,
  "available_capacity": null,
  "label": null,
  "show_available_capacity": null,
  "is_overridden": true,
  "is_past": true,
  "is_future": true,
  "is_active": true,
  "event_location": {
    "id": 1,
    "type": "",
    "location_id": null,
    "online_event_connection_details": null,
    "location": null
  },
  "booking_limits": {
    "capacity": null,
    "allocation_total": null,
    "sellable": null,
    "allocations": []
  },
  "statistics": {
    "orders_created": 1,
    "attendees_registered": 1,
    "products_sold": 1,
    "total_gross_sales": 1,
    "total_tax": 1,
    "total_fee": 1,
    "total_refunded": 1
  },
  "created_at": null,
  "updated_at": null
}
```

### LocationResource

**Type:** `object`

- **`created_at` (required)**: `string | null`
- **`id` (required)**: `integer`
- **`latitude` (required)**: `number | null`
- **`longitude` (required)**: `number | null`
- **`name` (required)**: `string | null`
- **`organizer_id` (required)**: `integer`
- **`provider` (required)**: `string | null`
- **`provider_place_id` (required)**: `string | null`
- **`structured_address` (required)**: `null | (array of any) | string | object`
- **`updated_at` (required)**: `string | null`

**Example:**

```json
{
  "id": 1,
  "organizer_id": 1,
  "name": null,
  "structured_address": [],
  "latitude": null,
  "longitude": null,
  "provider": null,
  "provider_place_id": null,
  "created_at": null,
  "updated_at": null
}
```

### OrderItemResource

**Type:** `object`

- **`band_key` (required)**: `string | null`
- **`event_occurrence_id` (required)**: `integer | null`
- **`id` (required)**: `integer`
- **`item_name` (required)**: `string | null`
- **`order_id` (required)**: `integer`
- **`product_id` (required)**: `integer`
- **`quantity` (required)**: `integer`
- **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

  *Schema `EventOccurrenceResource` is shown above.*
- **`price`**: `number`
- **`price_before_discount`**: `number | null`
- **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
- **`total_before_additions`**: `number`

**Example:**

```json
{
  "id": 1,
  "order_id": 1,
  "total_before_additions": 1,
  "price": 1,
  "price_before_discount": null,
  "taxes_and_fees_rollup": [],
  "quantity": 1,
  "product_id": 1,
  "event_occurrence_id": null,
  "band_key": null,
  "item_name": null,
  "event_occurrence": {
    "id": 1,
    "event_id": 1,
    "short_id": "",
    "start_date": "",
    "end_date": null,
    "status": "",
    "capacity": null,
    "used_capacity": 1,
    "available_capacity": null,
    "label": null,
    "show_available_capacity": null,
    "is_overridden": true,
    "is_past": true,
    "is_future": true,
    "is_active": true,
    "event_location": null,
    "booking_limits": {
      "capacity": null,
      "allocation_total": null,
      "sellable": null,
      "allocations": []
    },
    "statistics": {
      "orders_created": 1,
      "attendees_registered": 1,
      "products_sold": 1,
      "total_gross_sales": 1,
      "total_tax": 1,
      "total_fee": 1,
      "total_refunded": 1
    },
    "created_at": null,
    "updated_at": null
  }
}
```

### OrderResource

**Type:** `object`

- **`address` (required)**: `null | (array of any) | string | object`
- **`box_office_id` (required)**: `integer | null`
- **`created_at` (required)**: `string`
- **`currency` (required)**: `string`
- **`email` (required)**: `string | null`
- **`event_id` (required)**: `integer`
- **`first_name` (required)**: `string | null`
- **`id` (required)**: `integer`
- **`is_free_order` (required)**: `boolean`
- **`is_fully_refunded` (required)**: `boolean`
- **`is_manually_created` (required)**: `boolean`
- **`is_partially_refunded` (required)**: `boolean`
- **`last_name` (required)**: `string | null`
- **`notes` (required)**: `string | null`
- **`payment_provider` (required)**: `string | null`
- **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
- **`promo_code` (required)**: `string | null`
- **`public_id` (required)**: `string`
- **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
- **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
- **`attendees`**: `array | null`

  **Items:**

  *Schema `AttendeeResource` is shown above.*
- **`box_office_amount_tendered`**: `number | null`
- **`box_office_change_due`**: `number | null`
- **`box_office_operator_name`**: `string | null`
- **`box_office_reference`**: `string | null`
- **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
- **`latest_invoice`**: `object`
  - **`id` (required)**: `integer`
  - **`invoice_number` (required)**: `string`
  - **`order_id` (required)**: `integer`
  - **`status` (required)**: `string`
- **`order_items`**: `array | null`

  **Items:**

  *Schema `OrderItemResource` is shown above.*
- **`question_answers`**: `array | null`

  **Items:**

  *Schema `QuestionAnswerViewResource` is shown above.*
- **`short_id`**: `string`
- **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
- **`total_before_additions`**: `number`
- **`total_fee`**: `number`
- **`total_gross`**: `number`
- **`total_refunded`**: `number`
- **`total_tax`**: `number`

**Example:**

```json
{
  "id": 1,
  "short_id": "",
  "total_before_additions": 1,
  "total_gross": 1,
  "total_tax": 1,
  "total_fee": 1,
  "total_refunded": 1,
  "taxes_and_fees_rollup": [],
  "status": "RESERVED",
  "refund_status": "REFUND_PENDING",
  "payment_status": "NO_PAYMENT_REQUIRED",
  "currency": "",
  "first_name": null,
  "last_name": null,
  "email": null,
  "created_at": "",
  "public_id": "",
  "is_partially_refunded": true,
  "is_fully_refunded": true,
  "is_free_order": true,
  "is_manually_created": true,
  "box_office_id": null,
  "box_office_operator_name": null,
  "box_office_tender": "CASH",
  "box_office_amount_tendered": null,
  "box_office_change_due": null,
  "box_office_reference": null,
  "address": [],
  "notes": null,
  "payment_provider": null,
  "promo_code": null,
  "event_id": 1,
  "order_items": [
    {
      "id": 1,
      "order_id": 1,
      "total_before_additions": 1,
      "price": 1,
      "price_before_discount": null,
      "taxes_and_fees_rollup": [],
      "quantity": 1,
      "product_id": 1,
      "event_occurrence_id": null,
      "band_key": null,
      "item_name": null,
      "event_occurrence": null
    }
  ],
  "attendees": [
    {
      "id": 1,
      "order_id": 1,
      "product_id": 1,
      "product_price_id": 1,
      "event_id": 1,
      "email": null,
      "status": "ACTIVE",
      "first_name": "",
      "last_name": "",
      "public_id": "",
      "short_id": "",
      "event_occurrence_id": null,
      "seat_uid": null,
      "seat_label": null,
      "locale": "",
      "notes": null,
      "event_occurrence": null,
      "product": null,
      "check_ins": [],
      "order": null,
      "question_answers": [],
      "created_at": "",
      "updated_at": ""
    }
  ],
  "question_answers": [
    {
      "product_id": null,
      "product_title": null,
      "question_id": 1,
      "title": "",
      "question_required": true,
      "question_description": null,
      "answer": [],
      "text_answer": "",
      "order_id": null,
      "belongs_to": "",
      "question_type": "",
      "event_id": 1,
      "question_answer_id": 1,
      "question_options": [],
      "attendee_id": null,
      "first_name": null,
      "last_name": null,
      "attendee_public_id": null
    }
  ],
  "latest_invoice": {
    "id": 1,
    "invoice_number": "",
    "order_id": 1,
    "status": ""
  }
}
```

- `ProductPriceResource` — shown above.
- `ProductResource` — shown above.
- `QuestionAnswerViewResource` — shown above.
- `TaxAndFeeResource` — shown above.


# Get Attendee

- **Method:** `GET`
- **Path:** `/events/{eventId}/attendees/{attendeeId}`
- **Operation ID:** `attendees.getAttendeeAction`
- **Tags:** Attendees

## Effective servers

- `/`

## Authentication

- **http**: HTTP bearer

  An API key (`hie_…`) or a session token from `POST /auth/login`.

## Path parameters

- **`eventId` (required)**: `integer`
- **`attendeeId` (required)**: `integer`

## Responses

### 200 \`AttendeeResource\`

**Content type:** `application/json`

- **`data` (required)**: `object`, schema: `AttendeeResource`
  - **`created_at` (required)**: `string`
  - **`email` (required)**: `string | null`
  - **`event_id` (required)**: `integer`
  - **`event_occurrence_id` (required)**: `integer | null`
  - **`first_name` (required)**: `string`
  - **`id` (required)**: `integer`
  - **`last_name` (required)**: `string`
  - **`locale` (required)**: `string`
  - **`notes` (required)**: `string | null`
  - **`order_id` (required)**: `integer`
  - **`product_id` (required)**: `integer`
  - **`product_price_id` (required)**: `integer`
  - **`public_id` (required)**: `string`
  - **`seat_label` (required)**: `string | null`
  - **`seat_uid` (required)**: `string | null`
  - **`short_id` (required)**: `string`
  - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
  - **`updated_at` (required)**: `string`
  - **`check_ins`**: `array | null`

    **Items:**

    schema: `AttendeeCheckInResource`
    - **`attendee_id` (required)**: `integer`
    - **`check_in_list_id` (required)**: `integer`
    - **`created_at` (required)**: `string | null`
    - **`event_id` (required)**: `integer`
    - **`event_occurrence_id` (required)**: `integer | null`
    - **`id` (required)**: `integer`
    - **`product_id` (required)**: `integer`
    - **`short_id` (required)**: `string`
    - **`attendee`**: `object`
      - **`created_at` (required)**: `string`
      - **`email` (required)**: `string | null`
      - **`event_id` (required)**: `integer`
      - **`event_occurrence_id` (required)**: `integer | null`
      - **`first_name` (required)**: `string`
      - **`id` (required)**: `integer`
      - **`last_name` (required)**: `string`
      - **`locale` (required)**: `string`
      - **`notes` (required)**: `string | null`
      - **`order_id` (required)**: `integer`
      - **`product_id` (required)**: `integer`
      - **`product_price_id` (required)**: `integer`
      - **`public_id` (required)**: `string`
      - **`seat_label` (required)**: `string | null`
      - **`seat_uid` (required)**: `string | null`
      - **`short_id` (required)**: `string`
      - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
      - **`updated_at` (required)**: `string`
      - **`check_ins`**: `array | null`

        **Items:**

        *\[Circular Reference]*
      - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`
        - **`available_capacity` (required)**: `integer | null`
        - **`capacity` (required)**: `integer | null`
        - **`created_at` (required)**: `string | null`
        - **`end_date` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`id` (required)**: `integer`
        - **`is_active` (required)**: `boolean`
        - **`is_future` (required)**: `boolean`
        - **`is_overridden` (required)**: `boolean`
        - **`is_past` (required)**: `boolean`
        - **`label` (required)**: `string | null`
        - **`short_id` (required)**: `string`
        - **`show_available_capacity` (required)**: `boolean | null`
        - **`start_date` (required)**: `string`
        - **`status` (required)**: `string`
        - **`updated_at` (required)**: `string | null`
        - **`used_capacity` (required)**: `integer`
        - **`booking_limits`**: `object`
          - **`allocation_total` (required)**: `integer | null`
          - **`allocations` (required)**: `array of any`
          - **`capacity` (required)**: `integer | null`
          - **`sellable` (required)**: `integer | null`
        - **`event_location`**: `object | null`, schema: `EventLocationResource`
          - **`id` (required)**: `integer`
          - **`location_id` (required)**: `integer | null`
          - **`type` (required)**: `string`
          - **`location`**: `object | null`, schema: `LocationResource`
            - **`created_at` (required)**: `string | null`
            - **`id` (required)**: `integer`
            - **`latitude` (required)**: `number | null`
            - **`longitude` (required)**: `number | null`
            - **`name` (required)**: `string | null`
            - **`organizer_id` (required)**: `integer`
            - **`provider` (required)**: `string | null`
            - **`provider_place_id` (required)**: `string | null`
            - **`structured_address` (required)**: `null | (array of any) | string | object`
            - **`updated_at` (required)**: `string | null`
          - **`online_event_connection_details`**: `string | null`
        - **`statistics`**: `object`
          - **`attendees_registered` (required)**: `integer`
          - **`orders_created` (required)**: `integer`
          - **`products_sold` (required)**: `integer`
          - **`total_fee`**: `number`
          - **`total_gross_sales`**: `number`
          - **`total_refunded`**: `number`
          - **`total_tax`**: `number`
      - **`order`**: `object | null`, schema: `OrderResource`
        - **`address` (required)**: `null | (array of any) | string | object`
        - **`box_office_id` (required)**: `integer | null`
        - **`created_at` (required)**: `string`
        - **`currency` (required)**: `string`
        - **`email` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`first_name` (required)**: `string | null`
        - **`id` (required)**: `integer`
        - **`is_free_order` (required)**: `boolean`
        - **`is_fully_refunded` (required)**: `boolean`
        - **`is_manually_created` (required)**: `boolean`
        - **`is_partially_refunded` (required)**: `boolean`
        - **`last_name` (required)**: `string | null`
        - **`notes` (required)**: `string | null`
        - **`payment_provider` (required)**: `string | null`
        - **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
        - **`promo_code` (required)**: `string | null`
        - **`public_id` (required)**: `string`
        - **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
        - **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
        - **`attendees`**: `array | null`

          **Items:**

          schema: `AttendeeResource`
          - **`created_at` (required)**: `string`
          - **`email` (required)**: `string | null`
          - **`event_id` (required)**: `integer`
          - **`event_occurrence_id` (required)**: `integer | null`
          - **`first_name` (required)**: `string`
          - **`id` (required)**: `integer`
          - **`last_name` (required)**: `string`
          - **`locale` (required)**: `string`
          - **`notes` (required)**: `string | null`
          - **`order_id` (required)**: `integer`
          - **`product_id` (required)**: `integer`
          - **`product_price_id` (required)**: `integer`
          - **`public_id` (required)**: `string`
          - **`seat_label` (required)**: `string | null`
          - **`seat_uid` (required)**: `string | null`
          - **`short_id` (required)**: `string`
          - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
          - **`updated_at` (required)**: `string`
          - **`check_ins`**: `array | null`

            **Items:**

            schema: `AttendeeCheckInResource`
            - **`attendee_id` (required)**: `integer`
            - **`check_in_list_id` (required)**: `integer`
            - **`created_at` (required)**: `string | null`
            - **`event_id` (required)**: `integer`
            - **`event_occurrence_id` (required)**: `integer | null`
            - **`id` (required)**: `integer`
            - **`product_id` (required)**: `integer`
            - **`short_id` (required)**: `string`
            - **`attendee`**: `object`
              - **`created_at` (required)**: `string`
              - **`email` (required)**: `string | null`
              - **`event_id` (required)**: `integer`
              - **`event_occurrence_id` (required)**: `integer | null`
              - **`first_name` (required)**: `string`
              - **`id` (required)**: `integer`
              - **`last_name` (required)**: `string`
              - **`locale` (required)**: `string`
              - **`notes` (required)**: `string | null`
              - **`order_id` (required)**: `integer`
              - **`product_id` (required)**: `integer`
              - **`product_price_id` (required)**: `integer`
              - **`public_id` (required)**: `string`
              - **`seat_label` (required)**: `string | null`
              - **`seat_uid` (required)**: `string | null`
              - **`short_id` (required)**: `string`
              - **`status` (required)**: `string`, possible values: `"ACTIVE", "AWAITING_PAYMENT", "CANCELLED"`
              - **`updated_at` (required)**: `string`
              - **`check_ins`**: `array | null`

                **Items:**

                *\[Circular Reference]*
              - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                *Schema `EventOccurrenceResource` is shown above.*
              - **`order`**: `object | null`, schema: `OrderResource`

                *\[Circular Reference]*
              - **`product`**: `object | null`, schema: `ProductResource`
                - **`description` (required)**: `string | null`
                - **`event_id` (required)**: `integer`
                - **`hide_after_sale_end_date` (required)**: `boolean`
                - **`hide_before_sale_start_date` (required)**: `boolean`
                - **`hide_when_sold_out` (required)**: `boolean`
                - **`highlight_message` (required)**: `string | null`
                - **`id` (required)**: `integer`
                - **`initial_quantity_available` (required)**: `integer | null`
                - **`is_addon_only` (required)**: `boolean`
                - **`is_after_sale_end_date` (required)**: `boolean`
                - **`is_available` (required)**: `boolean`
                - **`is_before_sale_start_date` (required)**: `boolean`
                - **`is_hidden` (required)**: `boolean | null`
                - **`is_hidden_without_promo_code` (required)**: `boolean`
                - **`is_highlighted` (required)**: `boolean`
                - **`max_per_order` (required)**: `integer`
                - **`min_per_order` (required)**: `integer`
                - **`order` (required)**: `integer`
                - **`product_category_id` (required)**: `integer | null`
                - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
                - **`quantity_sold` (required)**: `integer`
                - **`sale_end_date` (required)**: `string | null`
                - **`sale_start_date` (required)**: `string | null`
                - **`sequential_tier_release_enabled` (required)**: `boolean`
                - **`show_quantity_remaining` (required)**: `boolean`
                - **`start_collapsed` (required)**: `boolean`
                - **`title` (required)**: `string`
                - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
                - **`waitlist_enabled` (required)**: `boolean | null`
                - **`addon_product_ids`**: `(array of any) | null`
                - **`addons`**: `array`

                  **Items:**
                  - **`id` (required)**: `integer`
                  - **`title` (required)**: `string`
                - **`is_sold_out`**: `boolean`
                - **`price`**: `number | null`
                - **`prices`**: `array | null`

                  **Items:**

                  schema: `ProductPriceResource`
                  - **`id` (required)**: `integer`
                  - **`initial_quantity_available` (required)**: `integer | null`
                  - **`is_after_sale_end_date` (required)**: `boolean`
                  - **`is_available` (required)**: `boolean | null`
                  - **`is_before_sale_start_date` (required)**: `boolean`
                  - **`is_hidden` (required)**: `boolean | null`
                  - **`is_locked_behind_earlier_tier` (required)**: `boolean`
                  - **`is_sold_out` (required)**: `boolean`
                  - **`label` (required)**: `string | null`
                  - **`off_sale_reason` (required)**: `string | null`
                  - **`price` (required)**: `number`
                  - **`price_including_taxes_and_fees` (required)**: `number`
                  - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
                  - **`quantity_sold` (required)**: `integer`
                  - **`sale_end_date` (required)**: `string | null`
                  - **`sale_start_date` (required)**: `string | null`
                - **`taxes_and_fees`**: `array | null`

                  **Items:**

                  schema: `TaxAndFeeResource`
                  - **`account_id` (required)**: `integer`
                  - **`calculation_type` (required)**: `string`
                  - **`description` (required)**: `string | null`
                  - **`id` (required)**: `integer`
                  - **`is_active` (required)**: `boolean | null`
                  - **`is_default` (required)**: `boolean`
                  - **`name` (required)**: `string`
                  - **`rate` (required)**: `number`
                  - **`type` (required)**: `string`
              - **`question_answers`**: `array | null`

                **Items:**

                schema: `QuestionAnswerViewResource`
                - **`answer` (required)**: `(array of any) | string`
                - **`belongs_to` (required)**: `string`
                - **`event_id` (required)**: `integer`
                - **`order_id` (required)**: `integer | null`
                - **`product_id` (required)**: `integer | null`
                - **`product_title` (required)**: `string | null`
                - **`question_answer_id` (required)**: `integer`
                - **`question_description` (required)**: `string | null`
                - **`question_id` (required)**: `integer`
                - **`question_options` (required)**: `(array of any) | null`
                - **`question_required` (required)**: `boolean`
                - **`question_type` (required)**: `string`
                - **`text_answer` (required)**: `string`
                - **`title` (required)**: `string`
                - **`attendee_id`**: `integer | null`
                - **`attendee_public_id`**: `string | null`
                - **`first_name`**: `string | null`
                - **`last_name`**: `string | null`
            - **`check_in_list`**: `object`
              - **`activates_at` (required)**: `string | null`
              - **`checked_in_attendees` (required)**: `integer | null`
              - **`description` (required)**: `string | null`
              - **`event_occurrence_id` (required)**: `integer | null`
              - **`expires_at` (required)**: `string | null`
              - **`id` (required)**: `integer`
              - **`is_system_default` (required)**: `boolean`
              - **`name` (required)**: `string`
              - **`public_show_attendee_notes` (required)**: `boolean`
              - **`public_show_order_details` (required)**: `boolean`
              - **`public_show_question_answers` (required)**: `boolean`
              - **`short_id` (required)**: `string`
              - **`total_attendees` (required)**: `integer | null`
              - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

                *Schema `EventOccurrenceResource` is shown above.*
              - **`is_active`**: `boolean`
              - **`is_expired`**: `boolean`
              - **`products`**: `array | null`

                **Items:**

                *Schema `ProductResource` is shown above.*
          - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

            *Schema `EventOccurrenceResource` is shown above.*
          - **`order`**: `object | null`, schema: `OrderResource`

            *\[Circular Reference]*
          - **`product`**: `object | null`, schema: `ProductResource`

            *Schema `ProductResource` is shown above.*
          - **`question_answers`**: `array | null`

            **Items:**

            *Schema `QuestionAnswerViewResource` is shown above.*
        - **`box_office_amount_tendered`**: `number | null`
        - **`box_office_change_due`**: `number | null`
        - **`box_office_operator_name`**: `string | null`
        - **`box_office_reference`**: `string | null`
        - **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
        - **`latest_invoice`**: `object`
          - **`id` (required)**: `integer`
          - **`invoice_number` (required)**: `string`
          - **`order_id` (required)**: `integer`
          - **`status` (required)**: `string`
        - **`order_items`**: `array | null`

          **Items:**

          schema: `OrderItemResource`
          - **`band_key` (required)**: `string | null`
          - **`event_occurrence_id` (required)**: `integer | null`
          - **`id` (required)**: `integer`
          - **`item_name` (required)**: `string | null`
          - **`order_id` (required)**: `integer`
          - **`product_id` (required)**: `integer`
          - **`quantity` (required)**: `integer`
          - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

            *Schema `EventOccurrenceResource` is shown above.*
          - **`price`**: `number`
          - **`price_before_discount`**: `number | null`
          - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
          - **`total_before_additions`**: `number`
        - **`question_answers`**: `array | null`

          **Items:**

          *Schema `QuestionAnswerViewResource` is shown above.*
        - **`short_id`**: `string`
        - **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
        - **`total_before_additions`**: `number`
        - **`total_fee`**: `number`
        - **`total_gross`**: `number`
        - **`total_refunded`**: `number`
        - **`total_tax`**: `number`
      - **`product`**: `object | null`, schema: `ProductResource`

        *Schema `ProductResource` is shown above.*
      - **`question_answers`**: `array | null`

        **Items:**

        schema: `QuestionAnswerViewResource`
        - **`answer` (required)**: `(array of any) | string`
        - **`belongs_to` (required)**: `string`
        - **`event_id` (required)**: `integer`
        - **`order_id` (required)**: `integer | null`
        - **`product_id` (required)**: `integer | null`
        - **`product_title` (required)**: `string | null`
        - **`question_answer_id` (required)**: `integer`
        - **`question_description` (required)**: `string | null`
        - **`question_id` (required)**: `integer`
        - **`question_options` (required)**: `(array of any) | null`
        - **`question_required` (required)**: `boolean`
        - **`question_type` (required)**: `string`
        - **`text_answer` (required)**: `string`
        - **`title` (required)**: `string`
        - **`attendee_id`**: `integer | null`
        - **`attendee_public_id`**: `string | null`
        - **`first_name`**: `string | null`
        - **`last_name`**: `string | null`
    - **`check_in_list`**: `object`
      - **`activates_at` (required)**: `string | null`
      - **`checked_in_attendees` (required)**: `integer | null`
      - **`description` (required)**: `string | null`
      - **`event_occurrence_id` (required)**: `integer | null`
      - **`expires_at` (required)**: `string | null`
      - **`id` (required)**: `integer`
      - **`is_system_default` (required)**: `boolean`
      - **`name` (required)**: `string`
      - **`public_show_attendee_notes` (required)**: `boolean`
      - **`public_show_order_details` (required)**: `boolean`
      - **`public_show_question_answers` (required)**: `boolean`
      - **`short_id` (required)**: `string`
      - **`total_attendees` (required)**: `integer | null`
      - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

        *Schema `EventOccurrenceResource` is shown above.*
      - **`is_active`**: `boolean`
      - **`is_expired`**: `boolean`
      - **`products`**: `array | null`

        **Items:**

        schema: `ProductResource`
        - **`description` (required)**: `string | null`
        - **`event_id` (required)**: `integer`
        - **`hide_after_sale_end_date` (required)**: `boolean`
        - **`hide_before_sale_start_date` (required)**: `boolean`
        - **`hide_when_sold_out` (required)**: `boolean`
        - **`highlight_message` (required)**: `string | null`
        - **`id` (required)**: `integer`
        - **`initial_quantity_available` (required)**: `integer | null`
        - **`is_addon_only` (required)**: `boolean`
        - **`is_after_sale_end_date` (required)**: `boolean`
        - **`is_available` (required)**: `boolean`
        - **`is_before_sale_start_date` (required)**: `boolean`
        - **`is_hidden` (required)**: `boolean | null`
        - **`is_hidden_without_promo_code` (required)**: `boolean`
        - **`is_highlighted` (required)**: `boolean`
        - **`max_per_order` (required)**: `integer`
        - **`min_per_order` (required)**: `integer`
        - **`order` (required)**: `integer`
        - **`product_category_id` (required)**: `integer | null`
        - **`product_type` (required)**: `string`, possible values: `"TICKET", "GENERAL"`
        - **`quantity_sold` (required)**: `integer`
        - **`sale_end_date` (required)**: `string | null`
        - **`sale_start_date` (required)**: `string | null`
        - **`sequential_tier_release_enabled` (required)**: `boolean`
        - **`show_quantity_remaining` (required)**: `boolean`
        - **`start_collapsed` (required)**: `boolean`
        - **`title` (required)**: `string`
        - **`type` (required)**: `string`, possible values: `"PAID", "FREE", "DONATION", "TIERED", "REGISTRATION"`
        - **`waitlist_enabled` (required)**: `boolean | null`
        - **`addon_product_ids`**: `(array of any) | null`
        - **`addons`**: `array`

          **Items:**
          - **`id` (required)**: `integer`
          - **`title` (required)**: `string`
        - **`is_sold_out`**: `boolean`
        - **`price`**: `number | null`
        - **`prices`**: `array | null`

          **Items:**

          schema: `ProductPriceResource`
          - **`id` (required)**: `integer`
          - **`initial_quantity_available` (required)**: `integer | null`
          - **`is_after_sale_end_date` (required)**: `boolean`
          - **`is_available` (required)**: `boolean | null`
          - **`is_before_sale_start_date` (required)**: `boolean`
          - **`is_hidden` (required)**: `boolean | null`
          - **`is_locked_behind_earlier_tier` (required)**: `boolean`
          - **`is_sold_out` (required)**: `boolean`
          - **`label` (required)**: `string | null`
          - **`off_sale_reason` (required)**: `string | null`
          - **`price` (required)**: `number`
          - **`price_including_taxes_and_fees` (required)**: `number`
          - **`quantity_applies_to` (required)**: `string`, possible values: `"OCCURRENCE", "EVENT"`
          - **`quantity_sold` (required)**: `integer`
          - **`sale_end_date` (required)**: `string | null`
          - **`sale_start_date` (required)**: `string | null`
        - **`taxes_and_fees`**: `array | null`

          **Items:**

          schema: `TaxAndFeeResource`
          - **`account_id` (required)**: `integer`
          - **`calculation_type` (required)**: `string`
          - **`description` (required)**: `string | null`
          - **`id` (required)**: `integer`
          - **`is_active` (required)**: `boolean | null`
          - **`is_default` (required)**: `boolean`
          - **`name` (required)**: `string`
          - **`rate` (required)**: `number`
          - **`type` (required)**: `string`
  - **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

    *Schema `EventOccurrenceResource` is shown above.*
  - **`order`**: `object | null`, schema: `OrderResource`

    *Schema `OrderResource` is shown above.*
  - **`product`**: `object | null`, schema: `ProductResource`

    *Schema `ProductResource` is shown above.*
  - **`question_answers`**: `array | null`

    **Items:**

    *Schema `QuestionAnswerViewResource` is shown above.*

**Example:**

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "product_id": 1,
    "product_price_id": 1,
    "event_id": 1,
    "email": null,
    "status": "ACTIVE",
    "first_name": "",
    "last_name": "",
    "public_id": "",
    "short_id": "",
    "event_occurrence_id": null,
    "seat_uid": null,
    "seat_label": null,
    "locale": "",
    "notes": null,
    "event_occurrence": null,
    "product": null,
    "check_ins": [],
    "order": null,
    "question_answers": [],
    "created_at": "",
    "updated_at": ""
  }
}
```

### 401 Unauthenticated

**Content type:** `application/json`

- **`message` (required)**: `string`

  Error overview.

**Example:**

```json
{
  "message": ""
}
```

### 403 The authenticated user is not allowed to perform this action.

**Content type:** `application/json`

- **`message` (required)**: `string`

**Example:**

```json
{
  "message": ""
}
```

### 404

## Schemas

- `AttendeeCheckInResource` — shown above.
- `AttendeeResource` — shown above.

### EventLocationResource

**Type:** `object`

- **`id` (required)**: `integer`
- **`location_id` (required)**: `integer | null`
- **`type` (required)**: `string`
- **`location`**: `object | null`, schema: `LocationResource`

  *Schema `LocationResource` is shown above.*
- **`online_event_connection_details`**: `string | null`

**Example:**

```json
{
  "id": 1,
  "type": "",
  "location_id": null,
  "online_event_connection_details": null,
  "location": {
    "id": 1,
    "organizer_id": 1,
    "name": null,
    "structured_address": [],
    "latitude": null,
    "longitude": null,
    "provider": null,
    "provider_place_id": null,
    "created_at": null,
    "updated_at": null
  }
}
```

### EventOccurrenceResource

**Type:** `object`

- **`available_capacity` (required)**: `integer | null`
- **`capacity` (required)**: `integer | null`
- **`created_at` (required)**: `string | null`
- **`end_date` (required)**: `string | null`
- **`event_id` (required)**: `integer`
- **`id` (required)**: `integer`
- **`is_active` (required)**: `boolean`
- **`is_future` (required)**: `boolean`
- **`is_overridden` (required)**: `boolean`
- **`is_past` (required)**: `boolean`
- **`label` (required)**: `string | null`
- **`short_id` (required)**: `string`
- **`show_available_capacity` (required)**: `boolean | null`
- **`start_date` (required)**: `string`
- **`status` (required)**: `string`
- **`updated_at` (required)**: `string | null`
- **`used_capacity` (required)**: `integer`
- **`booking_limits`**: `object`
  - **`allocation_total` (required)**: `integer | null`
  - **`allocations` (required)**: `array of any`
  - **`capacity` (required)**: `integer | null`
  - **`sellable` (required)**: `integer | null`
- **`event_location`**: `object | null`, schema: `EventLocationResource`

  *Schema `EventLocationResource` is shown above.*
- **`statistics`**: `object`
  - **`attendees_registered` (required)**: `integer`
  - **`orders_created` (required)**: `integer`
  - **`products_sold` (required)**: `integer`
  - **`total_fee`**: `number`
  - **`total_gross_sales`**: `number`
  - **`total_refunded`**: `number`
  - **`total_tax`**: `number`

**Example:**

```json
{
  "id": 1,
  "event_id": 1,
  "short_id": "",
  "start_date": "",
  "end_date": null,
  "status": "",
  "capacity": null,
  "used_capacity": 1,
  "available_capacity": null,
  "label": null,
  "show_available_capacity": null,
  "is_overridden": true,
  "is_past": true,
  "is_future": true,
  "is_active": true,
  "event_location": {
    "id": 1,
    "type": "",
    "location_id": null,
    "online_event_connection_details": null,
    "location": null
  },
  "booking_limits": {
    "capacity": null,
    "allocation_total": null,
    "sellable": null,
    "allocations": []
  },
  "statistics": {
    "orders_created": 1,
    "attendees_registered": 1,
    "products_sold": 1,
    "total_gross_sales": 1,
    "total_tax": 1,
    "total_fee": 1,
    "total_refunded": 1
  },
  "created_at": null,
  "updated_at": null
}
```

### LocationResource

**Type:** `object`

- **`created_at` (required)**: `string | null`
- **`id` (required)**: `integer`
- **`latitude` (required)**: `number | null`
- **`longitude` (required)**: `number | null`
- **`name` (required)**: `string | null`
- **`organizer_id` (required)**: `integer`
- **`provider` (required)**: `string | null`
- **`provider_place_id` (required)**: `string | null`
- **`structured_address` (required)**: `null | (array of any) | string | object`
- **`updated_at` (required)**: `string | null`

**Example:**

```json
{
  "id": 1,
  "organizer_id": 1,
  "name": null,
  "structured_address": [],
  "latitude": null,
  "longitude": null,
  "provider": null,
  "provider_place_id": null,
  "created_at": null,
  "updated_at": null
}
```

### OrderItemResource

**Type:** `object`

- **`band_key` (required)**: `string | null`
- **`event_occurrence_id` (required)**: `integer | null`
- **`id` (required)**: `integer`
- **`item_name` (required)**: `string | null`
- **`order_id` (required)**: `integer`
- **`product_id` (required)**: `integer`
- **`quantity` (required)**: `integer`
- **`event_occurrence`**: `object | null`, schema: `EventOccurrenceResource`

  *Schema `EventOccurrenceResource` is shown above.*
- **`price`**: `number`
- **`price_before_discount`**: `number | null`
- **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
- **`total_before_additions`**: `number`

**Example:**

```json
{
  "id": 1,
  "order_id": 1,
  "total_before_additions": 1,
  "price": 1,
  "price_before_discount": null,
  "taxes_and_fees_rollup": [],
  "quantity": 1,
  "product_id": 1,
  "event_occurrence_id": null,
  "band_key": null,
  "item_name": null,
  "event_occurrence": {
    "id": 1,
    "event_id": 1,
    "short_id": "",
    "start_date": "",
    "end_date": null,
    "status": "",
    "capacity": null,
    "used_capacity": 1,
    "available_capacity": null,
    "label": null,
    "show_available_capacity": null,
    "is_overridden": true,
    "is_past": true,
    "is_future": true,
    "is_active": true,
    "event_location": null,
    "booking_limits": {
      "capacity": null,
      "allocation_total": null,
      "sellable": null,
      "allocations": []
    },
    "statistics": {
      "orders_created": 1,
      "attendees_registered": 1,
      "products_sold": 1,
      "total_gross_sales": 1,
      "total_tax": 1,
      "total_fee": 1,
      "total_refunded": 1
    },
    "created_at": null,
    "updated_at": null
  }
}
```

### OrderResource

**Type:** `object`

- **`address` (required)**: `null | (array of any) | string | object`
- **`box_office_id` (required)**: `integer | null`
- **`created_at` (required)**: `string`
- **`currency` (required)**: `string`
- **`email` (required)**: `string | null`
- **`event_id` (required)**: `integer`
- **`first_name` (required)**: `string | null`
- **`id` (required)**: `integer`
- **`is_free_order` (required)**: `boolean`
- **`is_fully_refunded` (required)**: `boolean`
- **`is_manually_created` (required)**: `boolean`
- **`is_partially_refunded` (required)**: `boolean`
- **`last_name` (required)**: `string | null`
- **`notes` (required)**: `string | null`
- **`payment_provider` (required)**: `string | null`
- **`payment_status` (required)**: `string | null`, possible values: `"NO_PAYMENT_REQUIRED", "AWAITING_PAYMENT", "AWAITING_OFFLINE_PAYMENT", "PAYMENT_FAILED", "PAYMENT_RECEIVED", null`
- **`promo_code` (required)**: `string | null`
- **`public_id` (required)**: `string`
- **`refund_status` (required)**: `string | null`, possible values: `"REFUND_PENDING", "REFUND_FAILED", "REFUNDED", "PARTIALLY_REFUNDED", null`
- **`status` (required)**: `string`, possible values: `"RESERVED", "CANCELLED", "COMPLETED", "AWAITING_OFFLINE_PAYMENT", "ABANDONED"`
- **`attendees`**: `array | null`

  **Items:**

  *Schema `AttendeeResource` is shown above.*
- **`box_office_amount_tendered`**: `number | null`
- **`box_office_change_due`**: `number | null`
- **`box_office_operator_name`**: `string | null`
- **`box_office_reference`**: `string | null`
- **`box_office_tender`**: `string | null`, possible values: `"CASH", "CARD", "COMP", "OTHER", "FREE", null`
- **`latest_invoice`**: `object`
  - **`id` (required)**: `integer`
  - **`invoice_number` (required)**: `string`
  - **`order_id` (required)**: `integer`
  - **`status` (required)**: `string`
- **`order_items`**: `array | null`

  **Items:**

  *Schema `OrderItemResource` is shown above.*
- **`question_answers`**: `array | null`

  **Items:**

  *Schema `QuestionAnswerViewResource` is shown above.*
- **`short_id`**: `string`
- **`taxes_and_fees_rollup`**: `null | (array of any) | string | object`
- **`total_before_additions`**: `number`
- **`total_fee`**: `number`
- **`total_gross`**: `number`
- **`total_refunded`**: `number`
- **`total_tax`**: `number`

**Example:**

```json
{
  "id": 1,
  "short_id": "",
  "total_before_additions": 1,
  "total_gross": 1,
  "total_tax": 1,
  "total_fee": 1,
  "total_refunded": 1,
  "taxes_and_fees_rollup": [],
  "status": "RESERVED",
  "refund_status": "REFUND_PENDING",
  "payment_status": "NO_PAYMENT_REQUIRED",
  "currency": "",
  "first_name": null,
  "last_name": null,
  "email": null,
  "created_at": "",
  "public_id": "",
  "is_partially_refunded": true,
  "is_fully_refunded": true,
  "is_free_order": true,
  "is_manually_created": true,
  "box_office_id": null,
  "box_office_operator_name": null,
  "box_office_tender": "CASH",
  "box_office_amount_tendered": null,
  "box_office_change_due": null,
  "box_office_reference": null,
  "address": [],
  "notes": null,
  "payment_provider": null,
  "promo_code": null,
  "event_id": 1,
  "order_items": [
    {
      "id": 1,
      "order_id": 1,
      "total_before_additions": 1,
      "price": 1,
      "price_before_discount": null,
      "taxes_and_fees_rollup": [],
      "quantity": 1,
      "product_id": 1,
      "event_occurrence_id": null,
      "band_key": null,
      "item_name": null,
      "event_occurrence": null
    }
  ],
  "attendees": [
    {
      "id": 1,
      "order_id": 1,
      "product_id": 1,
      "product_price_id": 1,
      "event_id": 1,
      "email": null,
      "status": "ACTIVE",
      "first_name": "",
      "last_name": "",
      "public_id": "",
      "short_id": "",
      "event_occurrence_id": null,
      "seat_uid": null,
      "seat_label": null,
      "locale": "",
      "notes": null,
      "event_occurrence": null,
      "product": null,
      "check_ins": [],
      "order": null,
      "question_answers": [],
      "created_at": "",
      "updated_at": ""
    }
  ],
  "question_answers": [
    {
      "product_id": null,
      "product_title": null,
      "question_id": 1,
      "title": "",
      "question_required": true,
      "question_description": null,
      "answer": [],
      "text_answer": "",
      "order_id": null,
      "belongs_to": "",
      "question_type": "",
      "event_id": 1,
      "question_answer_id": 1,
      "question_options": [],
      "attendee_id": null,
      "first_name": null,
      "last_name": null,
      "attendee_public_id": null
    }
  ],
  "latest_invoice": {
    "id": 1,
    "invoice_number": "",
    "order_id": 1,
    "status": ""
  }
}
```

- `ProductPriceResource` — shown above.
- `ProductResource` — shown above.
- `QuestionAnswerViewResource` — shown above.
- `TaxAndFeeResource` — shown above.

---

# Webhooks and statuses (Hi.Events v2.0.0)

Not part of the API reference above. Read from the v2.0.0 source and checked against real deliveries
from a local v2.0.0 instance (5 November 2026). Anything not tested that way says so.

## Delivery

- `POST` with `Content-Type: application/json`, `User-Agent: GuzzleHttp/7`, `X-Webhook-Source: Hi.Events`.
- `Signature` header: lowercase hex HMAC-SHA256 of the raw request body, keyed with the webhook's secret.
  Matched 22 of 22 captured deliveries.
- Secrets are per webhook. Each new webhook gets its own.
- Internal targets are rejected unless the host is in `APP_ALLOWED_INTERNAL_WEBHOOK_HOSTS`: `localhost`,
  private IPs, and `.local`, `.internal`, `.intranet`, `.localhost` names. `*.ddev.site` resolves to
  127.0.0.1, so it is rejected too.

```json
{
  "event_type": "order.created",
  "event_sent_at": "2026-11-05T13:18:02+00:00",
  "payload": {}
}
```

`payload` is the same `OrderResource` / `AttendeeResource` as the API, including `event_id`, so ignore
events for other events.

## Event types

`order.created`, `order.updated`, `order.marked_as_paid`, `order.refunded`, `order.cancelled`,
`attendee.created`, `attendee.updated`, `attendee.cancelled`. Also exist, not relevant here:
`product.*`, `event.*`, `checkin.*`, `occurrence.cancelled`.

`order.*` payloads include `attendees` and `question_answers`. `order_items` and attendees carry
`product_id`; attendees also carry `product_price_id`.

## What actually happens

| Action | Deliveries, in order |
|---|---|
| Free order completed | `attendee.created` per attendee (`ACTIVE`), then `order.created` (`COMPLETED`, `NO_PAYMENT_REQUIRED`) |
| Offline order placed | `attendee.created` per attendee (`AWAITING_PAYMENT`), then `order.created` (`AWAITING_OFFLINE_PAYMENT`) |
| Offline order marked paid | Only `order.marked_as_paid` (`COMPLETED`, `PAYMENT_RECEIVED`, attendees now `ACTIVE`) |
| Order cancelled | `attendee.cancelled` per attendee, then `order.cancelled` |
| One attendee cancelled | Only `attendee.cancelled`, with `order_id`. Nothing for the order |
| Order details edited | `order.updated` |
| Cancelled attendee reactivated | **Nothing.** Only reconciliation can notice this |

Consequences for the seat app:

- `attendee.created` arrives before its order and can be unpaid. Act on the order events.
- A paid offline order sends no `attendee.updated`, so `order.marked_as_paid` must be handled.
- No `order.*` event fires while an order is only `RESERVED` (checkout in progress).

Not tested: `order.refunded` (offline orders have no refund flow, it needs Stripe) and
`attendee.updated`.

## Statuses

- Order `status`: `RESERVED`, `CANCELLED`, `COMPLETED`, `AWAITING_OFFLINE_PAYMENT`, `ABANDONED`.
- Order `payment_status`: `NO_PAYMENT_REQUIRED`, `AWAITING_PAYMENT`, `AWAITING_OFFLINE_PAYMENT`,
  `PAYMENT_FAILED`, `PAYMENT_RECEIVED`.
- Attendee `status`: `ACTIVE`, `AWAITING_PAYMENT`, `CANCELLED`.

An order counts as paid when `status` is `COMPLETED`.

## Checkout questions

An order-level answer in `question_answers`:

```json
{
  "question_id": 1,
  "title": "Rollstuhlplatz",
  "belongs_to": "ORDER",
  "question_type": "CHECKBOX",
  "answer": ["Wir brauchen einen Rollstuhlplatz"],
  "text_answer": "Wir brauchen einen Rollstuhlplatz",
  "order_id": 4,
  "event_id": 1
}
```

`GET /events/{id}/orders` does not include `question_answers`, although it does include `attendees`.
`GET /events/{id}/orders/{order_id}` includes both, and so do `order.*` webhooks. Reconciliation has to
fetch single orders for the answer.

## API keys

- Sent as `Authorization: Bearer <key>`. A `READ_ONLY` key reads events, orders and attendees and gets
  `403` on writes, confirmed.
- Lists are paginated with `per_page` and `page`, and report `meta.last_page`.
