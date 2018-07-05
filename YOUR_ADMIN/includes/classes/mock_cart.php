<?php
// -----
// Part of the Edit Orders plugin (v4.1.6 and later) by lat9 (lat9@vinosdefrutastropicales.com).
// Copyright (C) 2016-2018, Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

/**
 * This is a very basic mock shopping cart class.
 *
 * Many methods are not implemented and may return incorrect values,
 */
class mockCart extends base 
{
    public function __construct()
    {
        $requestErrors = false;
    }
    
    // Do Nothing
    public function restore_contents() 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function reset($reset_database = false) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function add_cart($products_id, $qty = '1', $attributes = '', $notify = true) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function update_quantity($products_id, $quantity = '', $attributes = '') 
    {
        $this->logUnsupportedRequest();        
    }

    // Do Nothing
    public function cleanup() 
    { 
        $this->logUnsupportedRequest();
    }

    /**
     * Method to count total number of items in cart
     *
     * Note this is not just the number of distinct items in the cart,
     * but the number of items adjusted for the quantity of each item
     * in the cart, So we have had 2 items in the cart, one with a quantity
     * of 3 and the other with a quantity of 4 our total number of items
     * would be 7
     *
     * @return total number of items in cart
     */
    public function count_contents() 
    {
        $this->notify('NOTIFIER_CART_COUNT_CONTENTS_START');
        $retval = 0;
        foreach($GLOBALS['order']->products as $product) {
            $retval += $product['qty'];
        }
        $this->notify('NOTIFIER_CART_COUNT_CONTENTS_END');
        return $retval;
    }

    /**
     * Method to get the quantity of an item in the cart
     *
     * @param mixed product ID of item to check
     * @return decimal the quantity of the item
     */
    public function get_quantity($products_id) 
    {
        $this->notify('NOTIFIER_CART_GET_QUANTITY_START', array(), $products_id);
        foreach ($GLOBALS['order']->products as $product) {
            if ($product['id'] == $products_id) {
                $this->notify('NOTIFIER_CART_GET_QUANTITY_END_QTY', array(), $products_id);
                return $product['qty'];
            }
        }
        $this->notify('NOTIFIER_CART_GET_QUANTITY_END_FALSE', $products_id);
        return 0;
    }

    /**
     * Method to check whether a product exists in the cart
     *
     * @param mixed product ID of item to check
     * @return boolean
     */
    public function in_cart($products_id) 
    {
        $this->notify('NOTIFIER_CART_IN_CART_START', array(), $products_id);
        foreach ($GLOBALS['order']->products as $product) {
            if ($product['id'] == $products_id) {
                $this->notify('NOTIFIER_CART_IN_CART_END_TRUE', array(), $products_id);
                return true;
            }
        }
        $this->notify('NOTIFIER_CART_IN_CART_END_FALSE', $products_id);
        return false;
    }

    // Do Nothing
    public function remove($products_id) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function remove_all() 
    { 
        $this->logUnsupportedRequest();
    }

    /**
     * Method return a comma separated list of all products in the cart
     *
     * @return string
     * @todo ICW - is this actually used anywhere?
     */
    function get_product_id_list() 
    {
        $product_id_list = '';
        foreach ($GLOBALS['order']->products as $product) {
            $product_id_list .= ',' . zen_db_input($product['id']);
        }
        return substr($product_id_list, 1);
    }

    // Do Nothing
    public function calculate() 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function attributes_price($products_id) 
    { 
        $this->logUnsupportedRequest();
        return 0; 
    }

    // Do Nothing
    public function attributes_price_onetime_charges($products_id, $qty) 
    {
        $this->logUnsupportedRequest();        
        return 0; 
    }

    /**
     * Method to calculate weight of attributes for a given item
     *
     * @param mixed the product ID of the item to check
     * @return decimal the weight of the items attributes
     */
    function attributes_weight($products_id) 
    {
        foreach ($GLOBALS['order']->products as $product) {
            if ($product['id'] == $products_id) {
                return $this->get_attribute_weight($product);
            }
        }
        return 0;
    }

    /**
     * Method to return details of all products in the cart
     *
     * @param boolean whether to check if cart contents are valid
     * @return array
     */
    function get_products($check_for_valid_cart = false) 
    {
        $retval = array();
        foreach ($GLOBALS['order']->products as $product) {
            // Adjust fields to match
            $product['quantity'] = $product['qty'];
            unset($product['qty']);

            $products = $GLOBALS['db']->Execute(
                "SELECT master_categories_id AS category, products_image AS image, products_weight AS weight, products_virtual,
                        product_is_always_free_shipping, products_tax_class_id AS tax_class_id
                   FROM " . TABLE_PRODUCTS . "
                  WHERE products_id = " . (int)$product['id'] . "
                  LIMIT 1"
            );
            if (!$products->EOF) {
                $product = array_merge($product, $products->fields);
            }
            $product['weight'] += $this->get_attribute_weight($product);

            $retval[] = $product;
        }
        return $retval;
    }

