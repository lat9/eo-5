<?php
// -----
// Part of the Edit Orders plugin (v4.1.6 and later) by lat9 (lat9@vinosdefrutastropicales.com).
// Copyright (C) 2016-2018, Vinos de Frutas Tropicales
//
if (!defined('EO_DEBUG_TAXES_ONLY')) define('EO_DEBUG_TAXES_ONLY', 'false');  //-Either 'true' or 'false'
class editOrders extends base
{
    public function __construct($orders_id)
    {
        global $db, $currencies;
        
        $this->eo_action_level = EO_DEBUG_ACTION_LEVEL;
        $this->orders_id = (int)$orders_id;
        $this->tax_updated = false;
        $this->update_database = false;
        
        $this->price_calc_auto = (EO_PRODUCT_PRICE_CALC_METHOD == 'Auto' || (EO_PRODUCT_PRICE_CALC_METHOD == 'Choose' && !isset($_POST['payment_calc_manual'])));
        
        // -----
        // Gather the currency-information associated with the current order, saving that information
        // into the current session.  This also sets the global 'currencies' class.
        //
        $currency_info = $db->Execute(
            "SELECT currency, currency_value 
               FROM " . TABLE_ORDERS . " 
              WHERE orders_id = " . $this->orders_id . " 
              LIMIT 1"
        );
        $this->currency = $currency_info->fields['currency'];
        $this->currency_value = $currency_info->fields['currency_value'];
        unset($currency_info);
        
        if (!isset($currencies)) {
            if (!class_exists('currencies')) {
                require DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php';
            }
            $currencies = new currencies();
        }
        $_SESSION['currency'] = $this->currency;
        $this->order_currency = $currencies->currencies[$this->currency];

        // -----
        // Create the logs/edit_orders directory, if not already present.
        //
        if ($this->eo_action_level != 0) {
            $log_file_dir = (defined('DIR_FS_LOGS') ? DIR_FS_LOGS : DIR_FS_SQL_CACHE) . '/edit_orders';
            if (!is_dir($log_file_dir) && !mkdir($log_file_dir, 0777, true)) {
                $this->eo_action_level = 0;
                trigger_error("Failure creating the Edit Orders log-file directory ($log_file_dir); the plugin's debug is disabled until this issue is corrected.", E_USER_WARNING);
            } else {
                $this->logfile_name = $log_file_dir . '/debug_edit_orders_' . $orders_id . '.log';
            }
        }
        
        // -----
        // Preset the "ORDER BY" clause used to gather any products' options.
        //
        if (PRODUCTS_OPTIONS_SORT_ORDER == '0') {
            $this->options_order_by = 'LPAD(po.products_options_sort_order,11,"0"), po.products_options_name';
        } else {
            $this->options_order_by = 'po.products_options_name';
        }
        
        // -----
        // Preset the "ORDER BY" clause used to gather any products' options' values.
        //
       if (PRODUCTS_OPTIONS_SORT_BY_PRICE == '1') {
            $this->options_values_order_by = 'LPAD(pa.products_options_sort_order,11,"0"), pov.products_options_values_name';
        } else {
            $this->options_values_order_by = 'LPAD(pa.products_options_sort_order,11,"0"), pa.options_values_price';
        }
        
        // -----
        // Initialize the class-based array that will map an orders-status value to its name.
        //
        $this->initializeOrdersStatusValues();
    }
    
    // -----
    // During class construction, create an array, indexed on an orders-status-id, that maps
    // the order-status ID values to their current names (based on the admin's current language).
    //
    protected function initializeOrdersStatusValues()
    {
        $this->ordersStatuses = array();
        $query = $GLOBALS['db']->Execute(
            "SELECT orders_status_id, orders_status_name
               FROM " . TABLE_ORDERS_STATUS . "
              WHERE language_id = " . (int)$_SESSION['languages_id'] . "
          ORDER BY orders_status_id"
        );
        while (!$query->EOF) {
            $this->ordersStatuses[$query->fields['orders_status_id']] = $query->fields['orders_status_name'];
            $query->MoveNext();
        }
    }
    
    // ----- ----- ----- ----- ----- ----- ----- ----- -----
    // Publicly-available "helper" methods
    // ----- ----- ----- ----- ----- ----- ----- ----- -----
    
    // -----
    // Retrieves an orders-status "name" based on its "id" in the current admin language.
    //
    public function eoGetOrdersStatusName($orders_status_id)
    {
        return (isset($this->ordersStatuses[$orders_status_id])) ? $this->ordersStatuses[$orders_status_id] : "Unknown [$orders_status_id]";
    }
    
    // -----
    // Create an array of order-status name/id pairs for use as an input to zen_draw_pull_down_menu.
    //
    public function eoGetOrdersStatusDropdownInput()
    {
        $orders_statuses = array();
        foreach ($this->ordersStatuses as $key => $value) {
            $orders_statuses[] = array(
                'id' => $key,
                'text' => "$value [$key]"
            );
        }
        return $orders_statuses;
    }
    
    // -----
    // Processing was present in "eo_get_available_order_totals_class_values".
    //
    // Builds an array suitable for input to zen_draw_pull_down_menu, containing any additional
    // order-total modules available for the order.  The array built includes
    // the totals' sort-orders, used if that total is added to the order.
    //
    public function eoGetAdditionalOrderTotalsDropdown()
    {
        // -----
        // The order-object is declared global, since the order-totals "constructors" will
        // need that information.
        //
        global $order;
        
        // -----
        // First, determine the available order-totals, removing any from the list that
        // are already being used in the order.
        //
        $module_list = explode(';', (str_replace('.php', '', MODULE_ORDER_TOTAL_INSTALLED)));
        foreach ($order->totals as $current_total) {
            $ot_class = $current_total['class'];
            if ($ot_class == 'ot_local_sales_tax') {
                continue;
            }
            if (($key = array_search($ot_class, $module_list)) !== false) {
                unset($module_list[$key]);
            }
        }
        
        // -----
        // Load the order total classes that are available for the current order.
        //
        if (!class_exists('order_total')) {
            require DIR_FS_CATALOG . DIR_WS_CLASSES . 'order_total.php';
        }
        $order_totals = new order_total();
        $this->eoLog("eoGetAdditionalOrderTotalsDropdown, modules: " . implode(';', $module_list) . PHP_EOL . $this->eoFormatDataForLog($order_totals));
        
        $dropdown = array();
        foreach ($module_list as $class) {
            if ($class == 'ot_group_pricing' || $class == 'ot_cod_fee' || $class == 'ot_tax' || $class == 'ot_loworderfee') {
                continue;
            }
            $dropdown[] = array(
                'id' => $class,
                'text' => $GLOBALS[$class]->title,
                'sort_order' => $GLOBALS[$class]->sort_order
            );
        }
        return $dropdown;
    }
    
    // -----
    // Processing was previously in "eo_get_available_shipping_modules"
    //
    // Builds an array suitable for input to zen_draw_pull_down_menu, containing the 
    // shipping methods available for the order.  The array built includes
    // the shipping methods' tax-related settings, used on an order-update to
    // calculate any shipping-related tax.
    //
    public function eoGetAvailableShippingModules()
    {
        // -----
        // The order-object is declared global, since the order-totals "constructors" will
        // need that information.
        //
        global $order;
        
        $retval = array();
        if (defined('MODULE_SHIPPING_INSTALLED') && zen_not_null(MODULE_SHIPPING_INSTALLED)) {
            // Load the shopping cart class into the session
            eo_shopping_cart();

            // Load the shipping class into the globals
            if (!class_exists('shipping')) {
                require DIR_FS_CATALOG . DIR_WS_CLASSES . 'shipping.php';
            }
            $shipping_modules = new shipping();

            $use_strip_tags = (defined('EO_SHIPPING_DROPDOWN_STRIP_TAGS') && EO_SHIPPING_DROPDOWN_STRIP_TAGS === 'true');
            foreach ($shipping_modules->modules as $current_module) {
                $class = str_replace('.php', '', $current_module);
                if (isset($GLOBALS[$class]) && $GLOBALS[$class]->enabled) {
                    $shipping_info = array(
                        'id' => $GLOBALS[$class]->code,
                        'text' => ($use_strip_tags) ? strip_tags($GLOBALS[$class]->title) : $GLOBALS[$class]->title,
                        'tax_class' => 0,
                        'tax_basis' => ''
                    );
                    if (isset($GLOBALS[$class]->tax_class)) {
                        $shipping_info['tax_class'] = $GLOBALS[$class]->tax_class;
                        $shipping_info['tax_basis'] = $GLOBALS[$class]->tax_basis;
                    }
                    $retval[] = $shipping_info;
                }
                unset($GLOBALS[$class]);
            }
            unset($shipping_modules);
        }
        return $retval;
    }
    
    // -----
    // Appends a message to the EO log for the active order.
    //
    public function eoLog($message, $message_type = 'general') 
    {
        if ($this->eo_action_level != 0) {
            if (!(EO_DEBUG_TAXES_ONLY == 'true' && $message_type != 'tax')) {
                error_log($message . PHP_EOL, 3, $this->logfile_name);
            }
        }
    }
    
    // -----
    // Formats a to-be-logged data element, usually used for arrays.
    //
    public function eoFormatDataForLog($data)
    {
        return var_export($data, true);
    }
    
    // -----
    // Formats the order's current tax and total information for inclusion in a log.
    //
    public function eoFormatTaxInfoForLog($include_caller = false)
    {
        global $order;
        $log_info = PHP_EOL;
        
        if ($include_caller) {
            $trace = debug_backtrace();
            $log_info = ' Called by ' . $trace[1]['file'] . ' on line #' . $trace[1]['line'] . PHP_EOL;
        }
        
        if (!is_object($GLOBALS['order'])) {
            $log_info .= "\t" . 'Order-object is not set.' . PHP_EOL;
        } else {
            $log_info .= "\t" .
                'Subtotal: ' . ((isset($GLOBALS['order']->info['subtotal'])) ? $GLOBALS['order']->info['subtotal'] : '(not set)') . ', ' .
                'Shipping: ' . ((isset($GLOBALS['order']->info['shipping_cost'])) ? $GLOBALS['order']->info['shipping_cost'] : '(not set)') . ', ' .
                'Shipping Tax: ' . ((isset($GLOBALS['order']->info['shipping_tax'])) ? $GLOBALS['order']->info['shipping_tax'] : '(not set)') . ', ' .
                'Tax: ' . $GLOBALS['order']->info['tax'] . ', ' .
                'Total: ' . $GLOBALS['order']->info['total'] . ', ' .
                'Tax Groups: ' . $this->eoFormatDataForLog($GLOBALS['order']->info['tax_groups']) . PHP_EOL;
                
            $log_info .= "\t" .
                '$_SESSION[\'shipping\']: ' . ((isset($_SESSION['shipping'])) ? $this->eoFormatDataForLog($_SESSION['shipping'], true) : '(not set)') . PHP_EOL;
                
            $log_info .= $this->eoFormatOrderTotalsForLog();
        }
        return $log_info;
    }
    
