# Changelog

## 2.0.0

Forked from `scandipwa/sales-graphql` 1.3.2. Module name and namespace are unchanged, and the package replaces `scandipwa/sales-graphql` at every version, so it installs as a drop-in replacement.

- The reorder lock is restored, so two concurrent reorders of one order no longer both run.
- An invoice, shipment or credit-memo id of `0` is refused like any other unknown id.
- The three print queries are declared uncacheable.
- An order's status history is read once per order.
- The overrides that only restated Magento are removed.
- The Adobe notices are added to the classes that copy Magento core.
- Orders whose status the merchant hid from the storefront no longer appear in the order list, matching what a single-order lookup already refused.
- Two filter fields sent together now narrow the result instead of widening it, and an empty `match` value no longer fails the query.
- The status restriction on the order list is a deliberate divergence: Magento's own SalesGraphQl applies no such filter.
- Restore `email`, `applied_coupons` and the timezone-formatted `order_date` dropped by the order formatter; `applied_coupons` is non-nullable, so its absence was nulling the whole order item.
- Restore `subtotal_incl_tax`, `subtotal_excl_tax` and `grand_total_excl_tax`, all non-nullable, so selecting `total` no longer fails.
- Restore `store_ids` in the customer-orders payload; without it `date_of_first_order` raised an undefined-key error.
- Restore the non-nullable `applied_to` on item discounts.
- Emit `country_code` alongside `country_id` so the core order-address field resolves again.
- Restore `parent_sku` and the tax-display-aware item sale price.
- Restore the null-payment guard before reading payment data.
- Format invoice and shipment comment timestamps through the store timezone.
- Restore the `Discount` label fallback.
- Use the iterated option, not the outer array, for the selected-option type, and restore the `print_value` fallback.
- The print resolvers return the same authorization message for not-found and not-authorized, closing an id-existence oracle.
- Declare the direct Magento dependencies and `selveq/route717`, and mirror them into `module.xml` `<sequence>`.
- Declare the `ignoredURLs` SPA-router bypasses in `etc/frontend/di.xml`, because Magento merges an area's DI config over the global one with a non-recursive `array_replace()` at argument granularity and a global declaration would never apply.
- `OrderItemOption.value` and `Discount.label` are nullable where Magento declares them non-null, so an option without a print value resolves.
