<?php
// -----
// Part of the "Edit Orders" plugin v5.0.0+.
//
// Loaded by /admin/edit_orders.php to provide the display of the products-purchased and order-totals associated 
// with the order.  For previous versions of EO, this was in-line code in that module.  Runs in "global" scope.
//
?>
    <table id="eo-prods">
        <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent" colspan="2"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
            <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
            <td class="dataTableHeadingContent eo-right"><?php echo TABLE_HEADING_TAX; ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
            <td class="dataTableHeadingContent eo-right"><?php echo TABLE_HEADING_UNIT_PRICE; ?></td>
            <td class="dataTableHeadingContent eo-right"><?php echo TABLE_HEADING_TOTAL_PRICE; ?></td>
        </tr>

<?php
    $orders_products = $eo->eoGetOrdersProducts();
    foreach ($orders_products as $current_product) {
        $opid = $current_product['orders_products_id'];
        $form_field_name = "update_products[$opid]"
?>
        <tr class="dataTableRow">
            <td class="dataTableContent eo-top-left">
                <input name="<?php echo $form_field_name; ?>[qty]" size="2" value="<?php echo zen_db_prepare_input($current_product['qty']); ?>" />&nbsp;&nbsp;&nbsp;&nbsp; X
            </td>
            <td class="dataTableContent eo-top-left">
                <input name="<?php echo $form_field_name; ?>[name]" size="55" value="<?php echo zen_html_quotes($current_product['name']); ?>" />
<?php
        if (isset($current_product['attributes']) && count($current_product['attributes']) > 0) { 
?>
                <br/><nobr><small>&nbsp;<i><?php echo TEXT_ATTRIBUTES_ONE_TIME_CHARGE; ?>
                <input name="<?php echo $form_field_name; ?>[onetime_charges]" size="8" value="<?php echo zen_db_prepare_input($current_product['onetime_charges']); ?>" />&nbsp;&nbsp;&nbsp;&nbsp;</i></small></nobr><br/>
<?php
            $attrs = $eo->eoGetProductOptionsAndValues($current_product['id']);
            $attr_field_name = $form_field_name . '[attr]';
            foreach ($attrs as $option_id => $option_info) {
                $html_id_value = "opid-$opid-oid-$option_id";
                $html_id = 'id="' . $html_id_value . '"';
                $option_element_name = $attr_field_name . "[$option_id][value]";
                
                echo zen_draw_hidden_field($attr_field_name . '[' . $option_id . '][type]', $option_info['type']);
                
                switch ($option_info['type']) {
                    case PRODUCTS_OPTIONS_TYPE_RADIO:
                    case PRODUCTS_OPTIONS_TYPE_SELECT:
                    case PRODUCTS_OPTIONS_TYPE_SELECT_SBA:
                    case PRODUCTS_OPTIONS_TYPE_ATTRIBUTE_GRID:
                        $products_options_array = array();
                        foreach ($option_info['options'] as $value_id => $value) {
                            $products_options_array[] = array(
                                'id' => $value_id,
                                'text' => $value
                            );
                        }
                        $selected_value = $eo->eoGetSelectedOptionValueId($current_product['uprid'], $option_id);
                        if ($selected_value === false) {
                            $selected_value = $products_options_array[0]['id'];
                        }
                        
                        echo '<label class="attribsSelect" for="' . $html_id_value . '">' . $option_info['name'] . '</label>';
                        echo zen_draw_pull_down_menu($option_element_name, $products_options_array, $selected_value, $html_id) . "<br />" . PHP_EOL;
                        unset($products_options_array, $selected_value);
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_CHECKBOX:
                        echo '<div class="attribsCheckboxGroup"><div class="attribsCheckboxName">' . $option_info['name'] . '</div>';
                        foreach ($option_info['options'] as $value_id => $value) {
                            $option_checked = $eo->eoIsOptionSelected($current_product['uprid'], $option_id, $value_id);
                            $cb_html_value_id = $html_id_value . "-$value_id";
                            $cb_html_id = 'id="' . $cb_html_value_id . '"';
                            echo zen_draw_checkbox_field($option_element_name . '[' . $value_id . ']', $value_id, $option_checked, null, $cb_html_id);
                            echo '<label class="attribsCheckbox" for="' . $cb_html_value_id . '">' . $value . '</label><br />' . PHP_EOL;
                        }
                        echo '</div>';
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_TEXT:
                        $text = zen_html_quotes($eo->eoGetOptionValue($current_product['uprid'], $option_id));
                        $rows = $option_info['rows'];
                        $cols = $size = $option_info['size'];
                        echo '<label class="attribsInput" for="' . $html_id_value . '">' . $option_info['name'] . '</label>';
                        if ($option_info['rows'] > 1 ) {
                            echo '<textarea class="attribsTextarea" name="' . $option_element_name . '" rows="' . $rows . '" cols="' . $cols . '" ' . $html_id . '>' . $text . '</textarea>' . PHP_EOL;
                        } else {
                            echo '<input type="text" name="' . $option_element_name . '" size="' . $size . '" maxlength="' . $size . '" value="' . $text . '" ' . $html_id . ' /><br />' . PHP_EOL;
                        }
                        unset($text);
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_FILE:
                        $value = $eo->eoGetOptionValue($current_product['uprid'], $option_id);
                        echo '<span class="attribsFile">' . $option_info['name'] . ': ' . (zen_not_null($value) ? $value : TEXT_ATTRIBUTES_UPLOAD_NONE) . '</span><br />';
                        if (zen_not_null($value)) {
                            echo zen_draw_hidden_field($option_element_name, $value);
                        }
                        unset($value);
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_READONLY:
                    default:
                        $value = $eo->eoGetOptionValue($current_product['uprid'], $option_id);
                        echo '<input type="hidden" name="' . $option_element_name . '" value="' . $value . '" /><span class="attribsRO">' . $option_info['name'] . ': ' . $value . '</span><br />';
                        break;
                }
            }
            unset($optionID, $option_info);
        } 
?>
            </td>
            <td class="dataTableContent eo-top"><input name="<?php echo $form_field_name; ?>[model]" size="55" value="<?php echo $current_product['model']; ?>" /></td>
            <td class="dataTableContent eo-top-right"><input class="amount" name="<?php echo $form_field_name; ?>[tax]" size="3" value="<?php echo zen_display_tax_value($current_product['tax']); ?>" />&nbsp;%</td>
            <td class="dataTableContent eo-top-right"><input class="amount" name="<?php echo $form_field_name; ?>[final_price]" size="5" value="<?php echo number_format($current_product['final_price'], 2, '.', ''); ?>" /></td>
            <td class="dataTableContent eo-top-right"><?php echo $eo->eoFormatCurrencyValue($current_product['final_price'] * $current_product['qty'] + $current_product['onetime_charges']); ?></td>
        </tr>
<?php
    } 