    // -----
    // Formats the order's current order-totals for inclusion in a log.
    //
    public function eoFormatOrderTotalsForLog()
    {
        $log_info = PHP_EOL . 'Order Totals' . PHP_EOL;
        foreach ($GLOBALS['order']->totals as $current_total) {
            $log_info .= "\t\t" . $current_total['class'] . '. Text: ' . $current_total['text'] . ', Value: ' . ((isset($current_total['value'])) ? $current_total['value'] : '(not set)') . PHP_EOL;
        }
        return $log_info;
    }
    
    // -----
    // Returns a boolean value, identifying whether (true) or not the order contains
    // only virtual products, i.e. no shipping is required.
    //
    public function eoOrderIsVirtual($order)
    {
        $virtual_products = 0;
        foreach ($order->products as $current_product) {
            $products_id = (int)$current_product['id'];
            $virtual_check = $GLOBALS['db']->Execute(
                "SELECT products_virtual, products_model 
                   FROM " . TABLE_PRODUCTS . " 
                  WHERE products_id = $products_id 
                  LIMIT 1"
            );
            if (!$virtual_check->EOF) {
                if ($virtual_check->fields['products_virtual'] == 1 || strpos($virtual_check->fields['products_model'], 'GIFT') === 0) {
                    $virtual_products++;
                } elseif (isset($current_product['attributes'])) {
                    foreach ($current_product['attributes'] as $current_attribute) {
                        $download_check = $GLOBALS['db']->Execute(
                            "SELECT pa.products_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                    INNER JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                        ON pad.products_attributes_id = pa.products_attributes_id
                              WHERE pa.products_id = $products_id
                                AND pa.options_values_id = " . (int)$current_attribute['value_id'] . "
                                AND pa.options_id = " . (int)$current_attribute['option_id'] . "
                              LIMIT 1"
                        );
                        if (!$download_check->EOF) {
                            $virtual_products++;
                            break;  //-Out of foreach attributes loop
                        }
                    }
                }
            }
        }
        
        $product_count = count($order->products);
        return ($virtual_products == $product_count);
    }
    
    // -----
    // Returns the currency-rounded version of the supplied value.
    //
    public function eoRoundCurrencyValue($value)
    {
        return $GLOBALS['currencies']->value($value, false, $this->currency, $this->currency_value);
    }
    
    // -----
    // Returns the currency-formatted (i.e. string) version for the supplied value.
    //
    public function eoFormatCurrencyValue($value)
    {
        return $GLOBALS['currencies']->format($this->eoRoundCurrencyValue($value), true, $this->currency, $this->currency_value);
    }
    
    // -----
    // This class method mimics the zen_get_products_stock function, present in /includes/functions/functions_lookups.php.
    //
    public function eoGetProductsStock($products_id)
    {
        $stock_handled = false;
        $stock_quantity = 0;
        $this->notify('NOTIFY_EO_GET_PRODUCTS_STOCK', $products_id, $stock_quantity, $stock_handled);
        if (!$stock_handled) {
            $check = $GLOBALS['db']->Execute(
                "SELECT products_quantity
                   FROM " . TABLE_PRODUCTS . "
                  WHERE products_id = " . (int)zen_get_prid($products_id) . "
                  LIMIT 1",
                false,
                false,
                0,
                true
            );
            $stock_quantity = ($check->EOF) ? 0 : $check->fields['products_quantity'];
        }
        return $stock_quantity;
    }
    
    // -----
    // Called on completion of the current EO processing to remove any order-related variables
    // created in the session.
    //
    public function eoSessionCleanup()
    {
        $variables = array(
            'cc_id',
            'cot_gv',
            'cot_voucher',
            'currency',
            'shipping',
            'payment',
            'customer_id',
            'customer_country_id',
            'customer_zone_id',
            'cart',
        );
        foreach ($variables as $varname) {
            unset($_SESSION[$varname]);
        }
    }
    
    public function eoGetOrdersProducts()
    {
        if (!isset($this->ordersProducts)) {
            trigger_error("eoGetOrdersProducts, sequencing error; the ordersProducts array is not set.", E_USER_ERROR);
            exit();
        }
        return $this->ordersProducts;
    }
    
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    // Protected "helper" methods.
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    
    protected function zenDbPerform($table, $data, $action = 'insert', $parameters = '') 
    {
        if ($this->update_database) {
            $type = 'Updated';
            zen_db_perform($table, $data, $action, $parameters);
        } else {
            $type = 'Review';
        }
        $this->eoLog("$type: zenDbPerform($table, , $action, $parameters), data: " . PHP_EOL . $this->eoFormatDataForLog($data));
    }
    
    protected function dbDelete($table, $where_clause)
    {
       if ($this->update_database) {
            $type = 'Updated';
            $GLOBALS['db']->Execute("DELETE FROM $table WHERE $where_clause");
        } else {
            $type = 'Review';
        }
        $this->eoLog("$type: dbDelete($table, $where_clause)");
    }
    
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    // Publicly-available methods to create and initialize the order
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
   
    // -----
    // Create the global order-object for display, no modifications to the
    // order's base information.  This form of order-initialization is used
    // when presenting the order for display.
    //   
    public function eoGetOrderInfoForDisplay()
    {
 
        return $this->getOrderInfo(false);
    }
    
    // -----
    // Create the global order-object as the first step of the update (or product-add)
    // process.  This call also initializes the order's "base" record for the product
    // and totals processing.
    //       
    public function eoGetOrderInfoForUpdate()
    {
        $order = $this->getOrderInfo(true);
        
        // -----
        // Unlike the previous version of EO, the order's totals are **unconditionally** reset prior
        // to any product/totals updates.  They'll be re-generated when/if the products and
        // totals are updated.
        //
        $this->initializeOrdersTotals($order);
        
        return $order;
    }
    
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    // ... and their protected "support" methods ...
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    
    protected function getOrderInfo($is_update)
    {
        // -----
        // Note: The order-object is declared global, allowing the various functions to
        // have access to the just-created information.
        //
        global $order;
        $oID = $this->orders_id;
        
        // -----
        // Initialize the array that will register any changes to this order.
        //
        $this->ordersEdits = array();

        // -----
        // Retrieve the formatted order.
        //
        $order = new order($oID);
        $this->eoLog('getOrderInfo, on entry:' .  $this->eoFormatTaxInfoForLog(true), 'tax');
        if ($is_update) {
            $this->eoLog('Posted information: ' . $this->eoFormatDataForLog($_POST));
        }
        
        // -----
        // Initialize the country and zone_id associated with this order's purchase. Values are
        // set into the session and into globally-available variables for order-totals' use.
        //
        $this->initializeOrdersCustomerLocation($order);

        // -----
        // Cleanup tax_groups in the order (broken code in order.php)
        // Shipping module will automatically add tax if needed.
        //
        $this->initializeOrdersTaxGroups();
        
        // -----
        // Creates the class variable ordersProducts from the products **currently** in the order.  That processing
        // also determines the orders_products_id values associated with each item in the order.
        //
        $this->initializeOrdersProducts();

        // -----
        // Adjust the addresses in the order to ensure that they're the same format as that
        // used on the storefront.
        //
        $this->adjustOrdersAddresses();
        
        // -----
        // Some order-totals (notably ot_cod_fee) rely on the payment-module code being present in the session ...
        //
        $_SESSION['payment'] = $order->info['payment_module_code'];
        
        // -----
        // Initialize the order's subtotal if not already set; referenced by various shipping modules.
        //
        if (!isset($order->info['subtotal'])) {
            $order->info['subtotal'] = 0;
            foreach ($order->totals as $current_total) {
                if ($current_total['class'] == 'ot_subtotal') {
                    $order->info['subtotal'] = $current_total['value'];
                    break;
                }
            }
        }
        
        // -----
        // Handle shipping costs (module will automatically handle tax)
        //
        $order->info['shipping_cost'] = 0;
        $order->info['shipping_tax'] = 0;
        foreach ($order->totals as $current_total) {
            if ($current_total['class'] == 'ot_shipping') {
                $order->info['shipping_cost'] = $current_total['value'];

                $_SESSION['shipping'] = array(
                    'title' => $order->info['shipping_method'],
                    'id' => $order->info['shipping_module_code'] . '_',
                    'cost' => $order->info['shipping_cost']
                );
                
                eo_shopping_cart();

                if (!class_exists('shipping')) {
                    require DIR_FS_CATALOG . DIR_WS_CLASSES . 'shipping.php';
                }
                $shipping_modules = new shipping($_SESSION['shipping']);
                
                // -----
                // Determine whether the order's shipping-method is taxed and
                // initialize the order's 'shipping_tax' value.
                //
                // Note that if the store displays prices with tax, that this value is
                // included in the order's shipping-cost.
                //
                $order->info['shipping_tax'] = $this->calculateOrderShippingTax($order->info['shipping_module_code'], $order->info['shipping_cost']);
                break;
            }
        }
        
        // -----
        // Determine which portion (if any) of the shipping-cost is associated with the shipping tax, removing that
        // value from the stored shipping-cost and accumulated tax to "present" the order to the various
        // order-total modules in the manner that's done on the storefront.
        //
        $shipping_module = $order->info['shipping_module_code'];
        $this->removeTaxFromShippingCost($order, $shipping_module);
        
        $this->eoLog('getOrderInfo, on exit:' . PHP_EOL . $this->eoFormatDataForLog($GLOBALS[$shipping_module]) . $this->eoFormatTaxInfoForLog(), 'tax');
        return $order;
    }
    
