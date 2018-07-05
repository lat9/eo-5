# Edit Orders v5.0.0, Design Considerations #

Unlike its previous version, EO-5 edits the order *in-place*, taking care to keep existing `orders_products` records where possible.  On an order-update, there are multiple steps taken:

1. Update order's addresses
1. Update changes to products in the order
1. Calculate and update the order's totals.
2. *(optionally)* Send the customer an email notification.
3. Create Order-Status record(s) identifying any change.

On entry to the update processing, EO now creates a copy of what the order *was*, on entry, for comparison to what the order *is* after the updates.  The order-class instance is updated in-place by each step, with the expectation that the order, on presentation to the order-totals' processing, has the same information as the storefront.

## Order Management ##



## Taxes ##

### Manually-entered Pricing

When an order is updated using manually-entered pricing, EO reformats the title associated with a product's `tax-group` to create a `generic` label for those taxes, e.g. changing **FL TAX 7.0%** to **Sales Tax 7%**.  This is done since there might be one or more products in the current order that no longer exist in the store's database.

The tax-group label(s) that EO creates reflect the *non-zero* value(s) that the admin has entered for a product's tax:

1. If all product-tax entries are 0, no label is created (since there is no product tax).
1. If the product-tax entries are all either 0 or another unique value (e.g. 7), then EO creates a single tax-group label (e.g. Sales Tax 7%).
1. Otherwise, multiple product-tax values were entered.  Processing depends on how the store has configured "Show Split Tax Lines"?
   a. When set to **false**, EO creates a single tax-group label: **Sales Tax**.
   b. When set to true, EO creates multiple tax-group labels &hellip; one for each *unique* tax-rate entered.


## Considerations ##

What should happen if &hellip;

1. An ordered product no longer exists in the database?
2. The customer's shipping address changes, causing a change in the to-be-applied product (and other) taxes?