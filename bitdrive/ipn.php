<?php

/*
 * Copyright (c) 2015 IntricateWare Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
 
include_once(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/../../init.php');
include_once(_PS_MODULE_DIR_.'bitdrive/bitdrive.php');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    exit;
}

// Initialise the required variables
$bitdrive = new BitDrive();
$bitdrive->defineOrderStates();
$merchantId = Configuration::get('BITDRIVE_MERCHANT_ID');
$ipnSecret = Configuration::get('BITDRIVE_IPN_SECRET');

// Check for SHA 256 support
if (!in_array('sha256', hash_algos())) {
    exit;
}

// Check the IPN data
$data = file_get_contents('php://input');
$json = json_decode($data);
if (!$json) {
    exit;
}

// Check for the IPN parameters that are required
$requiredIpnParams = array(
    'notification_type',
    'sale_id',
    'merchant_invoice',
    'amount',
    'bitcoin_amount'
);
foreach ($requiredIpnParams as $param) {
    if (!isset($json->$param) || strlen(trim($json->$param)) == 0) {
        exit;
    }
}

// Verify the SHA 256 hash
$hashString = strtoupper(hash('sha256', $json->sale_id . $merchantId . $json->merchant_invoice . $ipnSecret));
if ($hashString != $json->hash) {
    exit;
}

// Get the corresponding order
$invoice_data = explode('_', $json->merchant_invoice);
$order_id = $invoice_data[0];
$order = new Order((int) $order_id);
if (!Validate::isLoadedObject($order) OR !$order->id) {
    exit;
}

// Update the order status based on the notification type
$order_state_id = 0;
switch ($json->notification_type) {
    // Order created
    case 'ORDER_CREATED':
        $order_state_id = Configuration::get('BD_OS_PENDING');
        break;
    
    // Payment completed
    case 'PAYMENT_COMPLETED':
        $order_state_id = Configuration::get('PS_OS_PAYMENT');
        break;
    
    // Transaction cancelled
    case 'TRANSACTION_CANCELLED':
        $order_state_id = Configuration::get('PS_OS_CANCELED');
        break;
    
    // Transaction expired
    case 'TRANSACTION_EXPIRED':
        $order_state_id = Configuration::get('BD_OS_EXPIRED');
        break;
}

// Check if the new order status is the same as the current one
if ($order->getCurrentState() == $order_state_id) {
    return;
}

// Set the order state in order history
$history = new OrderHistory();
$history->id_order = (int) $order->id;
$history->changeIdOrderState((int) $order_state_id, (int) $order->id);
$history->addWithemail(true, null);

?>