    /**
     * Method to calculate total price of items in cart
     *
     * @return decimal Total Price
     */
    public function show_total() 
    {
        $total = 0;
        $this->notify('NOTIFIER_CART_SHOW_TOTAL_START');
        foreach ($this->get_products() as $products) {
            if (isset($products['final_price'])) {
                $total += (float) $products['final_price'];
            }
        }
        $this->notify('NOTIFIER_CART_SHOW_TOTAL_END');
        return $total;
    }

    // Do Nothing
    public function show_total_before_discounts() 
    { 
        $this->logUnsupportedRequest();
        return 0; 
    }

    /**
     * Method to calculate total weight of items in cart
     *
     * @return decimal Total Weight
     */
    public function show_weight() 
    {
        $weight = 0;
        foreach ($this->get_products() as $products) {
            if (isset($products['weight'])) {
                $weight += (float) $products['weight'];
            }
        }
        return $weight;
    }

    /**
     * Method to generate a cart ID
     *
     * @param length of ID to generate
     * @return string cart ID
     */
    public function generate_cart_id($length = 5) 
    {
        return zen_create_random_value($length, 'digits');
    }

    // Do Nothing
    public function get_content_type($gv_only = 'false') 
    { 
        $this->logUnsupportedRequest();
        return ''; 
    }

    // Do Nothing
    public function in_cart_mixed($products_id) 
    {
        $this->logUnsupportedRequest();        
        return 0; 
    }

    // Do Nothing
    public function in_cart_mixed_discount_quantity($products_id) 
    { 
        $this->logUnsupportedRequest();
        return 0; 
    }

    /**
     * Method to calculate the number of items in a cart based on an arbitrary property
     *
     * $check_what is the fieldname example: 'products_is_free'
     * $check_value is the value being tested for - default is 1
     * Syntax: $_SESSION['cart']->in_cart_check('product_is_free','1');
     *
     * @param string product field to check
     * @param mixed value to check for
     * @return integer number of items matching restraint
     */
    public function in_cart_check($check_what, $check_value = '1') 
    {
        $retval = 0;
        foreach ($GLOBALS['order']->products as $product) {
            $product_check = $GLOBALS['db']->Execute(
                "SELECT $check_what AS check_it
                   FROM " . TABLE_PRODUCTS . "
                  WHERE products_id = " . (int)$product['id'] . "
                  LIMIT 1"
            );
            if (!$product_check->EOF && $product_check->fields['check_it'] == $check_value) {
                $retval += $product['qty'];
            }
        }
        return $retval;
    }

    // Do Nothing
    public function gv_only() 
    { 
        $this->logUnsupportedRequest();
        return 0; 
    }

    // Do Nothing
    public function free_shipping_items() 
    { 
        $this->logUnsupportedRequest();
        return false; 
    }

    // Do Nothing
    public function free_shipping_prices() 
    { 
        $this->logUnsupportedRequest();
        return 0; 
    }

    // Do Nothing
    public function free_shipping_weight() 
    {
        $this->logUnsupportedRequest();        
        return 0; 
    }

    // Do Nothing
    public function download_counts() 
    { 
        $this->logUnsupportedRequest();
        return 0; 
    }

    // Do Nothing
    public function actionUpdateProduct($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionAddProduct($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionBuyNow($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionMultipleAddProduct($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionNotify($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionNotifyRemove($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionCustomerOrder($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function actionRemoveProduct($goto, $parameters) 
    {
        $this->logUnsupportedRequest();        
    }

    // Do Nothing
    public function actionCartUserAction($goto, $parameters) 
    { 
        $this->logUnsupportedRequest();
    }

    // Do Nothing
    public function adjust_quantity($check_qty, $products, $stack = 'shopping_cart') 
    { 
        $this->logUnsupportedRequest();
        return $check_qty; 
    }

    /**
     * Internal function to determine the attribute weight
     *
     * @param array $product the product array from the order
     * @return number the weight
     */
    private function get_attribute_weight($product) {
        $weight = 0;
        if (isset($product['attributes']) && is_array($product['attributes'])) {
            $products_id = (int)$product['id'];
            $is_always_free_shipping = ($product['product_is_always_free_shipping'] == 1);
            foreach ($product['attributes'] as $attribute) {
                $attribute_weight_info = $GLOBALS['db']->Execute(
                    "SELECT products_attributes_weight, products_attributes_weight_prefix 
                       FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                     WHERE products_id = $products_id
                       AND options_id = " . (int)$attribute['option_id'] . "
                       AND options_values_id = " . (int)$attribute['value_id'] . "
                     LIMIT 1"
                );

                if (!$attribute_weight_info->EOF) {
                    // adjusted count for free shipping
                    if (!$is_always_free_shipping) {
                        $new_attributes_weight = $attribute_weight_info->fields['products_attributes_weight'];
                    } else {
                        $new_attributes_weight = 0;
                    }

                    // + or blank adds
                    if ($attribute_weight_info->fields['products_attributes_weight_prefix'] == '-') {
                        $weight -= $new_attributes_weight;
                    } else {
                        $weight += $new_attributes_weight;
                    }
                }
            }
        }
        return $weight;
    }
    
    /**
     * Private function to log a warning if an unsupported cart function is accessed.
     */
    private function logUnsupportedRequest()
    {
        $this->requestErrors = true;
        trigger_error("Unsupported 'mock-cart' method requested.", E_USER_WARNING);
    }
}
