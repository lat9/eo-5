# Notifications

***Edit Orders*** makes use of the Zen Cart notifier/observer architecture to allow integration with other plugins and/or store-specific processes without affecting the "base" EO handling.

### NOTIFY_EO_GET_PRODUCTS_STOCK

Issued by the `editOrders` class method `eoGetProductsStock` to determine the product's currently-available stock.

Parameters:

1. (r/o) The (integer) `products_id` associated with the product.
1. (r/w) The (float) product-quantity to be returned if the following variable is set to `true`.
1. (r/w) The (bool) indicator that identifies whether (`true`) or not the product-quantity was updated.

If a watching observer sets the indicator to boolean `true`, that method returns the value set in parameter #2.

### EDIT_ORDERS_REMOVE_PRODUCT_STOCK_DECREMENT

Issued by the `editOrders` class method `removeProductFromOrder` prior handling the product's stock levels, giving a listening observer to provide customized stock-handling.

Parameters:

1. (r/o) An associative array:
	- `order_id` ... the integer identifier associated with the overall order.
	- `orders_products_id` ... the integer identifier associated with the to-be-removed product.
1. (r/w) The (bool) indicator that identifies whether (`true`) or not the product's stock has been "handled".

If a watching observer sets the indicator to boolean `true`, the observer has provided any stock-related updates  and the built-in EO handling is bypassed.

### EDIT_ORDERS_REMOVE_PRODUCT

Issued by the `editOrders` class method `removeProductFromOrder` prior to the product's removal from the order in the database, giving a listening observer to perform any additional "clean-up" for that product's removal.

Parameters:

1. (r/o) An associative array:
	- `order_id` ... the integer identifier associated with the overall order.
	- `orders_products_id` ... the integer identifier associated with the to-be-removed product.

### EDIT_ORDERS_ADD_PRODUCT_STOCK_DECREMENT

Issued by the `editOrders` class method `addProductToOrder` prior handling the product's stock levels, giving a listening observer to provide customized stock-handling.

Parameters:

1. (r/o) An associative array:
	- `order_id` ... the integer identifier associated with the overall order.
	- `product` ... an associative array containing the fields to be written to the store's `orders_products` database table.
1. (r/w) The (bool) indicator that identifies whether (`true`) or not the product's stock has been "handled".

If a watching observer sets the indicator to boolean `true`, the observer has provided any stock-related updates  and the built-in EO handling is bypassed.

### EDIT_ORDERS_ADD_PRODUCT

Issued by the `editOrders` class method `addProductToOrder` prior handling the product's stock levels, giving a listening observer to provide customized stock-handling.

Parameters:

1. (r/o) An associative array:
	- `order_id` ... the integer identifier associated with the overall order.
	- `orders_products_id` ... the integer identifier associated with the just-created additional product in the order.
	- `product` ... an associative array containing the fields to be written to the store's `orders_products` database table.

### EO_UPDATE_DATABASE_ORDER_TOTALS_MAIN

Issued by the `editOrders` class method `tbd` just prior to updating ***all*** the order's totals in the database.

Parameters:

1. (r/o, int) `order_id` ... the integer identifier associated with the overall order.


### EO_UPDATE_DATABASE_ORDER_TOTALS_ITEM

Issued by the `editOrders` class method `tbd` just prior to updating each order-total in the database.

Parameters:

1. (r/o, int) `order_id` ... the integer identifier associated with the overall order.
2. (r/w, array) `order_total` ... an updateable copy of the current to-be-updated order-total.


### NOTIFY_EDIT_ORDERS_PRE_CUSTOMER_NOTIFICATION

Issued by the `editOrders` class method `eoUpdateOrdersHistoryNotifyCustomer` prior to updating the order's history or sending any updated-order email to the customer.  Gives a listening observer to perform additional actions on the order's update.

Parameters:

1. (r/o) An associative array:
    - `oID` ... the integer identifier associated with the overall order.
    - `old_status` ... the integer identifying the order's current status.
    - `new_status` ... the integer identifying the order's new status.
    - `comments` ... the (string) comments to be associated with the order-update.
    - `notify_customer` ... the integer identifying the customer-notification value to accompany any status-update.
    - `notify_comments` ... a boolean value that identifies whether (`true`) or not to include the comments in any email generated for the status-update.
