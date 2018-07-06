<?php
// -----
// Part of the "Edit Orders" plugin v5.0.0+.
//
// Loaded by /admin/edit_orders.php to provide the display of the addresses associated with the order.  For previous
// versions of EO, this was in-line code in that module.  Runs in "global" scope.
//
// Note that the formatting is "legacy" to provide continued support for plugins that make use of the notifier
// EDIT_ORDERS_ADDITIONAL_ADDRESS_ROWS.
//
?>
    <div class="eo-spacer"></div>
    <table id="eo-addr">
        <tr>
            <td>&nbsp;</td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER; ?></td>
            <td>&nbsp;</td>
            <td class="eo-label"><?php echo ENTRY_BILLING_ADDRESS; ?></td>
            <td>&nbsp;</td>
            <td class="eo-label"><?php echo ENTRY_SHIPPING_ADDRESS; ?></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><?php echo zen_image(DIR_WS_IMAGES . 'icon_customers.png', ENTRY_CUSTOMER); ?></td>
            <td>&nbsp;</td>
            <td><?php echo zen_image(DIR_WS_IMAGES . 'icon_billing.png', ENTRY_BILLING_ADDRESS); ?></td>
            <td>&nbsp;</td>
            <td><?php echo zen_image(DIR_WS_IMAGES . 'icon_shipping.png', ENTRY_SHIPPING_ADDRESS); ?></td>
        </tr>

        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_NAME; ?>:&nbsp;</td>
            <td><input name="update_customer_name" value="<?php echo zen_html_quotes($order->customer['name']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_NAME; ?>:&nbsp;</td>
            <td><input name="update_billing_name" value="<?php echo zen_html_quotes($order->billing['name']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_NAME; ?>:&nbsp;</td>
            <td><input name="update_delivery_name" value="<?php echo zen_html_quotes($order->delivery['name']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_COMPANY; ?>:&nbsp;</td>
            <td><input name="update_customer_company" value="<?php echo zen_html_quotes($order->customer['company']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_COMPANY; ?>:&nbsp;</td>
            <td><input name="update_billing_company" value="<?php echo zen_html_quotes($order->billing['company']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_COMPANY; ?>:&nbsp;</td>
            <td><input name="update_delivery_company" value="<?php echo zen_html_quotes($order->delivery['company']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_ADDRESS; ?>:&nbsp;</td>
            <td><input name="update_customer_street_address" value="<?php echo zen_html_quotes($order->customer['street_address']); ?>"></td>
            <td class="eo-label"> <?php echo ENTRY_CUSTOMER_ADDRESS; ?>:&nbsp;</td>
            <td><input name="update_billing_street_address" value="<?php echo zen_html_quotes($order->billing['street_address']); ?>"></td>
            <td class="eo-label"> <?php echo ENTRY_CUSTOMER_ADDRESS; ?>:&nbsp;</td>
            <td><input name="update_delivery_street_address" value="<?php echo zen_html_quotes($order->delivery['street_address']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_SUBURB; ?>:&nbsp;</td>
            <td><input name="update_customer_suburb" value="<?php echo zen_html_quotes($order->customer['suburb']); ?>"></td>
            <td class="eo-label"> <?php echo ENTRY_CUSTOMER_SUBURB; ?>:&nbsp;</td>
            <td><input name="update_billing_suburb" value="<?php echo zen_html_quotes($order->billing['suburb']); ?>"></td>
            <td class="eo-label"> <?php echo ENTRY_CUSTOMER_SUBURB; ?>:&nbsp;</td>
            <td><input name="update_delivery_suburb" value="<?php echo zen_html_quotes($order->delivery['suburb']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_CITY; ?>:&nbsp;</td>
            <td><input name="update_customer_city" value="<?php echo zen_html_quotes($order->customer['city']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_CITY; ?>:&nbsp;</td>
            <td><input name="update_billing_city" value="<?php echo zen_html_quotes($order->billing['city']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_CITY; ?>:&nbsp;</td>
            <td><input name="update_delivery_city" value="<?php echo zen_html_quotes($order->delivery['city']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_STATE; ?>:&nbsp;</td>
            <td><input name="update_customer_state" value="<?php echo zen_html_quotes($order->customer['state']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_STATE; ?>:&nbsp;</td>
            <td><input name="update_billing_state" value="<?php echo zen_html_quotes($order->billing['state']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_STATE; ?>:&nbsp;</td>
            <td><input name="update_delivery_state" value="<?php echo zen_html_quotes($order->delivery['state']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_POSTCODE; ?>:&nbsp;</td>
            <td><input name="update_customer_postcode" value="<?php echo zen_html_quotes($order->customer['postcode']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_POSTCODE; ?>:&nbsp;</td>
            <td><input name="update_billing_postcode" value="<?php echo zen_html_quotes($order->billing['postcode']); ?>"></td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_POSTCODE; ?>:&nbsp;</td>
            <td><input name="update_delivery_postcode" value="<?php echo zen_html_quotes($order->delivery['postcode']); ?>"></td>
        </tr>
        <tr>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_COUNTRY; ?>:&nbsp;</td>
            <td>
<?php
    if (is_array($order->customer['country']) && isset($order->customer['country']['id'])) {
        echo zen_get_country_list('update_customer_country', $order->customer['country']['id']);
    } else {
        echo '<input name="update_customer_country" value="' . zen_html_quotes($order->customer['country']) . '">';
    } 
?>
            </td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_COUNTRY; ?>:&nbsp;</td>
            <td>
<?php
    if (is_array($order->billing['country']) && isset($order->billing['country']['id'])) {
        echo zen_get_country_list('update_billing_country', $order->billing['country']['id']);
    } else {
        echo '<input name="update_billing_country" value="' . zen_html_quotes($order->billing['country']) . '">';
    } 
?>
            </td>
            <td class="eo-label"><?php echo ENTRY_CUSTOMER_COUNTRY; ?>:&nbsp;</td>
            <td>
<?php
    if(is_array($order->delivery['country']) && array_key_exists('id', $order->delivery['country'])) {
        echo zen_get_country_list('update_delivery_country', $order->delivery['country']['id']);
    } else {
        echo '<input name="update_delivery_country" value="' . zen_html_quotes($order->delivery['country']) . '">';
    } 
?>
            </td>
        </tr>
<?php
    $additional_rows = '';
    $zco_notifier->notify('EDIT_ORDERS_ADDITIONAL_ADDRESS_ROWS', $order, $additional_rows);
    echo $additional_rows;
?>
    </table>
