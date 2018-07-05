<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003 The zen-cart developers                           |
// |                                                                      |
// | http://www.zen-cart.com/index.php                                    |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+

require 'includes/application_top.php';

// Check for commonly broken attribute related items
eo_checks_and_warnings();

// Start the currencies code
if (!class_exists('currencies')) {
    require DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php';
}
$currencies = new currencies();

// Use the normal order class instead of the admin one
include DIR_FS_CATALOG . DIR_WS_CLASSES . 'order.php';

$oID = (int)$_GET['oID'];

$step = (isset($_POST['step'])) ? (int)$_POST['step'] : 0;
if (isset($_POST['add_product_categories_id'])) {
    $add_product_categories_id = zen_db_prepare_input($_POST['add_product_categories_id']);
}
if (isset($_POST['add_product_products_id'])) {
    $add_product_products_id = zen_db_prepare_input($_POST['add_product_products_id']);
}
if (isset($_POST['add_product_quantity'])) {
    $add_product_quantity = zen_db_prepare_input($_POST['add_product_quantity']);
}
  
// -----
// The "queryCache" functionality present in the Zen Cart core can get in the way of
// Edit Orders due to the amount of database manipulation.  Remove the default instance
// of the class (used by the database-class) and replace it with a stubbed-out version
// for the EO processing.
//
unset($queryCache);
require DIR_WS_CLASSES . 'EditOrdersQueryCache.php';
$queryCache = new EditOrdersQueryCache();
  
// -----
// Include and instantiate the editOrders class.
//
require DIR_WS_CLASSES . 'editOrders.php';
$eo = new editOrders($oID);

$action = (isset($_GET['action']) ? $_GET['action'] : 'edit');