    // -----
    // During order initialization, determine and set (into global and session variables) the customer's country/zone information.
    //
    // Later versions of Zen Cart's zen_get_tax_rate (on the admin-side anyway) now expect the customer's countries_id and
    // zone_id to be in globally-available variables while earlier versions expect the values to be in session variables.
    //
    protected function initializeOrdersCustomerLocation($order)
    {
        $_SESSION['customer_id'] = $order->customer['id'];
        if (STORE_PRODUCT_TAX_BASIS == 'Store') {
            $GLOBALS['customer_country_id'] = STORE_COUNTRY;
            $GLOBALS['customer_zone_id'] = STORE_ZONE;
        } else {
            if (STORE_PRODUCT_TAX_BASIS == 'Shipping') {
                if ($this->eoOrderIsVirtual($order)) {
                    if (is_array($order->billing['country'])) {
                        $GLOBALS['customer_country_id'] = $order->billing['country']['id'];
                    } else {
                        $GLOBALS['customer_country_id'] = zen_get_country_id($order->billing['country']);
                    }
                    $GLOBALS['customer_zone_id'] = zen_get_zone_id($GLOBALS['customer_country_id'], $order->billing['state']);
                } else {
                    if (is_array($order->delivery['country'])) {
                        $GLOBALS['customer_country_id'] = $order->delivery['country']['id'];
                    } else {
                        $GLOBALS['customer_country_id'] = zen_get_country_id($order->delivery['country']);
                    }
                    $GLOBALS['customer_zone_id'] = zen_get_zone_id($GLOBALS['customer_country_id'], $order->delivery['state']);
                }
            } elseif (STORE_PRODUCT_TAX_BASIS == 'Billing') {
                if (is_array($order->billing['country'])) {
                    $GLOBALS['customer_country_id'] = $order->billing['country']['id'];
                } else {
                    $GLOBALS['customer_country_id'] = zen_get_country_id($order->billing['country']);
                }
                $GLOBALS['customer_zone_id'] = zen_get_zone_id($GLOBALS['customer_country_id'], $order->billing['state']);
            }
        }
        $_SESSION['customer_country_id'] = $GLOBALS['customer_country_id'];
        $_SESSION['customer_zone_id'] = $GLOBALS['customer_zone_id'];
    }
    
    protected function initializeOrdersTaxGroups()
    {
        $GLOBALS['order']->info['tax_groups'] = array();
        
        foreach ($GLOBALS['order']->products as &$current_product) {
            if ($current_product['tax'] != 0) {
                $query = $GLOBALS['db']->Execute(
                    "SELECT products_tax_class_id 
                       FROM " . TABLE_PRODUCTS . "
                      WHERE products_id = {$current_product['id']}
                      LIMIT 1"
                );
                if (!$query->EOF) {
                    $products_tax_description = zen_get_tax_description($query->fields['products_tax_class_id']);
                } else {
                    $products_tax_description = TEXT_UNKNOWN_TAX_RATE . ' (' . zen_display_tax_value($current_product['tax']) . '%)';
                }
                $current_product['tax_description'] = $products_tax_description;
                $GLOBALS['order']->info['tax_groups'][$products_tax_description] = 0;
            }
        }
    }
    
    protected function initializeOrdersProducts()
    {
        $oID = $this->orders_id;
        
        // -----
        // Start by copying the products **currently** in the order.
        //
        $this->ordersProducts = $GLOBALS['order']->products;
        
        // -----
        // We'll add a couple of flags and variables to each product to be used during a product add/update operation:
        //
        // - (bool)is_present ........ The product is present in the shop, i.e. hasn't been deleted.
        // - (bool)removed ........... Identifies whether the product was removed from the order, set by follow-on processing.
        // - (bool)attr_missing ...... Indicates whether one or more of the ordered attributes are no longer available.
        // - (bool)options_changed ... Indicates whether the  product's options have changed; if so, a new orders_products record will be created.
        // - (int)status ............. The product's current status (enabled/disabled).
        // - (int)orders_products_id . The orders_products_id associated with this product in the order.
        // - (mixed)uprid ............ The product's unique product-id, accounting for any associated attributes
        // - (array)updates .......... Set by the product update/add processing to identify any changes to the associated product.
        // - (bool)recalc ............ Used by the product update processing to identify that an order recalculation is needed.
        // - (array)messages ......... Identifies any messages to be displayed when a product_update is completed.
        // - (array)status_messages .. Identifies any messages to be added to the order's order-status-history upon an update.
        //
        foreach ($this->ordersProducts as &$current_product) {
            $current_product['is_present'] = true;
            $current_product['removed'] = false;
            $current_product['attr_missing'] = false;
            $current_product['options_changed'] = false;
            $current_product['messages'] = array();
            $current_product['status_messages'] = array();
            $current_product['updates'] = array();
            $current_product['recalc'] = false;
            
            $pID = (int)$current_product['id'];
            
            $check = $GLOBALS['db']->Execute(
                "SELECT p.products_status
                   FROM " . TABLE_PRODUCTS . " AS p
                  WHERE p.products_id = $pID
                  LIMIT 1"
            );
            if ($check->EOF) {
                $current_product['is_present'] = false;
            } else {
                $current_product['status'] = $check->fields['products_status'];
            
                if (!isset($current_product['attributes'])) {
                    $id_check = $GLOBALS['db']->Execute(
                        "SELECT orders_products_id
                           FROM " . TABLE_ORDERS_PRODUCTS . "
                          WHERE orders_id = $oID
                            AND products_id = $pID
                          LIMIT 1"
                    );
                    $current_product['orders_products_id'] = ($id_check->EOF) ? 0 : $id_check->fields['orders_products_id'];
                    $current_product['uprid'] = $pID;
                } else {
                    $attributes = array();
                    $attributes_values = array();
                    foreach ($current_product['attributes'] as $current_attribute) {
                        $attr_check = $GLOBALS['db']->Execute(
                            "SELECT products_attributes_id
                               FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                              WHERE products_id = $pID
                                AND options_id = {$current_attribute['option_id']}
                                AND options_values_id = {$current_attribute['value_id']}
                              LIMIT 1"
                        );
                        if ($attr_check->EOF) {
                            $current_product['attr_missing'] = true;
                            $current_product['messages'][] = $current_attribute['option'] . ': ' . $current_attribute['value'];
                        }
                        $attributes[$current_attribute['option_id']] = $current_attribute['value_id'];
                        $attributes_values[$current_attribute['option_id']] = $current_attribute['value'];
                    }
                    $current_product['uprid'] = $this->getUprid($pID, $attributes, $attributes_values);
                    $current_product['orders_products_id'] = $this->findAttributedOrdersProductsId($oID, $pID, $current_product['uprid']);
                }
            }
        }
        unset($current_product);
        $this->eoLog("initializeOrdersProducts($oID), ordersProducts: " . $this->eoFormatDataForLog($this->ordersProducts));
        
        return $this->ordersProducts;
    }
    
    // -----
    // During order initialization, adjust the addresses in the order to be the same format
    // as that used on the storefront.
    //
    protected function adjustOrdersAddresses()
    {
        if (isset($GLOBALS['order']->customer['country'])) {
            $country = $this->getCountry($GLOBALS['order']->customer['country']);
            if ($country !== null) {
                $GLOBALS['order']->customer['country'] = $country;
                $GLOBALS['order']->customer['country_id'] = $country['id'];
                $GLOBALS['order']->customer['zone_id'] = zen_get_zone_id($GLOBALS['order']->customer['country']['id'], $GLOBALS['order']->customer['state']);
            }
        }
        if (is_array($GLOBALS['order']->delivery) && isset($GLOBALS['order']->delivery['country'])) { //-20150811-lat9-Add is_array since virtual products don't have a delivery address
            $country = $this->getCountry($GLOBALS['order']->delivery['country']);
            if ($country !== null) {
                $GLOBALS['order']->delivery['country'] = $country;
                $GLOBALS['order']->delivery['country_id'] = $country['id'];
                $GLOBALS['order']->delivery['zone_id'] = zen_get_zone_id($GLOBALS['order']->delivery['country']['id'], $GLOBALS['order']->delivery['state']);
            }
        }
        if (isset($GLOBALS['order']->billing['country'])) {
            $country = $this->getCountry($GLOBALS['order']->billing['country']);
            if ($country !== null) {
                $GLOBALS['order']->billing['country'] = $country;
                $GLOBALS['order']->billing['country_id'] = $country['id'];
                $GLOBALS['order']->billing['zone_id'] = zen_get_zone_id($GLOBALS['order']->billing['country']['id'], $GLOBALS['order']->billing['state']);
            }
        }
    }
    
    // -----
    // For prior versions of EO, this processing was provided by the 'eo_get_country' function.
    //
    // Retrieves the country_id, name, iso_code_2 and iso_code_3 for the country currently identified
    // in an order's address.  That country value
    //
    // Returns an array of values if the country is found; null otherwise.
    //
    protected function getCountry($country)
    {
        $retval = null;
        if ($country !== null && !is_array($country)) {
            $prepared_country = $GLOBALS['db']->prepare_input($country);
            $check = $GLOBALS['db']->Execute(
                "SELECT countries_id AS `id`, countries_name AS `name`, countries_iso_code_2 AS iso_code_2, countries_iso_code_3 AS iso_code_3
                   FROM " . TABLE_COUNTRIES . "
                  WHERE countries_name = '$prepared_country'
                     OR countries_id = " . (int)$country . "
                     OR countries_iso_code_2 = '$prepared_country'
                     OR countries_iso_code_3 = '$prepared_country'
                  LIMIT 1"
            );
            if (!$check->EOF) {
                $retval = $check->fields;
            }
        }
        return $retval;
    }
     
    protected function calculateOrderShippingTax($shipping_module, $shipping_cost)
    {
        $shipping_tax = 0;
        
        if (!(isset($GLOBALS[$shipping_module]) && isset($GLOBALS[$shipping_module]->tax_class))) {
            $this->eoLog("calculateOrderShippingTax, $shipping_module does not provide tax-information.");
        } else {
            $shipping_tax_class = $GLOBALS[$shipping_module]->tax_class;
            $shipping_tax_basis = $GLOBALS[$shipping_module]->tax_basis;

            $tax_location = zen_get_tax_locations();
            $tax_rate = zen_get_tax_rate($shipping_tax_class, $tax_location['country_id'], $tax_location['zone_id']);
            if ($tax_rate != 0) {
                $shipping_tax = $this->eoRoundCurrencyValue(zen_calculate_tax($shipping_cost, $tax_rate));
                $shipping_tax_description = zen_get_tax_description($shipping_tax_class);
                $GLOBALS['order']->info['tax_groups'][$shipping_tax_description] = $shipping_tax;
            }
        }
        
        $this->eoLog("calculateOrderShippingTax($shipping_module, $shipping_cost), returning $shipping_tax.");
        return $shipping_tax;
    }
    
