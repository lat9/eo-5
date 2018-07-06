<?php
// -----
// Part of the "Edit Orders" plugin v5.0.0+.
//
// Loaded by /admin/edit_orders.php to provide the top-level order navigation.  For previous
// versions of EO, this was in-line code in that module.
//
// Runs in "global" scope.
//
$get_prev = $db->Execute(
    "SELECT orders_id 
       FROM " . TABLE_ORDERS . " 
      WHERE orders_id < $oID
   ORDER BY orders_id DESC 
      LIMIT 1"
);
if (!$get_prev->EOF) {
    $prev_oid = $get_prev->fields['orders_id'];
    $prev_button = '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, "oID=$prev_oid&amp;action=edit") . '">&lt;&lt;&lt; ' . $prev_oid . '</a>';
} else {
    $prev_button = '<a href="' . zen_href_link(FILENAME_ORDERS) . '">' . BUTTON_TO_LIST . '</a>';
}

$get_next = $db->Execute(
    "SELECT orders_id 
       FROM " . TABLE_ORDERS . " 
      WHERE orders_id > $oID
   ORDER BY orders_id ASC 
      LIMIT 1"
);
if (!$get_next->EOF) {
    $next_oid = $get_next->fields['orders_id'];
    $next_button = '<a href="' . zen_href_link(FILENAME_EDIT_ORDERS, "oID=$next_oid&amp;action=edit") . '">&gt;&gt;&gt; ' . $next_oid . '</a>';
} else {
    $next_button = '<a href="' . zen_href_link(FILENAME_ORDERS) . '">' . BUTTON_TO_LIST . '</a>';
}
?>
    <div id="eo-nav">
        <div class="eo-button"><?php echo $prev_button; ?></div>
        <div><?php echo SELECT_ORDER_LIST . '<br />' . zen_draw_form('input_oid', FILENAME_ORDERS, '', 'get', '', true) . zen_draw_input_field('oID', '') . zen_draw_hidden_field('action', 'edit') . '</form>'; ?></div>
        <div class="eo-button"><?php echo $next_button; ?></div>
    </div>
