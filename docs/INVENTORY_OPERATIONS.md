# Inventory operations

Inventory tracking is opt-in. Existing products remain untracked after migration; their historical `stock_quantity` values do not become sale limits. Existing orders receive no inferred reservations.

## Stock policies

Choose a policy in the product editor:

| Policy | Behavior |
|---|---|
| Untracked / made to order | Quantity is unrestricted by inventory; product and variant availability still apply. |
| Shared stock across variants | All variants and custom configurations draw from the product's on-hand quantity. |
| Separate stock per variant | Tracked variants each have their own on-hand quantity. Other variants remain untracked. |

On-hand stock includes reserved units. Available quantity is **on hand minus reserved**. For example, five units on hand with two reserved means three can be sold. Toppings and other configuration choices share the selected product or variant pool.

The product's In Stock and Active switches and each variant's Available and Active switches also control sales. Turning availability off prevents new sales; it does not release existing reservations.

## Order lifecycle

Carts do not reserve stock. Checkout reloads and locks current product/variant rows, aggregates all quantities drawing from each pool, and reserves stock inside the transaction that creates the order. Insufficient stock preserves the cart and creates no order, allocation, or stock movement. Failed checkout rolls back its stock changes too.

| Event | On-hand change | Reserved change | Allocation state |
|---|---:|---:|---|
| Checkout for quantity Q | 0 | +Q | Reserved |
| Staff confirms fulfillment, advances fulfillment, or marks payment paid | −Q | −Q | Consumed |
| Staff cancels a pending reserved order | 0 | −Q | Released |
| Staff cancels consumed stock before dispatch | +Q | 0 | Restocked |

Manual-payment orders with uploaded receipts **retain reservations until staff confirms or cancels the order**. There is no automatic expiry job. Staff should review pending orders regularly. The same indefinite hold policy currently applies to other pending tracked orders.

Recording failed payment for a pending order cancels it and releases its holds. For confirmed or preparing orders, explicitly cancel before recording payment failure. Cancelled orders cannot reopen; fulfillment cannot move backward. Single and bulk updates use the same transaction and inventory rules. Repeating a supported status or payment update does not move stock twice, including after a terminal order's catalog records have been deleted.

Dispatched and delivered orders cannot be cancelled through this workflow. Refunds do not automatically restore stock. A separate physical return workflow remains future work. Delivery does not mark payment paid; staff must record payment separately.

## Staff adjustments and history

Enter the absolute on-hand quantity in the product editor. Saving below reserved quantity is rejected. Stock edits also check the revision saved with the form: if checkout, another staff edit, or an order update changes tracked stock, reload the editor before saving.

Changing the product's stock policy or a variant's tracking switch is blocked while affected allocations are reserved or consumed on undelivered orders. Complete or cancel those orders first. Product, variant, and order deletion use the same protection. Supported deletion detaches terminal records while retaining order snapshots and movement history; raw database cascades are restricted.

The product editor displays the latest 20 stock movements with staff identity, action, quantity changes, and resulting balances. Order details show each tracked item's reservation state. Movement records are retained and cannot be edited or deleted through the model. History contains stock identity and product/variant/order snapshots even after supported catalog or order deletion.

## Verification

Apply the schema through the usual migration command:

```sh
php artisan migrate --force
```

Run functional checks:

```sh
php artisan test
```

The explicit concurrency check uses the configured local MySQL server. It creates a randomly named `mavyn_inventory_check_*` database, migrates and tests it, then drops that database. The configured account needs permission to create/drop a disposable database; the application database is not modified.

```sh
php tests/Support/inventory-concurrency.php
```

This exercises competing checkouts for the last shared-product and variant unit and simultaneous cancellations. SQLite functional tests cover rollback, availability, admin revision conflicts, manual receipt holds, bulk transitions, and deletion/history retention. Browser verification uses disposable fixtures for stock editing, checkout, sold-out variants, quantity controls, and mobile behavior.

Inventory movements and checkout retries are protected. Repeating the same completed checkout key returns its original order without moving stock again; see [Checkout retries and recovery](CHECKOUT_OPERATIONS.md). Decimal-safe pricing and guest coupon identity remain future work.