    // -----
    // Notes:
    //
    // 1) Side-effect: Sets the tax-related description(s) into the product (and order)
    //    tax_groups array.
    //
    protected function getProductTaxes($opi)
    {
        global $order;

        $qty = $GLOBALS['order']->products[$opi]['qty'];
        $tax = $GLOBALS['order']->products[$opi]['tax'];
        $final_price = $GLOBALS['order']->products[$opi]['final_price'];
        $onetime_charges = $GLOBALS['order']->products[$opi]['onetime_charges'];
        
        $shown_price = $this->eoRoundCurrencyValue($final_price * $qty);
        $onetime_charges = $this->eoRoundCurrencyValue($onetime_charges);
        if (DISPLAY_PRICE_WITH_TAX == 'true') {
            $shown_price += $this->eoRoundCurrencyValue(zen_calculate_tax($shown_price, $tax));
            $onetime_charges += $this->eoRoundCurrencyValue(zen_calculate_tax($onetime_charges, $tax));
        }
        $shown_price += $onetime_charges;

        $query = false;
        if (!$this->price_calc_auto) {
            
        } else {
            if (isset($GLOBALS['order']->products[$opi]['tax_description'])) {
                $products_tax_description = $GLOBALS['order']->products[$opi]['tax_description'];
            } else {
                $query = $GLOBALS['db']->Execute(
                    "SELECT products_tax_class_id 
                       FROM " . TABLE_PRODUCTS . "
                      WHERE products_id = {$GLOBALS['order']->products[$opi]['id']}
                      LIMIT 1"
                );
                if (!$query->EOF) {
                    $products_tax_description = zen_get_tax_description($query->fields['products_tax_class_id']);
                } else {
                    $products_tax_description = TEXT_UNKNOWN_TAX_RATE . ' (' . zen_display_tax_value($tax) . '%)';
                }
            }
        }
        
        $totalTaxAdd = 0;
        if (zen_not_null($products_tax_description)) {
            $taxAdd = 0;
            if (DISPLAY_PRICE_WITH_TAX == 'true') {
                $taxAdd = $shown_price - ($shown_price / (1 + ($tax / 100)));
            } else {
                $taxAdd = zen_calculate_tax($shown_price, $tax);
            }
            $taxAdd = $this->eoRoundCurrencyValue($taxAdd);
            if (isset($order->info['tax_groups'][$products_tax_description])) {
                $order->info['tax_groups'][$products_tax_description] += $taxAdd;
            } else {
                $order->info['tax_groups'][$products_tax_description] = $taxAdd;
            }
            $totalTaxAdd += $taxAdd;
            unset($taxAdd);
        }
        $this->eoLog("getProductTaxes, returning $totalTaxAdd." . PHP_EOL);
        return $totalTaxAdd;
    }
    
    // -----
    // When a store "Displays Prices with Tax" and shipping is taxed, the shipping-cost recorded in the order includes
    // the shipping tax.  This function, called when an EO order is created, backs that tax quantity out of the shipping
    // cost since the order-totals processing will re-calculate that value.
    //
    public function removeTaxFromShippingCost(&$order, $module)
    {
        if (DISPLAY_PRICE_WITH_TAX == 'true' && isset($GLOBALS[$module]) && isset($GLOBALS[$module]->tax_class) && $GLOBALS[$module]->tax_class > 0) {
            $tax_class = $GLOBALS[$module]->tax_class;
            $tax_basis = isset($GLOBALS[$module]->tax_basis) ? $GLOBALS[$module]->tax_basis : STORE_SHIPPING_TAX_BASIS;
            
            $country_id = false;
            switch ($tax_basis) {
                case 'Billing':
                    $country_id = $order->billing['country']['id'];
                    $zone_id = $order->billing['zone_id'];
                    break;
                case 'Shipping':
                    $country_id = $order->delivery['country']['id'];
                    $zone_id = $order->delivery['zone_id'];
                    break;
                default:
                    if (STORE_ZONE == $order->billing['zone_id']) {
                        $country_id = $order->billing['country']['id'];
                        $zone_id = $order->billing['zone_id'];
                    } elseif (STORE_ZONE == $order->delivery['zone_id']) {
                        $country_id = $order->delivery['country']['id'];
                        $zone_id = $order->delivery['zone_id'];
                    }
                    break;
            }
            if ($country_id !== false) {
                $tax_rate = 1 + (zen_get_tax_rate($tax_class, $country_id, $zone_id) / 100);
                $shipping_cost = $order->info['shipping_cost'];
                $shipping_cost_ex = $this->eoRoundCurrencyValue($order->info['shipping_cost'] / $tax_rate);
                $shipping_tax = $this->eoRoundCurrencyValue($shipping_cost - $shipping_cost_ex);
                $order->info['shipping_cost'] = $shipping_cost - $shipping_tax;
                $order->info['tax'] -= $shipping_tax;
                $order->info['shipping_tax'] = 0;
             
                $this->eoLog("removeTaxFromShippingCost(order, $module), $tax_class, $tax_basis, $tax_rate, $shipping_cost, $shipping_cost_ex, $shipping_tax", 'tax');
            }
        }
    }
    
    protected function initializeOrdersTotals($order)
    {
        // -----
        // Capture the order's current info-block and totals for an after-update comparison.
        //
        $this->ordersInfo = $order->info;
        
        // -----
        // The order-totals are created as an associative array (keyed on a total's class) of
        // numeric arrays.  This is done to accommodate the ot_tax handling, where multiple
        // tax-records might be present.
        //
        $this->ordersTotals = array();
        foreach ($order->totals as $current_total) {
            $ot_class = $current_total['class'];
            if (!isset($this->ordersTotals[$ot_class])) {
                $this->ordersTotals[$ot_class] = array();
            }
            $this->ordersTotals[$ot_class][] = $current_total;
        }
        
        // -----
        // Initialize the order's total-related information, prior to product and
        // totals updates.
        //
        $order->info['tax'] = 0;
        $order->info['subtotal'] = 0;
        $order->info['shipping_tax'] = 0;
        $order->info['shipping_cost'] = 0;
        $order->info['total'] = 0;
        $order->info['tax_groups'] = array();
        $order->totals = array();
    }
    
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    // Publicly-available method to update the order's base information, e.g. addresses.
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    
    // -----
    // This function updates the information in the base order-record, including the customer,
    // billing and shipping addresses, customer-contact information and the payment method
    // information.
    //
    public function eoUpdateOrdersInfo()
    {
        // -----
        // The globally-available order is used, and declared "global" for possible use by any watching observers.
        //
        global $order;
        
        $oID = $this->orders_id;
        
        $sql_data_array = $this->checkAddressUpdated($order->customer, 'customer', 'customers');
        $sql_data_array = array_merge($sql_data_array, $this->checkAddressUpdated($order->billing, 'billing'));
        $sql_data_array = array_merge($sql_data_array, $this->checkAddressUpdated($order->delivery, 'delivery'));
        
        $sql_data_array = array_merge($sql_data_array, $this->checkPaymentInfoUpdated($order->info));
    
        // -----
        // If anything has changed in the order, let any watching observer "know" using the base
        // zco_notifier so that internal class information isn't exposed.
        //
        $order_updated = false;
        if (count($sql_data_array) != 0) {
            // -----
            // Give any listening observer the opportunity to make modifications to the SQL data associated
            // with the updated order.
            //
            $GLOBALS['zco_notifier']->notify('EDIT_ORDERS_PRE_UPDATE_ORDER', $oID, $sql_data_array);
            
            // -----
            // Update the addresses in the order and indicate that the order has been updated.
            //
            $order_updated = true;
            $sql_data_array['last_modified'] = 'now()';
            $this->zenDbPerform(TABLE_ORDERS, $sql_data_array, 'update', "orders_id = $oID LIMIT 1");
        }
        $this->eoLog('eoUpdateOrdersInfo, on exit:' . $this->eoFormatDataForLog($sql_data_array) . PHP_EOL . $this->eoFormatDataForLog($this->ordersEdits));
        return $order_updated;
    }
    
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    // ... and its protected "support" methods ...
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    
    // -----
    // This function checks a specific address-block element to see if anything has **changed**.
    //
    protected function checkAddressUpdated($current_address, $post_name, $db_name = '')
    {
        if ($db_name == '') {
            $db_name = $post_name;
        }
        $sql_data_array = array();
        foreach ($current_address as $key => $value) {
            $varname = "update_{$post_name}_$key";
            $db_fieldname = ($key == 'format_id') ? "{$db_name}_address_format_id" : "{$db_name}_$key";

            // -----
            // Since a "country" is returned as an 'id', but stored in the database as its 'name',
            // change the posted value to the country's name for database recording.  
            //
            // Note that the country might have been removed from the store's database.  In this case,
            // the name returned will be 'empty' and the original country name in the order is used.
            //
            if ($key == 'country' && isset($_POST[$varname])) {
                $_POST[$varname] = zen_get_country_name((int)$_POST[$varname]);
                $value = $value['name'];
                if (empty($_POST[$varname])) {
                    $_POST[$varname] = $value;
                }
            }
            
            if (isset($_POST[$varname]) && $_POST[$varname] != $value) {
                $sql_data_array[$db_fieldname] = $_POST[$varname];
                $GLOBALS['order']->$post_name[$key] = $_POST[$varname];
                if ($key == 'country') {
                    $country = $GLOBALS['db']->Execute(
                        "SELECT address_format_id
                           FROM " . TABLE_COUNTRIES . "
                          WHERE countries_name = '{$_POST[$varname]}'
                          LIMIT 1"
                    );
                    if (!$country->EOF) {
                        $sql_data_array[$db_name . '_address_format_id'] = $country->fields['address_format_id'];
                    }
                }
                $this->ordersEdits[] = array(
                    'type' => 'address',
                    'name' => $db_fieldname,
                    'old_value' => $value
                );
            }
        }
        return $sql_data_array;
    }
    
