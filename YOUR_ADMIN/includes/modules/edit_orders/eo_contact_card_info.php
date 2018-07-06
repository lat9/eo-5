<?php
// -----
// Part of the "Edit Orders" plugin v5.0.0+.
//
// Loaded by /admin/edit_orders.php to provide the display of the contact- and credit-card-information associated 
// with the order.  For previous versions of EO, this was in-line code in that module.  Runs in "global" scope.
//
?>
    <div class="eo-spacer"></div>
    <div id="eo-info">
        <div class="eo-wrap">
            <div><?php echo ENTRY_TELEPHONE_NUMBER; ?></div>
            <div><input name="update_customer_telephone" size="15" value="<?php echo zen_html_quotes($order->customer['telephone']); ?>"></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_EMAIL_ADDRESS; ?></div>
            <div><input name="update_customer_email_address" size="35" value="<?php echo zen_html_quotes($order->customer['email_address']); ?>"></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_PAYMENT_METHOD; ?></div>
            <div><input name="update_info_payment_method" size="20" value="<?php echo zen_html_quotes($order->info['payment_method']); ?>">&nbsp;&nbsp;<?php echo ($order->info['payment_method'] != 'Credit Card') ? ENTRY_UPDATE_TO_CC : ENTRY_UPDATE_TO_CK; ?></div>
        </div>
<?php
// -----
// If credit-card information is available for the order, display it.
//
if (!empty($order->info['cc_type']) || !empty($order->info['cc_owner']) || $order->info['payment_method'] == "Credit Card" || !empty($order->info['cc_number'])) {
    $cc_type = (empty($order->info['cc_type'])) ? 'n/a' : zen_html_quotes($order->info['cc_type']);
    $cc_owner = (empty($order->info['cc_owner'])) ? 'n/a' : zen_html_quotes($order->info['cc_owner']);
    $cc_number = (empty($order->info['cc_number'])) ? 'n/a' : zen_html_quotes($order->info['cc_number']);
    $cc_expires = (empty($order->info['cc_expires'])) ? 'n/a' : zen_html_quotes($order->info['cc_expires']);
?>
        <div class="eo-wrap">
            <div><?php echo ENTRY_CREDIT_CARD_TYPE; ?></div>
            <div><input name="update_info_cc_type" size="10" value="<?php echo $cc_type; ?>"></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_CREDIT_CARD_OWNER; ?></div>
            <div><input name="update_info_cc_owner" size="20" value="<?php echo $cc_owner; ?>"></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_CREDIT_CARD_NUMBER; ?></div>
            <div><input name="update_info_cc_number" size="20" value="<?php echo $cc_number; ?>"></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_CREDIT_CARD_EXPIRES; ?></div>
            <div><input name="update_info_cc_expires" size="4" value="<?php echo $cc_expires; ?>"></div>
        </div>
<?php
}

// -----
// If "Super Orders" fields are available for the order, display them.
//
if (isset($order->info['account_name']) || isset($order->info['account_number']) || isset($order->info['po_number'])) {
    $account_name = (empty($order->info['account_name'])) ? 'n/a' : zen_html_quotes($order->info['account_name']);
    $account_number = (empty($order->info['account_number'])) ? 'n/a' : zen_html_quotes($order->info['account_number']);
    $po_number = (empty($order->info['po_number'])) ? 'n/a' : zen_html_quotes($order->info['po_number']);
?>
        <div class="eo-wrap">
            <div><?php echo ENTRY_ACCOUNT_NAME; ?></div>
            <div><?php echo $account_name; ?></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_ACCOUNT_NUMBER; ?></div>
            <div><?php echo $account_number; ?></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_PURCHASE_ORDER_NUMBER; ?></div>
            <div><?php echo $po_number; ?></div>
        </div>
<?php
    }
?>
    </div>
