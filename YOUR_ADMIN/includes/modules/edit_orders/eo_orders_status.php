<?php
// -----
// Part of the "Edit Orders" plugin v5.0.0+.
//
// Loaded by /admin/edit_orders.php to provide the display of the orders-status entries for the order, accepting
// a status-update with comments. For previous versions of EO, this was in-line code in that module.  Runs in "global" scope.
//
$using_ty_tracker = (defined('TY_TRACKER') && TY_TRACKER == 'True');
$num_columns = ($using_ty_tracker) ? 5 : 4;

// -----
// Define some in-line constants (might be moved to admin configuration in the future)
//
if (!defined('EO_NOTIFICATION_DEFAULT')) {
    define('EO_NOTIFICATION_DEFAULT', '1');     //- One of '0' (no email, visible), '1' (email, visible), '-1' (no email, admin-only)
}

// -----
// First, generate a table that contains the order's current status-history.
//
?>
    <table id="eo-stats">
        <tr>
            <td class="main eo-label" colspan="<?php echo $num_columns; ?>"><?php echo zen_image(DIR_WS_IMAGES . 'icon_comment_add.png', TABLE_HEADING_STATUS_HISTORY) . '&nbsp;' . TABLE_HEADING_STATUS_HISTORY; ?></td>
        </tr>
<?php
$status_fields = 'orders_status_id, date_added, customer_notified, comments';
if ($using_ty_tracker) {
    $status_fields .= ', track_id1, track_id2, track_id3, track_id4, track_id5';
}
$orders_history = $db->Execute(
    "SELECT $status_fields
       FROM " . TABLE_ORDERS_STATUS_HISTORY . "
      WHERE orders_id = $oID
   ORDER BY date_added"
);
if ($orders_history->EOF) {
?>
        <tr>
            <td colspan="<?php echo $num_columns; ?>"><?php echo TEXT_NO_ORDER_HISTORY; ?></td>
        </tr>
<?php
} else {
?>
        <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent eo-label"><?php echo TABLE_HEADING_DATE_ADDED; ?></td>
            <td class="dataTableHeadingContent eo-label eo-center"><strong><?php echo TABLE_HEADING_CUSTOMER_NOTIFIED; ?></td>
            <td class="dataTableHeadingContent eo-label"><?php echo TABLE_HEADING_STATUS; ?></td>
<?php
    if ($using_ty_tracker) {
?>
            <td class="dataTableHeadingContent eo-label"><?php echo TABLE_HEADING_TRACKING_ID; ?></td>
<?php
    }
?>
            <td class="dataTableHeadingContent eo-label"><?php echo TABLE_HEADING_COMMENTS; ?></td>
        </tr>
<?php
    while (!$orders_history->EOF) {
        switch ($orders_history->fields['customer_notified']) {
            case '1':
                $status_icon = 'tick.gif';
                $icon_alt_text = TEXT_YES;
                break;
            case '0':
                $status_icon = 'unlocked.gif';
                $icon_alt_text = TEXT_VISIBLE;
                break;
             default:
                $status_icon = 'locked.gif';
                $icon_alt_text = TEXT_HIDDEN;
                break;
        }
        $icon_image = zen_image(DIR_WS_ICONS . $status_icon, $icon_alt_text);
        
        if ($using_ty_tracker) {
            $display_track_id = '&nbsp;';
            for ($i = 1, $display_track_id = '&nbsp;'; $i <= 5; $i++) {
                $tID = "track_id$i";
                if (!empty($orders_history->fields[$tID])) {
                    $carrier_name = "CARRIER_NAME_$i";
                    $carrier_link = "CARRIER_LINK_$i";
                    if (!defined($carrier_name) || !defined($carrier_link)) {
                        trigger_error("Missing Ty-Tracker constants ($carrier_name, $carrier_link); order#$oID cannot be formatted.", E_USER_WARNING);
                    } else {
                        $track_id = htmlspecialchars($orders_history->fields[$tID], ENT_COMPAT, CHARSET, false);
                        $display_track_id .= (constant($carrier_name) . ': <a href="' . constant($carrier_link) . nl2br($track_id) . ' target="_blank">' . $track_id . '</a>&nbsp;');
                    }
                }
            }
         }
?>
        <tr>
            <td class="eo-top"><?php echo zen_datetime_short($orders_history->fields['date_added']); ?></td>
            <td class="eo-center"><?php echo $icon_image; ?></td>
            <td class="eo-top"><?php echo $eo->eoGetOrdersStatusName($orders_history->fields['orders_status_id']); ?></td>
<?php
        if ($using_ty_tracker) {
?>
            <td class="eo-top"><?php echo $display_track_id; ?></td>
<?php
        }
?>
            <td class="eo-top"><?php echo nl2br(zen_db_output($orders_history->fields['comments'])); ?>&nbsp;</td>
        </tr>
<?php
        $orders_history->MoveNext();
    }
}
?>
    </table>
    
    <div class="eo-spacer"></div>