    protected function checkPaymentInfoUpdated($order_info)
    {
        $fields_to_check = array(
            'payment_method',
            'cc_type',
            'cc_owner',
            'cc_expires',
            'cc_number'
        );
        $sql_data_array = array();
        foreach ($fields_to_check as $current_field) {
            $varname = "update_info_$current_field";
            $value = (isset($_POST[$varname])) ? $_POST[$varname] : '';
            
            // -----
            // For PA-DSS Compliance, the credit-card number is not fully stored
            // the database. While inconvenient, this saves us in the event of an audit.
            //
            // If the card-number is not already obfuscated (i.e. it's all numbers), use the
            // same method as the authorize.net module to hide some of that information.
            //
            if ($current_field == 'cc_number' && $value != '' && is_numeric($value)) {
                $value = str_pad(substr($value, -4), strlen($value), "X", STR_PAD_LEFT);
                unset($_POST['update_info_cc_number']);
            }
            
            // -----
            // If the field's posted value is different than that currently stored in the order,
            // add that field and its value to the to-be-written SQL data and note the change
            // in the class variable.
            //
            if ($order_info[$current_field] != $value) {
                $sql_data_array[$current_field] = $_POST[$varname];
                $GLOBALS['order']->info[$current_field] = $_POST[$varname];
                $this->ordersEdits[] = array(
                    'type' => 'info',
                    'name' => $current_field,
                    'old_value' => $value
                );
            }
        }
        return $sql_data_array;
    }
    
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    // Publicly-available methods to update/add products in the order
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    
    public function eoUpdateProductsInOrder()
    {
        if (!isset($_POST['update_products']) || !is_array($_POST['update_products'])) {
            trigger_error("updateProductsInOrder, 'update_products' not set or not an array.", E_USER_ERROR);
            exit();
        }
        
        $oID = $this->orders_id;
        
        // -----
        // Record the requested products and those currently in the order.
        //
        $_POST['update_products'] = zen_db_prepare_input($_POST['update_products']);
        $this->eoLog (
            PHP_EOL . 'Requested Products:' . PHP_EOL . $this->eoFormatDataForLog($_POST['update_products']) . PHP_EOL .
            'Products in Original Order: ' . PHP_EOL . $this->eoFormatDataForLog($GLOBALS['order']->products)
        );

        // -----
        // Cycle through each of the posted products to see if anything has changed.  The form, created
        // by the base admin/edit_orders.php processing, gathers the following information for each update_products
        // array element:
        //
        // - qty ............. The updated quantity
        // - name ............ The product's name
        // - onetime_charges . The base onetime-charge for the product
        // - model ........... The product's model
        // - tax ............. The tax rate (0-100) to apply to the product's price
        // - final_price ..... The final (including any attribute additions) price of the product.
        // - attr ............ A numerically indexed array of associative arrays, keyed by the option-id:
        //      - value ......... The value.  Depending on the 'type' can be either an options_values_id or text
        //      - type .......... The option type
        //
        $order_updated = false;
        $products_updated = false;
        foreach ($_POST['update_products'] as $orders_products_id => $product_update) {
            if ($this->updateProduct($orders_products_id, $product_update)) {
                $order_updated = true;
                $products_updated = true;
            }
        }
        
        // -----
        // If one or more products were just updated (including removal) ...
        //
        if ($products_updated) {
            // -----
            // Loop through the updated products for the order, updating any form-entries to the
            // in-memory order-class instance (for use by the order-totals' processing) and the database.
            //
            // Notes:
            //
            // 1) If a product was removed from the order, it's been removed from the database,
            //    $orders->products and $this->ordersProducts by the 'updateProduct' method's processing.
            // 2) If a product's options were changed, the previous product-variant has been removed
            //    from the order and the updated variant added to $orders->products.
            //
            foreach ($this->ordersProducts as $opi => $product_fields) {
                if (count($product_fields['updates']) != 0) {
                    $updates = array(
                        'name' => $product_fields['name'],
                        'model' => $product_fields['model'],
                        'qty' => $product_fields['qty'],
                        'final_price' => $product_fields['final_price'],
                        'onetime_charges' => $product_fields['onetime_charges'],
                        'tax' => $product_fields['tax']
                    );
                    $GLOBALS['order']->products[$opi] = array_merge($GLOBALS['order']->products[$opi], $updates);
                    
                    $sql_data_array = array(
                        'products_name' => $product_fields['name'],
                        'products_model' => $product_fields['model'],
                        'products_quantity' => $product_fields['qty'],
                        'final_price' => $product_fields['final_price'],
                        'products_tax' => $product_fields['tax'],
                        'onetime_charges' => $product_fields['onetime_charges']
                    );
                    $this->zenDbPerform(TABLE_ORDERS_PRODUCTS, $sql_data_array, 'update', "orders_products_id = {$product_fields['orders_products_id']} LIMIT 1");
                }
            }
            
            // -----
            // Re-index the order's products array ... just in case a product was removed ... to ensure 
            // that the array has sequentially-numbered indices.  This is done for the next order-update
            // step ... order-totals processing ... where order-totals might expect that sequential
            // ordering.
            // 
            // NOTE: This re-indexing is performed **after** the updated products have been recorded
            // in the database and in-memory copy of the order, since this class' ordersProducts array
            // contains a reference (and index) back to the original $orders->products layout!
            //
            $products_array = array();
            foreach ($GLOBALS['order']->products as $index => $order_info) {
                $products_array[] = $order_info;
            }
            $GLOBALS['order']->products = $products_array;
        }
        return $order_updated;
    }
    
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    // ... and their protected "support" methods ...
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
        
    protected function updateProduct($orders_products_id, $product_update)
    {
        // -----
        // "Sanitize" floating-point values.
        //
        $product_update['qty'] = (float)$product_update['qty'];
        $product_update['onetime_charges'] = (isset($product_update['onetime_charges'])) ? (float)$product_update['onetime_charges'] : 0;
        $product_update['tax'] = (float)$product_update['tax'];
        $product_update['final_price'] = (float)$product_update['final_price'];
        
        // -----
        // Initialize return value.
        //
        $product_updated = false;
        
        $opi = $this->findProductInOrder($orders_products_id);
        if ($opi === false) {
            $this->eoLog(PHP_EOL . "Could not locate orders_products_id#$orders_products_id in order. " . PHP_EOL . $this->eoFormatDataForLog($product_update));
        } else {
            $this->eoLog(PHP_EOL . "Updating orders_products_id#$orders_products_id. " . $this->eoFormatDataForLog($product_update));
            
            $updates = array();
            
            $current_product = $this->ordersProducts[$opi];
            
            if ($this->price_calc_auto && (!$current_product['is_present'] || $current_product['attr_missing'] || $current_product['status'] == 0)) {
                $this->ordersProducts[$opi]['messages'][] = "Pricing not updated; use manual";
            } else { 
                if ($product_update['name'] != $current_product['name']) {
                    $product_updated = true;
                    $GLOBALS['order']->products[$opi]['name'] = $product_update['name'];
                    $updates[] = array(
                        'name' => $current_product['name']
                    );
                }
                
                if ($product_update['model'] != $current_product['model']) {
                    $product_updated = true;
                    $GLOBALS['order']->products[$opi]['model'] = $product_update['model'];
                    $updates[] = array(
                        'model' => $current_product['model']
                    );
                }
                
                if ($this->productPricingOrOptionsChanged($opi, $product_update)) {
                    $updated_qty = $product_update['qty'];
                    if ($updated_qty < 0) {
                        $this->ordersProducts[$opi]['messages'][] = "Pricing not updated; the quantity ($updated_qty) was negative.";
                    } else {
                        if ($updated_qty == 0 || $this->ordersProducts[$opi]['options_changed']) {
                            $this->removeProductFromOrder($opi);
                        }
                        if ($updated_qty > 0) {
                            if ($this->ordersProducts[$opi]['options_changed']) {
                                $this->addProductWithChangedOptionsToOrder($opi, $product_update);
                            } else {
                                $this->updateProductQuantityPrices($opi, $product_update);
                            }
                        }
                    }
                    $product_updated = true;
                }
                $this->updateProductTaxesAndSubtotal($opi);
            }
            $this->ordersProducts[$opi]['updates'] = array_merge($this->ordersProducts[$opi]['updates'], $updates);
            $this->eoLog('product updates: ' . PHP_EOL . $this->eoFormatDataForLog($this->ordersProducts[$opi]['updates']));
        }
        return $product_updated;
    }
    
    // -----
    // For versions of EO < 5.0.0, this processing was provided by eo_remove_product_from_order.
    //
    protected function removeProductFromOrder($opi)
    {
        // -----
        // Grab the orders_products_id associated with the specified ordersProducts index.
        //
        $orders_products_id = $this->ordersProducts[$opi]['orders_products_id'];
        
        // -----
        // Give a listening observer the chance to provide customized stock-handling.  Note that
        // the notification's name is "legacy" as this processing will actually **increment** a
        // product's quantity, not decrement it!
        //
        $doStockDecrement = true;
        $this->notify(
            'EDIT_ORDERS_REMOVE_PRODUCT_STOCK_DECREMENT', 
            array(
                'order_id' => $this->orders_id, 
                'orders_products_id' => $orders_products_id
            ), 
            $doStockDecrement
        );
        if (STOCK_LIMITED == 'true' && $doStockDecrement) {
            $query = $GLOBALS['db']->Execute(
                "SELECT products_id, products_quantity
                   FROM " . TABLE_ORDERS_PRODUCTS . "
                  WHERE orders_id = {$this->orders_id}
                    AND orders_products_id = $orders_products_id
                  LIMIT 1"
            );
            
            $ordered_uprid = $query->fields['products_id'];
            $ordered_pid = (int)zen_get_prid($ordered_uprid);
            $ordered_qty = $query->fields['products_quantity'];

            if (!$query->EOF) {
                if (DOWNLOAD_ENABLED == 'true') {
                    $check = $GLOBALS['db']->Execute(
                        "SELECT p.products_quantity, p.products_ordered, p.products_status, pad.products_attributes_filename, p.product_is_always_free_shipping
                           FROM " . TABLE_PRODUCTS . " AS p
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " AS pa 
                                    ON p.products_id = pa.products_id
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " AS pad 
                                    ON pa.products_attributes_id = pad.products_attributes_id
                          WHERE p.products_id = $ordered_pid
                          LIMIT 1"
                    );
                } else {
                    $check = $GLOBALS['db']->Execute(
                        "SELECT p.products_quantity, p.products_ordered, p.products_status 
                           FROM " . TABLE_PRODUCTS . " AS p
                          WHERE p.products_id = $ordered_pid
                          LIMIT 1"
                    );
                }
                if (!$check->EOF && (DOWNLOAD_ENABLED != 'true' || $check->fields['product_is_always_free_shipping'] == 2 || !$check->fields['products_attributes_filename'])) {
                    $current_qty = $check->fields['products_quantity'];
                    
                    $sql_data_array = array(
                        'products_quantity' => $current_qty + $ordered_qty,
                        'products_ordered' => $check->fields['products_ordered'] - $ordered_qty
                    );
                    if ($sql_data_array['products_ordered'] < 0) {
                        $sql_data_array['products_ordered'] = 0;
                    }
                    if ($sql_data_array['products_quantity'] > 0) {
                        // Only set status to on when not displaying sold out
                        if (SHOW_PRODUCTS_SOLD_OUT == '0') {
                            $sql_data_array['products_status'] = 1;
                        }
                    }
                    $this->zenDbPerform(TABLE_PRODUCTS, $sql_data_array, 'update', "products_id = $ordered_pid LIMIT 1");
                }
            }
            unset($check, $query, $sql_data_array);
        }
        
        $this->notify(
            'EDIT_ORDERS_REMOVE_PRODUCT', 
            array(
                'order_id' => $this->orders_id,
                'orders_products_id' => $orders_products_id
            )
        );
        $where_clause = " orders_id = {$this->orders_id} AND orders_products_id = $orders_products_id";
        $this->dbDelete(TABLE_ORDERS_PRODUCTS, $where_clause);
        $this->dbDelete(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $where_clause);
        $this->dbDelete(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $where_clause);
 
        // -----
        // Remove the product from the order-instance, for use in the order-totals'
        // recalculation, and note within the class-based order "tracker" that this
        // product was removed from the order.
        //
        unset($GLOBALS['order']->products[$opi]);
        $this->ordersProducts[$opi]['removed'] = true;
    }
    