if (zen_not_null($action)) {
    $eo->eoLog(PHP_EOL . date('Y-m-d H:i:s') . ", Edit Orders entered (". EO_VERSION . ") action ($action)" . PHP_EOL . 'Enabled Order Totals: ' . MODULE_ORDER_TOTAL_INSTALLED, 1);

    switch ($action) {
        // Update Order
        case 'update_order':
            // -----
            // Pull in the order's **current** information, used to determine if anything has changed.
            //
            $order = $eo->eoGetOrderInfoForUpdate();
            
            // -----
            // Check to see if any of the "base" elements of the order have changed, e.g. addresses or contact
            // information.  The return value will be an empty array if nothing has changed.
            //
            $order_updated = $eo->eoUpdateOrdersInfo();
             
            // Handle updating products and attributes as needed
            if (isset($_POST['update_products'])) {
                // -----
                // If products were updated for the order, the order's been updated!
                //
                if ($eo->eoUpdateProductsInOrder()) {
                    $order_updated = true;
                }
            }

            // Update order totals (or delete if no title / value)
            if (isset($_POST['update_total'])) {
                if ($eo->eoUpdateTotalsInOrder()) {
                    $order_updated = true;
                }
            }
            
            if ($eo->eoUpdateOrdersHistoryNotifyCustomer()) {
                $order_updated = true;
            }

            if ($order_updated) {
                $eo->eoUpdateOrdersChanges();
                $messageStack->add_session(SUCCESS_ORDER_UPDATED, 'success');
            } else {
                $messageStack->add_session(WARNING_ORDER_NOT_UPDATED, 'warning');
            }
            
            $eo->eoSessionCleanup();
            zen_redirect(zen_href_link(FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit', 'NONSSL'));
            break;

        case 'add_prdct':
            if (!zen_not_null($step)) {
                $step = 1;
            }
            $eo->eoLog(
                PHP_EOL . '============================================================' .
                PHP_EOL . '= Adding a new product to the order (step ' . $step . ')' .
                PHP_EOL . '============================================================' .
                PHP_EOL . PHP_EOL
            );
            if ($step == 5) {

                // Get Order Info
                $order = $eo->eoGetOrderInfo();

                // Check qty field
                $add_max = zen_get_products_quantity_order_max($add_product_products_id);
                if ($add_product_quantity > $add_max && $add_max != 0) {
                    $add_product_quantity = $add_max;
                    $messageStack->add_session(WARNING_ORDER_QTY_OVER_MAX, 'warning');
                }

                // Retrieve the information for the new product
                $new_product = eo_get_new_product(
                    $add_product_products_id,
                    $add_product_quantity,
                    false,
                    zen_db_prepare_input($_POST['id']),
                    isset($_POST['applyspecialstoprice'])
                );

                // Add the product to the order
                $eo->eoLog(PHP_EOL . 'Product Being Added:' . PHP_EOL . json_encode($new_product) . PHP_EOL);
                eo_add_product_to_order($oID, $new_product);

                // Update Subtotal and Pricing
                eo_update_order_subtotal($oID, $new_product);

                // Save the changes
                eo_update_database_order_totals($oID);
                $order = $eo->eoGetOrderInfo();

                // Remove the low order and/or cod fees (will automatically repopulate if needed)
                foreach ($order->totals as $key => $total) {
                    if ($total['class'] == 'ot_loworderfee' || $total['class'] == 'ot_cod_fee') {
                        // Update the information in the order
                        $total['title'] = '';
                        $total['value'] = 0;
                        $total['code'] = $total['class'];

                        eo_update_database_order_total($oID, $total);
                        unset($order->totals[$key]);
                        break;
                    }
                }

                // Requires $GLOBALS['order'] to be reset and populated
                $order = $eo->eoGetOrderInfo();
                eo_update_database_order_totals($oID);

                $eo->eoLog(
                    PHP_EOL . 'Final Products in Order:' . PHP_EOL . var_export($order->products, true) . PHP_EOL .
                    $eo->eoFormatOrderTotalsForLog($order, 'Final Order Totals:') .
                    'Final Tax (total): ' . $order->info['tax'] . PHP_EOL .
                    'Final Tax Groups:' . PHP_EOL . json_encode($order->info['tax_groups']) . PHP_EOL
                );
                $eo->eoSessionCleanup();
                zen_redirect(zen_href_link(FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action')) . 'action=edit'));
            }
            break;
            
        default:
            break; 
    }
}

$order_exists = false;
if ($action == 'edit' && isset($_GET['oID'])) {
    $orders_query = $db->Execute(
        "SELECT orders_id FROM " . TABLE_ORDERS . " 
          WHERE orders_id = $oID
          LIMIT 1"
    );
    if ($orders_query->EOF) {
      $messageStack->add(sprintf(ERROR_ORDER_DOES_NOT_EXIST, $oID), 'error');
    } else {
        $order_exists = true;
        $order = $eo->eoGetOrderInfoForDisplay();
        if (!$eo->eoOrderIsVirtual($order) &&
               ( !is_array($order->customer['country']) || !array_key_exists('id', $order->customer['country']) ||
                 !is_array($order->billing['country']) || !array_key_exists('id', $order->billing['country']) ||
                 !is_array($order->delivery['country']) || !array_key_exists('id', $order->delivery['country']) )) {
            $messageStack->add(WARNING_ADDRESS_COUNTRY_NOT_FOUND, 'warning');
        }
    }
}
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" href="includes/edit_orders.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script type="text/javascript" src="includes/menu.js"></script>
<script type="text/javascript" src="includes/general.js"></script>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }
  // -->
</script>
</head>
<body onload="init();">
<!-- header //-->
<div class="header-area">
<?php
require DIR_WS_INCLUDES . 'header.php';
?>
</div>
<!-- header_eof //-->
<?php
// -----
// This section formats the Edit Orders form through which order-changes are submitted.
//
if ($action == 'edit' && $order_exists) {
    if ($order->info['payment_module_code']) {
        if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php')) {
            require DIR_FS_CATALOG_MODULES . 'payment/' . $order->info['payment_module_code'] . '.php';
            require DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $order->info['payment_module_code'] . '.php';
            $module = new $order->info['payment_module_code'];
        }
    }
?>
<div id="eo-main">
<?php
    // -----
    // Pull in the upper-navigation formatting.
    //
    require DIR_WS_MODULES . 'edit_orders/eo_navigation.php';
    
    // -----
    // Format the page's header, identifying the order being edited and providing additional navigation.
    //
?>
    <div id="eo-hdr">
        <div><?php echo HEADING_TITLE; ?> #<?php echo $oID; ?></div>
        <div>
            <a href="<?php echo zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('action'))); ?>"><?php echo zen_image_button('button_back.gif', IMAGE_BACK); ?></a>
            <a href="<?php echo zen_href_link(FILENAME_ORDERS, zen_get_all_get_params(array('oID', 'action')) . "oID=$oID&amp;action=edit"); ?>"><?php echo zen_image_button('button_details.gif', IMAGE_ORDER_DETAILS); ?></a>
        </div>
    </div>
<?php
    // -----
    // Start the EO-update form.
    //
    echo zen_draw_form('edit_order', FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action','paycc')) . 'action=update_order');
    
    // -----
    // Pull in the formatting of the customer-, billing- and shipping-address blocks.
    //
    require DIR_WS_MODULES . 'edit_orders/eo_address_blocks.php';
    
    // -----
    // Pull in the formatting of the order's contact and, if present, credit-card information.
    //
    require DIR_WS_MODULES . 'edit_orders/eo_contact_card_info.php';

//-bof-20180323-lat9-GitHub#75, Multiple product-price calculation methods.
    $reset_totals_block = '<b>' . RESET_TOTALS . '</b>' . zen_draw_checkbox_field('reset_totals', '', (EO_TOTAL_RESET_DEFAULT == 'on'));
    $payment_calc_choice = '';
    if (EO_PRODUCT_PRICE_CALC_METHOD == 'Choose') {
        $payment_calc_choice = '<b>' . PAYMENT_CALC_MANUAL . '</b>' . zen_draw_checkbox_field('payment_calc_manual', '', (EO_PRODUCT_PRICE_CALC_DEFAULT == 'Manual'));
    } elseif (EO_PRODUCT_PRICE_CALC_METHOD == 'Manual') {
        $payment_calc_choice = PRODUCT_PRICES_CALC_MANUAL;
    } else {
        $payment_calc_choice = PRODUCT_PRICES_CALC_AUTO;
    }
?>
    <div class="eo-spacer"></div>
    <div class="clearBoth"><?php echo zen_image_submit('button_update.gif', IMAGE_UPDATE, 'name="update_button"') . "&nbsp;$reset_totals_block&nbsp;$payment_calc_choice"; ?></div>
<?php
//-eof-20180323-lat9

    // -----
    // Pull in the formatting of the order's products and order-totals.
    //
    require DIR_WS_MODULES . 'edit_orders/eo_products_totals.php';
    
    // -----
    // Pull in the formatting of the order's status-history and status-update form.
    //
    require DIR_WS_MODULES . 'edit_orders/eo_orders_status.php';
?>
    </form>
</div>
<?php
    // -----
    // Remove any variables added to the session in re-creating this order.
    //
    $eo->eoSessionCleanup();
}

if ($action == "add_prdct") { 
    $order_parms = zen_get_all_get_params(array('oID', 'action', 'resend')) . "oID=$oID&amp;action=edit";
?>
<table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="pageHeading"><?php echo HEADING_TITLE_ADD_PRODUCT; ?> #<?php echo $oID; ?></td>
                <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
                <td class="pageHeading" align="right">
                    <a href="<?php echo zen_href_link(FILENAME_EDIT_ORDERS, $order_parms); ?>"><?php echo zen_image_button('button_back.gif', IMAGE_EDIT); ?></a>
                    <a href="<?php echo zen_href_link(FILENAME_ORDERS, $order_parms); ?>"><?php echo zen_image_button('button_details.gif', IMAGE_ORDER_DETAILS); ?></a>
                </td>
            </tr>
        </table></td>
    </tr>

<?php
    // Set Defaults
    if (!isset($add_product_categories_id)) {
        $add_product_categories_id = .5;
    }

    if (!isset($add_product_products_id)) {
        $add_product_products_id = 0;
    }

    // Step 1: Choose Category
    if ($add_product_categories_id == .5) {
        // Handle initial population of categories
        $categoriesarr = zen_get_category_tree();
        $catcount = count($categoriesarr);
        $texttempcat1 = $categoriesarr[0]['text'];
        $idtempcat1 = $categoriesarr[0]['id'];
        $catcount++;
        for ($i=1; $i<$catcount; $i++) {
            $texttempcat2 = $categoriesarr[$i]['text'];
            $idtempcat2 = $categoriesarr[$i]['id'];
            $categoriesarr[$i]['id'] = $idtempcat1;
            $categoriesarr[$i]['text'] = $texttempcat1;
            $texttempcat1 = $texttempcat2;
            $idtempcat1 = $idtempcat2;
        }


        $categoriesarr[0]['text'] = "Choose Category";
        $categoriesarr[0]['id'] = .5;


        $categoryselectoutput = zen_draw_pull_down_menu('add_product_categories_id', $categoriesarr, $current_category_id, 'onChange="this.form.submit();"');
        $categoryselectoutput = str_replace('<option value="0" SELECTED>','<option value="0">',$categoryselectoutput);
        $categoryselectoutput = str_replace('<option value=".5">','<option value=".5" SELECTED>',$categoryselectoutput);
    } else {

        // Add the category selection. Selecting a category will override the search
        $categoryselectoutput = zen_draw_pull_down_menu('add_product_categories_id', zen_get_category_tree(), $current_category_id, 'onChange="this.form.submit();"');
    }
?> 
    <tr>
        <td><?php echo zen_draw_form('add_prdct', FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action', 'oID')) . "oID=$oID&amp;action=add_prdct", 'post', '', true); ?><table border="0">
            <tr class="dataTableRow">
                <td class="dataTableContent" align="right"><strong><?php echo ADDPRODUCT_TEXT_STEP1; ?></strong></td>
                <td class="dataTableContent" valign="top">
<?php               
    echo 
        ' ' . $categoryselectoutput . 
        ' OR ' .
        HEADING_TITLE_SEARCH_DETAIL . ' ' . 
        zen_draw_input_field('search', (isset($_POST['search']) && $add_product_categories_id <= 1) ? $_POST['search'] : '', 'onclick="this.form.add_product_categories_id.value=0;"') . zen_hide_session_id().
        zen_draw_hidden_field('step', '2');
?>
                </td>
            </tr>
        </table></form></td>
    </tr>
            
    <tr>
        <td>&nbsp;</td>
    </tr>
<?php
    // Step 2: Choose Product
    if ($step > 1 && ($add_product_categories_id != .5 || zen_not_null($_POST['search']))) {
        $query =
            "SELECT p.products_id, p.products_model, pd.products_name, p.products_status
               FROM " . TABLE_PRODUCTS . " p
                    INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                        ON pd.products_id = p.products_id
                       AND pd.language_id = " . (int)$_SESSION['languages_id'];

        if ($add_product_categories_id >= 1) {
            $query .=
                " LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
                    ON ptc.products_id = p.products_id
                 WHERE ptc.categories_id=" . (int)$add_product_categories_id . "
                 ORDER BY p.products_id";
        } elseif (zen_not_null($_POST['search'])) {
            // Handle case where a product search was entered
            $keywords = zen_db_input(zen_db_prepare_input($_POST['search']));

            $query .=
                " WHERE (pd.products_name LIKE '%$keywords%'
                    OR pd.products_description LIKE '%$keywords%'
                    OR p.products_id = " . (int)$keywords . "
                    OR p.products_model LIKE '%$keywords%')
              ORDER BY p.products_id";
        }
?>
    <tr>
        <td><?php echo zen_draw_form('add_prdct', FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action', 'oID')) . "oID=$oID&amp;action=add_prdct", 'post', '', true); ?><table border="0">
            <tr class="dataTableRow">
                <td class="dataTableContent" align="right"><strong><?php echo ADDPRODUCT_TEXT_STEP2; ?></strong></td>
                <td class="dataTableContent" valign="top">
                    <select name="add_product_products_id" onchange="this.form.submit();">
<?php
        $ProductOptions = '<option value="0">' .  ADDPRODUCT_TEXT_SELECT_PRODUCT . '</option>' . PHP_EOL;
        $result = $db->Execute($query);
        while (!$result->EOF) {
            $ProductOptions .= 
                '<option value="' . $result->fields['products_id'] . '">' . 
                    $result->fields['products_name'] .
                    ' [' . $result->fields['products_model'] . '] ' . ($result->fields['products_status'] == 0 ? ' (OOS)' : '') . 
                '</option>' . PHP_EOL;
            $result->MoveNext();
        }
        $ProductOptions = str_replace(
            'value="' . $add_product_products_id . '"',
            'value="' . $add_product_products_id . '" selected',
            $ProductOptions
        );
        echo $ProductOptions;
        unset($ProductOptions);
?>
                    </select>
<?php
        echo 
            zen_draw_hidden_field('add_product_categories_id', $add_product_categories_id) .
            zen_draw_hidden_field('search', $_POST['search']) .
            zen_draw_hidden_field('step', 3);
?>
                </td>
            </tr>
        </table></form></td>
    </tr>
<?php
    }

    // Step 3: Choose Options
    if ($step > 2 && $add_product_products_id > 0) {
        // Skip to Step 4 if no Options
        if (!zen_has_product_attributes($add_product_products_id)) {
            $step = 4;
?>
    <tr class="dataTableRow">
        <td class="dataTableContent"><strong><?php echo ADDPRODUCT_TEXT_STEP3; ?></strong> <i><?php echo ADDPRODUCT_TEXT_OPTIONS_NOTEXIST; ?></i></td>
    </tr>
<?php
        } else {
            $attrs = eo_get_product_attributes_options($add_product_products_id);
?>
    <tr>
        <td><?php echo zen_draw_form('add_prdct', FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action', 'oID')) . "oID=$oID&amp;action=add_prdct", 'post', '', true); ?><table border="0">
            <tr class="dataTableRow">
                <td class="dataTableContent" align="right" valign="top"><strong><?php echo ADDPRODUCT_TEXT_STEP3; ?></strong></td>
                <td class="dataTableContent" valign="top">
<?php
            foreach ($attrs as $optionID => $optionInfo) {
                $option_name = $optionInfo['name'];
                $attrib_id = "attrib-$optionID";
                switch ($optionInfo['type']) {
                    case PRODUCTS_OPTIONS_TYPE_ATTRIBUTE_GRID:
                    case PRODUCTS_OPTIONS_TYPE_RADIO:
                    case PRODUCTS_OPTIONS_TYPE_SELECT:       
?>
                    <label class="attribsSelect" for="<?php echo $attrib_id; ?>"><?php echo $option_name; ?></label>
<?php
                        $products_options_array = array();
                        foreach($optionInfo['options'] as $attributeId => $attributeValue) {
                            $products_options_array[] = array(
                                'id' => $attributeId,
                                'text' => $attributeValue
                            );
                        }
                        $selected_attribute = $products_options_array[0]['id'];
                        if (isset($_POST['id'][$optionID])) {
                            $selected_attribute = $_POST['id'][$optionID]['value'];
                        }
                        echo zen_draw_pull_down_menu('id[' . $optionID . '][value]', $products_options_array, $selected_attribute, 'id="' . $attrib_id . '"') . '<br />' . PHP_EOL;
                        unset($products_options_array, $selected_attribute, $attributeId, $attributeValue);
                        echo zen_draw_hidden_field('id[' . $optionID . '][type]', $optionInfo['type']);
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_CHECKBOX:
?>
                    <div class="attribsCheckboxGroup">
                        <div class="attribsCheckboxName"><?php echo $option_name; ?></div>
<?php
                        foreach($optionInfo['options'] as $attributeId => $attributeValue) {
                            $checked = isset($_POST['id'][$optionID]['value'][$attributeId]);
                            echo zen_draw_checkbox_field('id[' . $optionID . '][value][' . $attributeId . ']', $attributeId, $checked, null, 'id="' . $attrib_id . '-' . $attributeId . '"') . '<label class="attribsCheckbox" for="' . $attrib_id . '-' . $attributeId . '">' . $attributeValue . '</label><br />' . PHP_EOL;
                        }
                        unset($checked, $attributeId, $attributeValue);
                        echo zen_draw_hidden_field('id[' . $optionID . '][type]', $optionInfo['type']);
?>
                    </div>
<?php
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_TEXT:
                        $text = (isset($_POST['id'][$optionID]['value']) ? $_POST['id'][$optionID]['value'] : '');
                        $text = zen_html_quotes($text);
?>
                    <label class="attribsInput" for="<?php echo $attrib_id; ?>"><?php echo $option_name; ?></label>
<?php
                        $field_name = 'id[' . $optionID . '][value]';
                        $field_size = $optionInfo['size'];
                        if ($optionInfo['rows'] > 1 ) {
                            echo zen_draw_textarea_field($field_name, 'hard', $field_size, $optionInfo['rows'], $text, 'class="attribsTextarea" id="' . $attrib_id . '"') . '<br />' . PHP_EOL;
                        } else {
                            echo zen_draw_input_field($field_name, $text, 'size="' . $field_size . '" maxlength="' . $field_size . '" id="' . $attrib_id . '"') . '<br />' . PHP_EOL;
                        }
                        echo zen_draw_hidden_field('id[' . $optionID . '][type]', $optionInfo['type']);
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_FILE:
?>
                    <span class="attribsFile"><?php echo $option_name . ': FILE UPLOAD NOT SUPPORTED'; ?></span><br />
<?php
                        break;
                        
                    case PRODUCTS_OPTIONS_TYPE_READONLY:
                    default:
?>
                    <span class="attribsRO"><?php echo $option_name . ': ' . $optionValue; ?></span><br />
<?php
                        $optionValue = array_shift($optionInfo['options']);
                        echo 
                            zen_draw_hidden_field('id[' . $optionID . '][value]', $optionValue) .
                            zen_draw_hidden_field('id[' . $optionID . '][type]', $optionInfo['type']) . PHP_EOL;
                        unset($optionValue);
                        break;
                }
            }
?>
                </td>
                <td class="dataTableContent" align="center" valign="bottom">
                    <input type="submit" value="<?php echo ADDPRODUCT_TEXT_OPTIONS_CONFIRM; ?>" />
<?php
            echo zen_draw_hidden_field('add_product_categories_id', $add_product_categories_id) .
                zen_draw_hidden_field('add_product_products_id', $add_product_products_id) .
                zen_draw_hidden_field('search', $_POST['search']) .
		zen_draw_hidden_field('step', '4');
?>
                </td>
            </tr>
        </table></form></td>
    </tr>
<?php
        }
?>
    <tr>
        <td>&nbsp;</td>
    </tr>
<?php
    }

    // Step 4: Confirm
    if ($step > 3) {
?>
    <tr>
        <td><?php echo zen_draw_form('add_prdct', FILENAME_EDIT_ORDERS, zen_get_all_get_params(array('action', 'oID')) . "oID=$oID&amp;action=add_prdct", 'post', '', true); ?><table border="0">
            <tr class="dataTableRow">
                <td class="dataTableContent" align="right"><strong><?php echo ADDPRODUCT_TEXT_STEP4; ?></strong></td>
                <td class="dataTableContent" valign="top"><?php echo ADDPRODUCT_TEXT_CONFIRM_QUANTITY . 
                    zen_draw_input_field('add_product_quantity', 1, 'size="2"') .
                    '&nbsp;&nbsp;&nbsp;&nbsp;' .
                    zen_draw_checkbox_field('applyspecialstoprice', '1', true) . ADDPRODUCT_SPECIALS_SALES_PRICE; ?></td>
                 <td class="dataTableContent" align="center">
                    <input type="submit" value="<?php echo ADDPRODUCT_TEXT_CONFIRM_ADDNOW; ?>" />
<?php
        if (isset($_POST['id'])) {
            foreach ($_POST['id'] as $id => $value) {
                if (is_array($value)) {
                    foreach ($value as $id2 => $value2) {
                        if (is_array($value2)) {
                            foreach ($value2 as $id3 => $value3) {
                                echo zen_draw_hidden_field('id[' . $id . '][' . $id2 . '][' . $id3 . ']', zen_html_quotes($value3));
                            }
                        } else {
                            echo zen_draw_hidden_field('id[' . $id . '][' . $id2 . ']', zen_html_quotes($value2));
                        }
                    }
                } else {
                    echo zen_draw_hidden_field('id[' . $id . ']', zen_html_quotes($value));
                }
            }
        }
        echo zen_draw_hidden_field('add_product_categories_id', $add_product_categories_id) .
            zen_draw_hidden_field('add_product_products_id', $add_product_products_id) .
            zen_draw_hidden_field('step', '5');
?>
                </td>
            </tr>
        </table></form></td>
    </tr>
<?php
    }
?>
</table>
<?php
}
?>
<!-- body_text_eof //-->
<script type="text/javascript">
    <!--
    handleShipping();
    function handleShipping() {
        if (document.getElementById('update_total_code') != undefined && document.getElementById('update_total_code').value == 'ot_shipping') {
            document.getElementById('update_total_shipping').style.display = 'table-cell';
        } else {
            document.getElementById('update_total_shipping').style.display = 'none';
        }
    }
    document.getElementById('update_total_code').onchange = function(){handleShipping();};
    // -->
</script>
<!-- body_eof //-->

<!-- footer //-->
<?php 
require DIR_WS_INCLUDES . 'footer.php'; 
?>
<!-- footer_eof //-->
</body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php';