<?php
// -----
// Next, create the form elements through which the admin can add a *new* comment to the order.
//
?>
    <div id="eo-notify">
        <div class="eo-label"><?php echo TABLE_HEADING_COMMENTS; ?></div>
        <div><?php echo zen_draw_textarea_field('comments', 'soft', '60', '5'); ?></div>
<?php
if ($using_ty_tracker) {
?>
        <table>
            <tr>
                <td class="main eo-label" colspan="2"><?php echo zen_image(DIR_WS_IMAGES . 'icon_track_add.png', ENTRY_ADD_TRACK) . '&nbsp;' . ENTRY_ADD_TRACK; ?></td>
            </tr>
            <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent eo-label"><?php echo TABLE_HEADING_CARRIER_NAME; ?></td>
                <td class="dataTableHeadingContent eo-label"><?php echo TABLE_HEADING_TRACKING_ID; ?></td>
            </tr>
<?php
    for ($i = 1; $i <= 5; $i++) {
        $carrier_status = "CARRIER_STATUS_$i";
        $carrier_name = "CARRIER_NAME_$i";
        if (defined($carrier_status) && constant($carrier_status) == 'True' && defined($carrier_name)) {
?>
            <tr>
                <td><?php echo constant($carrier_name); ?></td>
                <td class="eo-top"><?php echo zen_draw_input_field("track_id[$i]", '', 'size="50"'); ?></td>
            </tr>
<?php
        }
    }
?>
        </table>
<?php
}

// -----
// Create the radio-buttons that identify the form (if any) of customer-notification to accompany
// any status-update.
//
$notifications = array(
    '1' => TEXT_EMAIL,
    '0' => TEXT_NOEMAIL,
    '-1' => TEXT_HIDE
);
$customer_notification = '';
foreach ($notifications as $value => $text) {
    $default = ($value == EO_NOTIFICATION_DEFAULT);
    $customer_notification .= zen_draw_radio_field('notify', $value, $default) . " - $text ";
}

$customer_notification .= '&nbsp;&nbsp;&nbsp;<b>' . ENTRY_NOTIFY_COMMENTS . '</b> ' . zen_draw_checkbox_field('notify_comments', '', true);
?>
        <div class="eo-wrap">
            <div><?php echo ENTRY_CURRENT_STATUS; ?></div>
            <div><?php echo $eo->eoGetOrdersStatusName($orders_history->fields['orders_status_id']); ?></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_STATUS; ?></div>
            <div><?php echo zen_draw_pull_down_menu('status', $eo->eoGetOrdersStatusDropdownInput(), $orders_history->fields['orders_status_id']); ?></div>
        </div>
        <div class="eo-wrap">
            <div><?php echo ENTRY_NOTIFY_CUSTOMER; ?></div>
            <div><?php echo $customer_notification; ?></div>
        </div>
        <div class="clearBoth"><?php echo zen_image_submit('button_update.gif', IMAGE_UPDATE); ?></div>
    </div>