    protected function addProductWithChangedOptionsToOrder($opi, $product_update)
    {
    }
    
    // -----
    // For versions of EO < 5.0.0, this processing was provided by eo_add_product_to_order.
    //
    protected function addProductToOrder()
    {
    }
    
    protected function updateProductQuantityPrices($opi, $product_update)
    {
        if ($this->price_calc_auto) {
            $this->calculateProductPrice($opi, $product_update);
        } else {
            $this->useEnteredProductPrice($opi, $product_update);
        }
    }
    
    protected function updateProductTaxesAndSubtotal($opi)
    {
        if (!$this->ordersProducts[$opi]['removed']) {
            $tax = $GLOBALS['order']->products[$opi]['tax'];
            $qty = $GLOBALS['order']->products[$opi]['qty'];
            $final_price = $GLOBALS['order']->products[$opi]['final_price'];
            $onetime_charges = $GLOBALS['order']->products[$opi]['onetime_charges'];
            
            $this->eoLog("Entering updateOrderSubtotal($opi), qty = $qty, price = $final_price, onetime = $onetime_charges, tax = $tax", 'tax');
            
            $shown_price = $this->eoRoundCurrencyValue($final_price * $qty);
            $onetime_charges = $this->eoRoundCurrencyValue($onetime_charges);
            if (DISPLAY_PRICE_WITH_TAX == 'true') {
                $shown_price += $this->eoRoundCurrencyValue(zen_calculate_tax($shown_price, $tax));
                $onetime_charges += $this->eoRoundCurrencyValue(zen_calculate_tax($onetime_charges, $tax));
            }
            $shown_price += $onetime_charges;
            
            $GLOBALS['order']->info['subtotal'] += $shown_price;
            $GLOBALS['order']->info['total'] += $shown_price;
            $GLOBALS['order']->info['tax'] += $this->eoRoundCurrencyValue($this->getProductTaxes($opi));
        }
    }
    
    protected function calculateProductPrice($opi, $product_update)
    {
    }
    
    protected function useEnteredProductPrice($opi, $product_update)
    {
        $updates = array(
            'qty' => $product_update['qty'],
            'tax' => $product_update['tax'],
            'final_price' => $product_update['final_price'],
            'onetime_charges' => $product_update['onetime_charges'],
        );
        $GLOBALS['order']->products[$opi] = array_merge($GLOBALS['order']->products[$opi]);
    }
    
    protected function productPricingOrOptionsChanged($opi, $product_update)
    {
        $product_changed = false;
        $updates = array();
        $product = $this->ordersProducts[$opi];
        
        if ($this->price_calc_auto) {
            $fields_to_check = array('qty');
        } else {
            $fields_to_check = array('qty', 'tax', 'onetime_charges', 'final_price');
        }
        foreach ($fields_to_check as $field_name) {
            if ($product[$field_name] != $product_update[$field_name]) {
                $GLOBALS['order']->products[$opi][$field_name] = $product_update[$field_name];
                $updates[$field_name] = $product_update[$field_name];
                $product_changed = true;
                break;
            }
        }
        
        if (isset($product['attributes']) && isset($product_update['attr'])) {
            if ($this->productAttributesChanged($opi, $product_update)) {
                $this->ordersProducts[$opi]['options_changed'] = true;
                $product_changed = true;
            }
        }
        
        $this->ordersProducts[$opi]['updates'] = array_merge($this->ordersProducts[$opi]['updates'], $updates);
        
        $this->eoLog("productPricingOrOptionsChanged, on exit: " . $this->eoFormatDataForLog($this->ordersProducts[$opi]) . $this->eoFormatDataForLog($product_update) . $this->eoFormatDataForLog($updates));

        return $product_changed;
    }
    
    protected function productAttributesChanged($opi, $product_update)
    {
        $attributes_changed = false;
        $updates = array();
        foreach ($product_update['attr'] as $option_id => $option_value_type) {
            foreach ($this->ordersProducts[$opi]['attributes'] as $attribs_info) {
                $changed = false;
                if ($attribs_info['option_id'] == $option_id) {
                    if ($option_value_type['type'] == PRODUCTS_OPTIONS_TYPE_TEXT) {
                        if ($option_value_type['value'] != $attribs_info['value']) {
                            $changed = true;
                        }
                    } else {
                        if ($option_value_type['value'] != $attribs_info['value_id']) {
                            $changed = true;
                        }
                    }
                }
                if ($changed) {
                    $attributes_changed = true;
                    if (!isset($updates['attributes'])) {
                        $updates['attributes'] = array();
                    }
                    $updates['attributes'][$option_id] = array(
                        'option' => $attribs_info['option'],
                        'value' => $attribs_info['value']
                    );
                }
            }
        }
        
        $this->ordersProducts[$opi]['updates'] = array_merge($this->ordersProducts[$opi]['updates'], $updates);
        
        return $attributes_changed;
    }
    
    protected function findProductInOrder($orders_products_id)
    {
        $opi = false;
        for ($i = 0, $n = count($this->ordersProducts); $i < $n; $i++) {
            if ($this->ordersProducts[$i]['orders_products_id'] == $orders_products_id) {
                $opi = $i;
                break;
            }
        }
        $this->eoLog("findProductInOrder($orders_products_id), returning ($opi)");
        return $opi;
    }
    
    protected function getUprid($pID, $attributes, $attributes_values)
    {
        $parameters = array();
        foreach ($attributes as $option_id => $value_id) {
            if ($value_id != PRODUCTS_OPTIONS_VALUES_TEXT_ID) {
                $value = $value_id;
            } else {
                $value = $attributes_values[$option_id];
                $option_id = TEXT_PREFIX . $option_id;
            }
            $parameters[$option_id] = $value;
        }
        return zen_get_uprid($pID, $parameters);
    }
    
    protected function findAttributedOrdersProductsId($oID, $pID, $uprid_to_match)
    {
        $orders_products_id = 0;
        $id_check = $GLOBALS['db']->Execute(
            "SELECT orders_products_id, products_options_id AS option_id, products_options_values_id AS value_id, products_options_values AS value
               FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . "
              WHERE orders_id = $oID
                AND products_prid LIKE '$pID%'
           ORDER BY orders_products_id ASC, orders_products_attributes_id ASC"
        );
        if (!$id_check->EOF) {
            $options = array();
            $options_values = array();
            while (!$id_check->EOF) {
                $orders_products_id = $id_check->fields['orders_products_id'];
                if (!isset($options[$orders_products_id])) {
                    $options[$orders_products_id] = array();
                    $options_values[$orders_products_id] = array();
                }
                $options[$orders_products_id][$id_check->fields['option_id']] = $id_check->fields['value_id'];
                $options_values[$orders_products_id][$id_check->fields['option_id']] = $id_check->fields['value'];
                $id_check->MoveNext();
            }

            foreach ($options as $opID => $attributes) {
                if ($uprid_to_match == $this->getUprid($pID, $attributes, $options_values[$opID])) {
                    $orders_products_id = $opID;
                    break;
                }
            }
        }
        return $orders_products_id;
    }

    
    // -----------------------------
    // Attribute-related methods.  For versions of EO prior to 5.5.0, this functionality was provided
    // by a combination of procedural functions (in edit_orders_functions.php) in conjunction with the
    // attributes.php class.
    // -----------------------------
    
    public function eoGetProductOptionsAndValues($pID, $readonly = false)
    {
        $pID = (int)$pID;
        
        $options = array();
        $attributes = $this->getProductAttributeOptions($pID, $readonly);
        foreach ($attributes as $info) {
            $current_option_id = $info['id'];
            if (!isset($options[$current_option_id])) {
                $options[$current_option_id] = array(
                    'name' => $info['name'],
                    'type' => $info['type'],
                    'length' => $info['length'],
                    'size' => $info['size'],
                    'rows' => $info['rows'],
                    'options' => array(),
                );
            }
            $options[$current_option_id]['options'][$info['value_id']] = $info['value'];
        }
        
        $this->eoLog("eoGetProductOptionsAndValues($pID, $readonly), returning: " . $this->eoFormatDataForLog($options));
        return $options;
    }
    
    protected function getProductAttributeOptions($pID, $readonly = false)
    {
       $query = 
            "SELECT pa.options_id AS `id`, po.products_options_name AS `name`, po.products_options_type AS `type`, po.products_options_size AS `size`, po.products_options_rows AS `rows`, po.products_options_length AS `length`, pov.products_options_values_name as `value`, pov.products_options_values_id AS `value_id`
               FROM " . TABLE_PRODUCTS_ATTRIBUTES . " AS pa
                    LEFT JOIN " . TABLE_PRODUCTS_OPTIONS . " AS po
                        ON pa.options_id = po.products_options_id
                       AND po.language_id = " . (int)$_SESSION['languages_id'] . "
                    LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " AS pov
                        ON pa.options_values_id = pov.products_options_values_id
                       AND pov.language_id = po.language_id
              WHERE pa.products_id = $pID";
 
        // Don't include READONLY attributes if product can be added to cart without them
        if (PRODUCTS_OPTIONS_TYPE_READONLY_IGNORED == '1' && $readonly === false) {
            $query .= " AND po.products_options_type != " . (int)PRODUCTS_OPTIONS_TYPE_READONLY;
        }

        $query .= ' ORDER BY ' . $this->options_order_by . ', ' . $this->options_values_order_by;

        $options = $GLOBALS['db']->Execute($query);

        $retval = array();
        while (!$options->EOF) {
            $retval[] = $options->fields;
            $options->MoveNext();
        }
        unset($options);
        return $retval;
    }
    
    public function eoGetSelectedOptionValueId($uprid, $option_id)
    {
        $selected_value = false;
        foreach ($this->ordersProducts as $pInfo) {
            if ($pInfo['uprid'] == $uprid && isset($pInfo['attributes']) && is_array($pInfo['attributes'])) {
                foreach ($pInfo['attributes'] as $aInfo) {
                    if ($aInfo['option_id'] == $option_id) {
                        $selected_value = $aInfo['value_id'];
                        break;
                    }
                }
            }
        }
        return $selected_value;
    }
    
