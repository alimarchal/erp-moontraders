# Optional Modules: Batches/Serials, Price Lists & Rules, Pick Lists

This project includes optional modules that are disabled by default (no code depends on them). You can start using them anytime after migrating.

## What's included

- Batches: `batches` table per product with batch_no, mfg/expiry
- Serials: `serial_numbers` table per product with optional batch, current warehouse and status
- Price Lists: `price_lists` and `item_prices` to maintain standard rates per product/UOM
- Pricing Rules: `pricing_rules` for discounts or fixed rates by product, price list, customer and date window
- Pick Lists: `pick_lists` and `pick_list_items` to plan and track warehouse picking

## Quick start

1) Run migrations (already safe for MySQL index lengths):

   - php artisan migrate

2) Create a price list and rates:

   - Insert into `price_lists` (name, currency) and then `item_prices` (price_list_id, product_id, uom_id, rate, valid_from).

3) Optional pricing rules:

   - Add `pricing_rules` for discount_percent, discount_amount, or fixed_rate, scoped by product and/or customer, with valid_from/upon.

4) Enable batches/serials if your products require tracking:

   - Create `batches` per product and link `serial_numbers` where applicable. Use `current_warehouse_id` to reflect location.

5) Create pick lists when preparing deliveries:

   - Create a `pick_lists` record (warehouse, picker, scheduled_date), then add `pick_list_items` with product, qty_to_pick, and optionally batch/serial and source_warehouse_id. Update `picked_qty` and `status` as work progresses.

## Notes & MySQL considerations

- Index names are kept short to satisfy MySQL's 64-character limit.
- All new FKs use `nullOnDelete()` where optional to avoid cascading surprises.
- These tables are intentionally independent to avoid breaking existing workflows.

## Next ideas

- Service layer to resolve item price by price list + pricing rules, with precedence and tie-breakers.
- UI pages for batch/serial management and pick list execution.
- Background job to auto-close pick lists when all items are picked.
