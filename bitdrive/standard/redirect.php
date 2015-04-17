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

include(dirname(__FILE__).'/../../../config/config.inc.php');
include(dirname(__FILE__).'/../../../init.php');
include(dirname(__FILE__).'/../bitdrive.php');

$bitdrive = new BitDrive();
$cart = new Cart((int) ($cookie->id_cart));

$currency_order = new Currency((int) ($cart->id_currency));
$currency_module = $bitdrive->getCurrency((int) ($cart->id_currency));
$customer = new Customer((int) ($cart->id_customer));

$merchantId = Configuration::get('BITDRIVE_MERCHANT_ID');
if (!$merchantId || strlen(trim($merchantId)) == 0) {
    die($bitdrive->getL('BitDrive error: (invalid or undefined merchant ID)'));
}

// Check currency of payment
if ($currency_order->id != $currency_module->id)
{
    $cookie->id_currency = $currency_module->id;
    $cart->id_currency = $currency_module->id;
    $cart->update();
}

$memo = sprintf('Payment for Order #%s', $cart->id);
$items = $cart->getProducts();
if (count($items) == 1) {
    $item = $items[0];
    $qty = intval($item['cart_quantity']);
    $itemString = (($qty > 0) ? $qty . ' x ' : '') . $item['name'];
    
    $newMemo = $memo . ': ' . $itemString;
    if (strlen($newMemo) <= 200) {
        $memo = $newMemo;
    }
}

// Save the order
$amount = (float)($cart->getOrderTotal(true, Cart::BOTH));
$bitdrive->validateOrder($cart->id, Configuration::get('BD_OS_PENDING'), $amount, $bitdrive->displayName,
                         null, null, (int) $currency_order->id, false, $customer->secure_key);

$smarty->assign(array(
    'amount' => $amount,
    'bitdrive_id' => $bitdrive->id,
    'bitdrive_url' => $bitdrive->getCheckoutUrl(),
    'cancel_text' => $bitdrive->getL('Cancel'),
    'cart_id' => (int) ($cart->id).'_'.pSQL($cart->secure_key),
    'cart_text' => $bitdrive->getL('My cart'),
    'currency_module' => $currency_module,
    'customer' => $customer,
    'memo' => $memo,    
    'merchant_id' => $merchantId,
    'redirect_text' => $bitdrive->getL('Please wait while we redirect you to BitDrive to make your bitcoin payment...'),
    'return_text' => $bitdrive->getL('Return to shop'),
    'url' => BitDrive::getShopDomainSsl(true, true).__PS_BASE_URI__
));

if (is_file(_PS_THEME_DIR_.'modules/bitdrive/standard/redirect.tpl')) {
    $smarty->display(_PS_THEME_DIR_.'modules/'.$bitdrive->name.'/standard/redirect.tpl');
} else {
    $smarty->display(_PS_MODULE_DIR_.$bitdrive->name.'/standard/redirect.tpl');
}

?>