    public function eoIsOptionSelected($uprid, $option_id, $option_value_id)
    {
        $is_selected = false;
        foreach ($this->ordersProducts as $pInfo) {
            if ($pInfo['uprid'] == $uprid && isset($pInfo['attributes']) && is_array($pInfo['attributes'])) {
                foreach ($pInfo['attributes'] as $aInfo) {
                    if ($aInfo['option_id'] == $option_id && $aInfo['value_id'] == $option_value_id) {
                        $is_selected = true;
                        break;
                    }
                }
            }
        }
        return $is_selected;
    }
    
    public function eoGetOptionValue($uprid, $option_id)
    {
        $value = '';
        foreach ($this->ordersProducts as $pInfo) {
            if ($pInfo['uprid'] == $uprid && isset($pInfo['attributes']) && is_array($pInfo['attributes'])) {
                foreach ($pInfo['attributes'] as $aInfo) {
                    if ($aInfo['option_id'] == $option_id) {
                        $value = $aInfo['value'];
                        break;
                    }
                }
            }
        }
        return $value;
    }
    
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    // Publicly-available methods to update any changed totals in the order.
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    
    // -----------------------------
    // Order-total-related methods.  For versions of EO prior to 5.5.0, this functionality was provided
    // by a combination of procedural functions (in edit_orders_functions.php).
    // -----------------------------  
    
    public function eoUpdateTotalsInOrder()
    {
        $totals_updated = false;
        $oID = $this->orders_id;
        $this->tax_updated = false;
        
        // -----
        // Note:  Previous processing is "presumed" to have initialized the totals in the order,
        // creating the ordersInfo and ordersTotals class variables ... containing the starting
        // order's values.  If either of these variables are not set, it's a sequencing error.
        //
        if (!isset($this->ordersInfo) || !isset($this->ordersTotals)) {
            trigger_error("Sequencing error, prior call to 'initializeOrdersTotals' required for correct operation.", E_USER_ERROR);
            exit();
        }

        // -----
        // Some of the order-total values posted need recording in either the order itself or in
        // the session for "proper" order-totals' processing.
        //
        $this->setPostedTotalUpdatesInOrder();
        
        // -----
        // Continue only if at least one order-total module is installed.
        //
        if (defined('MODULE_ORDER_TOTAL_INSTALLED') && zen_not_null(MODULE_ORDER_TOTAL_INSTALLED)) {
            $this->eoLog(PHP_EOL . 'eoUpdateTotalsInOrder, taxes/totals on entry. ' . $this->eoFormatTaxInfoForLog(true) . PHP_EOL . $this->eoFormatDataForLog($this->ordersTotals), 'tax');
            
            // -----
            // Since we're running in function-scope, declare the $order instance to be global
            // to enable the order-total processing to access that information.
            //
            // Just in case an order-total module has notifications to issue, also globalize the
            // $zco_notifier.
            //
            global $order, $zco_notifier;

            // Load order totals.
            if (!class_exists('order_total')) {
                require DIR_FS_CATALOG . DIR_WS_CLASSES . 'order_total.php';
            }
            $GLOBALS['order_total_modules'] = new order_total();

            // -----
            // Load the "mock" shopping cart class into the session; some order-totals are actually
            // "cart"-totals as they depend on the shopping-cart session instance for their operation.
            //
            eo_shopping_cart();

            // -----
            // Process the order totals, updating each one in the database.
            //
            $order_totals = $GLOBALS['order_total_modules']->process();
            $GLOBALS['zco_notifier']->notify('EO_UPDATE_DATABASE_ORDER_TOTALS_MAIN', $oID);
            foreach ($order_totals as $current_total) {
                $GLOBALS['zco_notifier']->notify('EO_UPDATE_DATABASE_ORDER_TOTALS_ITEM', $oID, $current_total);
                if ($this->updateOrderTotalInDatabase($current_total)) {
                    $totals_updated = true;
                }
            }
            unset($order_totals);
            
            // -----
            // Delete from the database any order-totals that were previously in the order, but have been
            // removed.
            //
            if ($this->deleteRemovedOrderTotals()) {
                $totals_updated = true;
            }

            // -----
            // Handle a corner-case:  If the store has set Configuration->My Store->Sales Tax Display Status to '0' (no tax displayed
            // if it's 0), and the admin has removed the tax (setting the tax-percentages to 0) for this order.
            //
            // In that case, an ot_tax value doesn't get generated for this order-update but there might have previously been
            // a tax value set.  If this situation is detected, simply remove the ot_tax value from the order's stored
            // order-totals.
            //
            if (STORE_TAX_DISPLAY_STATUS == '0' && $GLOBALS['order']->info['tax'] == 0) {
                $this->dbDelete(TABLE_ORDERS_TOTAL, "orders_id = $oID AND `class` = 'ot_tax'");
            }
            
            $this->eoLog('eoUpdateTotalsInOrder, taxes on exit. ' . $this->eoFormatTaxInfoForLog(), 'tax');
        }        
        return $totals_updated;
    }
    
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    // ... and its protected "support" methods ...
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
            
    protected function setPostedTotalUpdatesInOrder()
    {
        foreach ($_POST['update_total'] as $order_total) {
            $order_total['value'] = (float)$order_total['value'];
            $order_total['text'] = $this->eoFormatCurrencyValue($order_total['value']);

            if (!empty($order_total['title']) && $order_total['title'] != ':') {
                switch ($order_total['code']) {
                    case 'ot_shipping':
                        $shipping_cost = (float)$order_total['value'];
                        $GLOBALS['order']->info['shipping_cost'] = $shipping_cost;
                        $GLOBALS['order']->info['total'] += $shipping_cost;
                        $GLOBALS['order']->info['shipping_method'] = $order_total['title'];
                        $GLOBALS['order']->info['shipping_module_code'] = $order_total['shipping_module'];
                        break;
                    case 'ot_tax':/*
                        if (count($order->products) == 0) {
                            $order_total['title'] = '';
                            $order_total['value'] = 0;
                        }
                        $order->info['tax'] = $order_total['value'];*/
                        break;
                    case 'ot_gv':
                        if ($order_total['value'] < 0) {
                            $order_total['value'] = $order_total['value'] * -1;
                        }
                        $order_total['text'] = $this->eoFormatCurrencyValue($order_total['value']);
                        $_SESSION['cot_gv'] = $order_total['value'];
                        break;
                    case 'ot_voucher':
                        if ($order_total['value'] < 0) {
                            $order_total['value'] = $order_total['value'] * -1;
                        }
                        $order_total['text'] = $this->eoFormatCurrencyValue($order_total['value']);
                        $_SESSION['cot_voucher'] = $order_total['value'];
                        break;
                    case 'ot_coupon':
                        // Default to using the title from the module
                        $coupon = rtrim($order_total['title'], ': ');
                        $order_total['title'] = $GLOBALS['ot_coupon']->title;

                        // Look for correctly formatted title
                        preg_match('/([^:]+):([^:]+)/', $coupon, $matches);
                        if (count($matches) > 2) {
                            $order_total['title'] = trim($matches[1]);
                            $coupon = $matches[2];
                        }
                        $cc_id = $db->Execute(
                            "SELECT coupon_id 
                               FROM " . TABLE_COUPONS . "
                              WHERE coupon_code = '" . trim($coupon) . "'
                              LIMIT 1"
                        );
                        if (!$cc_id->EOF) {
                            $_SESSION['cc_id'] = $cc_id->fields['coupon_id'];
                        } else {
                            $messageStack->add_session(WARNING_ORDER_COUPON_BAD, 'warning');
                            unset($_SESSION['cc_id']);
                        }
                        unset($cc_id, $matches, $coupon);
                        break;
                    default:
                        break;
                }
            }
        }
    }
    
    // -----
    // Updates or adds an order-total record in the database.
    //
    // Processing makes use of the previously-captured "copy" of the order's
    // original totals.  Each pre-existing order-total that was updated in the
    // database is noted in that class-based array.
    //
    // A call to "deleteRemovedOrderTotals" after **all** the current totals
    // are processed will remove any order-total records from the database that
    // are no longer associated with the order.
    //
    protected function updateOrderTotalInDatabase($order_total)
    {
        $oID = $this->orders_id;
        $updated = false;
        
        $this->eoLog("updateOrderTotalInDatabase: " . PHP_EOL . $this->eoFormatDataForLog($order_total));
        
        $ot_code = $order_total['code'];
        $ot_title = $order_total['title'];
        $ot_value = (is_numeric($order_total['value'])) ? $order_total['value'] : 0;
        $ot_text = $order_total['text'];
        $ot_sort = $order_total['sort_order'];

        $sql_data_array = array(
            'title' => $ot_title,
            'text' => $ot_text,
            'value' => $ot_value,
            'sort_order' => $ot_sort
        );
        
        // Update the Order Totals in the Database, recognizing that there might be multiple records for the product's tax
        $and_clause = '';
        if ($ot_code == 'ot_tax' && SHOW_SPLIT_TAX_CHECKOUT == 'true') {
            $and_clause = " AND `title` = '$ot_title'";
            $previous_value = $this->markOrderTotalProcessed($ot_code, $ot_title);
        } else {
            $previous_value = $this->markOrderTotalProcessed($ot_code);
        }

        if ($previous_value !== false) {
            if ($previous_value['value'] != $ot_value || $previous_value['title'] != $ot_title || $previous_value['text'] != $ot_text || $previous_value['sort_order'] != $ot_sort) {
                if (!empty($ot_title) && $ot_title != ':') {
                    $this->zenDbPerform(TABLE_ORDERS_TOTAL, $sql_data_array, 'update', "class='$ot_code' AND orders_id = $oID $and_clause");
                } else {
                    $this->dbDelete(TABLE_ORDERS_TOTAL, "`class` = '$ot_code' AND orders_id = $oID $and_clause");
                }
                $updated = true;
            }
        } elseif (!empty($ot_title) && $ot_title != ':') {
            $sql_data_array['orders_id'] = $oID;
            $sql_data_array['class'] = $ot_code;

            $this->zenDbPerform(TABLE_ORDERS_TOTAL, $sql_data_array);
            $updated = true;
        }
        return $updated;
    }
    
    // -----
    // Searches through the list of the order's pre-update order-totals, looking
    // to see if the specified total-class was previously recorded and capturing
    // its current record if so.
    //
    // The method returns false if the order-total class/code was not previously in
    // the order or the total's "current" values if so.
    //
    protected function markOrderTotalProcessed($ot_code, $ot_title = false)
    {
        $previous_value = false;
        if (isset($this->ordersTotals[$ot_code])) {
            if ($ot_title === false) {
                $previous_value = $this->ordersTotals[$ot_code][0];
                $this->ordersTotals[$ot_code][0]['processed'] = true;
            } else {
                for ($i = 0, $n = count($this->ordersTotals[$ot_code]); $i < $n; $i++) {
                    if ($this->ordersTotals[$ot_code][$i]['title'] == $ot_title) {
                        $previous_value = $this->ordersTotals[$ot_code][$i];
                        $this->ordersTotals[$ot_code][$i]['processed'] = true;
                        break;
                    }
                }
            }
        }
        return $previous_value;
    }
    