?>
        <tr>
            <td class="eo-top" colspan="3"><br /><?php echo '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('oID', 'action')) . 'oID=' . $oID . '&amp;action=add_prdct') . '">' . zen_image_button('button_add_product.gif', TEXT_ADD_NEW_PRODUCT) . '</a>'; ?></td>
            <td colspan="3"><table id="eo-totals">
 <?php
    // Iterate over the order totals.
    $index = 0;
    foreach ($order->totals as $total) {
        $total_index_name = "update_total[$index]";
        $index++;
        $hidden_total_field = zen_draw_hidden_field($total_index_name . '[code]', $total['class']);
        
        $stripped_title = strip_tags(trim($total['title']));
?>
                <tr>
<?php
        switch($total['class']) {
            // Automatically generated fields, those should never be included
            case 'ot_subtotal':
            case 'ot_total':
            case 'ot_tax':
            case 'ot_local_sales_taxes': 
?>
                    <td align="right">&nbsp;</td>
                    <td class="main" align="right"><strong><?php echo $total['title']; ?></strong></td>
                    <td class="main" align="right"><strong><?php echo $total['text']; ?></strong></td>
<?php
                break;

            // Include these in the update but do not allow them to be changed
            case 'ot_group_pricing':
            case 'ot_cod_fee':
            case 'ot_loworderfee': 
?>
                    <td><?php echo $hidden_total_field; ?></td>
                    <td class="main eo-right"><?php echo strip_tags($total['title']) . zen_draw_hidden_field("{$total_index_name}[title]", strip_tags($total['title'])); ?></td>
                    <td class="main eo-right"><?php echo $total['text'] . zen_draw_hidden_field("{$total_index_name}[value]", $total['value']); ?></td>
<?php
                break;

            // Allow changing the title / text, but not the value. Typically used
            // for order total modules which handle the value based upon another condition
            case 'ot_coupon': 
?>
                    <td><?php echo $hidden_total_field; ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[title]", $stripped_title, 'class="amount" size="' . strlen($stripped_title) . '"'); ?></td>
                    <td class="main eo-right"><?php echo $total['text'] . zen_draw_hidden_field("{$total_index_name}[value]", $total['value']); ?></td>
<?php
                break;

            case 'ot_shipping':
                $available_shipping_modules = $eo->eoGetAvailableShippingModules();
                $hidden_shipping_fields = $hidden_total_field;
                foreach ($available_shipping_modules as $current_shipping_module) {
                    $ship_id = $current_shipping_module['id'];
                    $hidden_shipping_fields .= zen_draw_hidden_field($total_index_name . "[{$ship_id}_tax_class]", $current_shipping_module['tax_class']);
                    $hidden_shipping_fields .= zen_draw_hidden_field($total_index_name . "[{$ship_id}_tax_basis]", $current_shipping_module['tax_basis']);
                }
?>
                    <td class="eo-right"><?php echo $hidden_shipping_fields . zen_draw_pull_down_menu("{$total_index_name}[shipping_module]", $available_shipping_modules, $order->info['shipping_module_code']); ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[title]", $stripped_title, 'class="amount" size="' . strlen($stripped_title) . '"'); ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[value]", $total['value'], 'class="amount" size="6"'); ?></td>
<?php
                break;

            case 'ot_gv':
            case 'ot_voucher': 
?>
                    <td><?php echo $hidden_total_field; ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[title]", $stripped_title, 'class="amount" size="' . strlen($stripped_title) . '"'); ?></td>
                    <td class="smallText eo-right">
<?php                 
                if ($total['value'] > 0) {
                    $total['value'] *= -1;
                }
                echo '<input class="amount" size="6" name="' . "{$total_index_name}[value]" . '" value="' . $total['value'], '" />'; 
?>
                    </td>
<?php
                break;

            default: 
?>
                    <td><?php echo $hidden_total_field; ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[title]", $stripped_title, 'class="amount" size="' . strlen($stripped_title) . '"'); ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[value]", $total['value'], 'class="amount" size="6"'); ?></td>
<?php
                break;
        } 
?>
                </tr>
<?php
    }
    
    // -----
    // Retrieve the list of order-totals that are available for an order, but not yet included.
    //
    $total_index_name = "update_total[$index]";
    $additional_totals = $eo->eoGetAdditionalOrderTotalsDropdown();
    if (count($additional_totals) > 0) {
?>
                <tr>
                    <td class="smallText eo-right"><?php echo TEXT_ADD_ORDER_TOTAL . zen_draw_pull_down_menu("{$total_index_name}[code]", $additional_totals, '', 'id="update_total_code"'); ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[title]", '', 'class="amount" style="width: 100%"'); ?></td>
                    <td class="smallText eo-right"><?php echo zen_draw_input_field("{$total_index_name}[value]", '', 'class="amount" size="6"'); ?></td>
                </tr>
                <tr>
                    <td colpan="3" class="smallText eo-left" id="update_total_shipping" style="display: none"><?php echo TEXT_CHOOSE_SHIPPING_MODULE . zen_draw_pull_down_menu("{$total_index_name}[shipping_module]", $additional_totals); ?></td>
                </tr>
<?php
    }
    unset($i, $index, $n, $total, $details); 
?>
            </table></td>
        </tr>
    </table>
