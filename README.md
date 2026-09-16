# ScandiPWA SalesGraphQl

Fork of [scandipwa/sales-graphql](https://github.com/scandipwa/sales-graphql) 1.3.2, maintained by Selveq for Magento 2.4.9 and PHP 8.3. Module name and namespace are unchanged, and the package replaces `scandipwa/sales-graphql` at every version, so it installs as a drop-in replacement. Selveq is not affiliated with or endorsed by Scandiweb.

## What it does

- Fills out the order history the theme reads through `customer{orders}` with `rss_link`, `can_reorder`, `country_id`, `purchase_number` and bundle and downloadable rows among an item's options, and leaves out orders whose status is hidden from the storefront.
- Answers the whole order behind a child document's id through `orderByInvoice`, `orderByShipment` and `orderByRefund`, and only to the customer who owns that order.
- Resolves an order's invoices, shipments and credit memos with their items and their comments.
- Reorders under the same lock Magento's own reorder takes, so one order cannot be reordered twice at once.

## Install

```sh
composer require selveq/sales-graphql
bin/magento setup:upgrade
```

## License

[OSL-3.0](LICENSE), the license of the original work. Scandiweb's copyright notices are kept in every file, and each file Selveq changed carries a `Modifications © Selveq` notice.