    protected function deleteRemovedOrderTotals()
    {
        $oID = $this->orders_id;
        foreach ($this->ordersTotals as $current_total) {
            if (count($current_total) == 1) {
                if (!isset($current_total[0]['processed'])) {
                    $this->dbDelete(TABLE_ORDERS_TOTAL, "orders_id = $oID AND `class` = '{$order_total[0]['code']}' LIMIT 1");
                }
            } else {
                foreach ($current_total as $multi_total) {
                    if (!isset($multi_total['processed'])) {
                        $this->dbDelete(TABLE_ORDERS_TOTAL, "orders_id = $oID AND `class` = '{$multi_total['code']}' AND `title` = '{$multi_total['title']}' LIMIT 1");
                    }
                }
            }
        }
    }
    
    // -----
    // Called at the very end of any orders-update processing when information
    // in the order has been updated.
    //
    // This updates the base order-record, updating the total, tax and shipping-method
    // information.  At this time, any changes to the order are gathered from class-based
    // variables and formatted into a hidden status-update to the order.
    //
    public function eoUpdateOrdersChanges()
    {
        $sql_data_array = array(
            'order_total' => $GLOBALS['order']->info['total'],
            'order_tax' => $GLOBALS['order']->info['tax'],
            'shipping_method' => $GLOBALS['order']->info['shipping_method'],
            'shipping_module_code' => $GLOBALS['order']->info['shipping_module_code'],
            'last_modified' => 'now()'
        );
        $this->zenDbPerform(TABLE_ORDERS, $sql_data_array, 'update', 'orders_id=' . $this->orders_id . ' LIMIT 1');
    }
    
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    // Publicly-available method to update the order's history and notify the customer.
    // ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
    
    public function eoUpdateOrdersHistoryNotifyCustomer()
    {
        $status = (int)$_POST['status'];
        if ($status < 1) {
            return false;
        }
        
        $order_updated = false;
        
        $oID = $this->orders_id;
        $comments = zen_db_prepare_input($_POST['comments']);
        $notify_customer = (isset($_POST['notify']) && ($_POST['notify'] == 1 || $_POST['notify'] == -1)) ? $_POST['notify'] : 0;
        $notify_include_comments = (isset($_POST['notify_comments']) && $_POST['notify_comments'] == 'on');

        // BEGIN TY TRACKER 1 - READ FROM POST
        $ty_changed = false;
        $track_id = array();
        if (defined('TY_TRACKER') && TY_TRACKER == 'True') {
            $track_id = zen_db_prepare_input($_POST['track_id']);
            foreach ($track_id as $id => $track) {
                $carrier_constant = "CARRIER_STATUS_$id";
                if (defined($carrier_constant) && constant($carrier_constant) == 'True' && zen_not_null($track)) {
                    $ty_changed = true;
                }
            }
        }
        // END TY TRACKER 1 - READ FROM POST
        
        $check_status = $GLOBALS['db']->Execute(
            "SELECT customers_name, customers_email_address, orders_status, date_purchased 
               FROM " . TABLE_ORDERS . "
              WHERE orders_id = $oID
              LIMIT 1"
        );
        
        $this->notify(
            'NOTIFY_EDIT_ORDERS_PRE_CUSTOMER_NOTIFICATION',
            array(
                'oID' => $oID,
                'old_status' => $check_status->fields['orders_status'],
                'new_status' => $status,
                'comments' => $comments,
                'notify_customer' => $notify_customer,
                'notify_comments' => $notify_include_comments,
                'order_info' => $check_status->fields
            )
        );

        if ($check_status->fields['orders_status'] != $status || $ty_changed || zen_not_null($comments)) {
            if (function_exists('zen_update_orders_history')) {
                if (zen_update_orders_history($oID, $comments, null, $status, $notify_customer, $notify_include_comments) != -1) {
                    $order_updated = true;
                }
            } else {
                $order_updated = true;
                if ($notify_customer == '1') {
                    $this->notifyCustomerOrderUpdated($status, $comments, $notify_include_comments, $track_id, $check_status->fields);
                    $this->updateOrdersStatusHistory($status, $comments, $notify_customer, $track_id);
                }
            }
        }
        return $order_updated;
    }
    
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    // ... and its protected "support" methods ...
    // -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
    
    protected function notifyCustomerOrderUpdated($status, $comments, $notify_include_comments, $track_id, $order_info)
    {
        $oID = $this->orders_id;
        $notify_comments = '';
        if ($notify_include_comments) {
            if (zen_not_null($comments)) {
                $notify_comments = EMAIL_TEXT_COMMENTS_UPDATE . $comments . PHP_EOL . PHP_EOL;
            }
            // BEGIN TY TRACKER 2 - EMAIL TRACKING INFORMATION
            if (count($track_id) != 0) {
                $notify_comments .= EMAIL_TEXT_COMMENTS_TRACKING_UPDATE . PHP_EOL . PHP_EOL;
                $comment = EMAIL_TEXT_COMMENTS_TRACKING_UPDATE;
            }
            foreach ($track_id as $id => $track) {
                $carrier_status = "CARRIER_STATUS_$id";
                $carrier_name = "CARRIER_NAME_$id";
                $carrier_link = "CARRIER_LINK_$id";
                if (zen_not_null($track) && defined($carrier_status) && constant($carrier_status) == 'True' && defined($carrier_name)) {
                    $notify_comments .= "Your " . constant($carrier_name) . " Tracking ID is " . $track . " \n<br /><a href=" . constant($carrier_link) . $track . ">Click here</a> to track your package. \n<br />If the above link does not work, copy the following URL address and paste it into your Web browser. \n<br />" . constant($carrier_link) . $track . "\n\n<br /><br />It may take up to 24 hours for the tracking information to appear on the website." . "\n<br />";
                }
            }
            // END TY TRACKER 2 - EMAIL TRACKING INFORMATION
        }
        
        //send emails
        $account_history_info_link = zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, "order_id=$oID", 'SSL');
        $date_purchased = zen_date_long($order_info['date_purchased']);
        $status_name = $this->eoGetOrdersStatusName($status);
        $order_status_label = sprintf(EMAIL_TEXT_STATUS_LABEL, $status_name);
        $message =
            STORE_NAME . ' ' . EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID . PHP_EOL . PHP_EOL .
            EMAIL_TEXT_INVOICE_URL . ' ' . $account_history_info_link . PHP_EOL . PHP_EOL .
            EMAIL_TEXT_DATE_ORDERED . ' ' . $date_purchased . PHP_EOL . PHP_EOL .
            strip_tags($notify_comments) .
            EMAIL_TEXT_STATUS_UPDATED . $order_status_label .
            EMAIL_TEXT_STATUS_PLEASE_REPLY;

        $html_msg['EMAIL_CUSTOMERS_NAME'] = $order_info['customers_name'];
        $html_msg['EMAIL_TEXT_ORDER_NUMBER'] = EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID;
        $html_msg['EMAIL_TEXT_INVOICE_URL']  = '<a href="' . $account_history_info_link .'">' . str_replace(':', '', EMAIL_TEXT_INVOICE_URL) . '</a>';
        $html_msg['EMAIL_TEXT_DATE_ORDERED'] = EMAIL_TEXT_DATE_ORDERED . ' ' . $date_purchased;
        $html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = nl2br($notify_comments);
        $html_msg['EMAIL_TEXT_STATUS_UPDATED'] = str_replace("\n", '', EMAIL_TEXT_STATUS_UPDATED);
        $html_msg['EMAIL_TEXT_STATUS_LABEL'] = str_replace("\n", '', $order_status_label);
        $html_msg['EMAIL_TEXT_NEW_STATUS'] = $this->eoGetOrdersStatusName($status);
        $html_msg['EMAIL_TEXT_STATUS_PLEASE_REPLY'] = str_replace("\n",'', EMAIL_TEXT_STATUS_PLEASE_REPLY);
        $html_msg['EMAIL_PAYPAL_TRANSID'] = '';

        zen_mail(
            $order_info['customers_name'], 
            $order_info['customers_email_address'], 
            EMAIL_TEXT_SUBJECT . ' #' . $oID, 
            $message, 
            STORE_NAME, 
            EMAIL_FROM, 
            $html_msg, 
            'order_status'
        );

        // PayPal Trans ID, if any
        $sql = 
            "SELECT txn_id, parent_txn_id 
               FROM " . TABLE_PAYPAL . " 
              WHERE order_id = :orderID 
           ORDER BY last_modified DESC, date_added DESC, parent_txn_id DESC, paypal_ipn_id DESC ";
        $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
        $result = $db->Execute($sql);
        if (!$result->EOF) {
            $message .= PHP_EOL . PHP_EOL . ' PayPal Trans ID: ' . $result->fields['txn_id'];
            $html_msg['EMAIL_PAYPAL_TRANSID'] = $result->fields['txn_id'];
        }

        //send extra emails
        if (SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_STATUS == '1' and SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO != '') {
            zen_mail(
                '', 
                SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO, 
                SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_SUBJECT . ' ' . EMAIL_TEXT_SUBJECT . ' #' . $oID, 
                $message, 
                STORE_NAME, 
                EMAIL_FROM, 
                $html_msg, 
                'order_status_extra'
            );
        }
    }
    
    protected function updateOrdersStatusAndComments($status, $comments, $notify_customer, $track_id)
    {
        $oID = $this->orders_id;
        
//-bof-20160330-lat9-Don't over-prepare input (results in \n\n instead of two line-feeds).
        $sql_data_array = array(
            'orders_id' => (int)$oID,
            'orders_status_id' => $status,
            'date_added' => 'now()',
            'customer_notified' => $notify_customer,
            'comments' => $comments,
        );
//-eof-20160330-lat9
        // BEGIN TY TRACKER 3 - INCLUDE DATABASE FIELDS IN STATUS UPDATE
        foreach ($track_id as $id => $track) {
            $sql_data_array['track_id' . $id] = zen_db_input($track);
        }
        // END TY TRACKER 3 - INCLUDE DATABASE FIELDS IN STATUS UPDATE
        $this->zenDbPerform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        $sql_data_array = array(
            'orders_status' => zen_db_input($status),
            'last_modified' => 'now()'
        );
        $this->zenDbPerform(TABLE_ORDERS, $sql_data_array, 'update', "orders_id=$oID LIMIT 1");
    }
}
