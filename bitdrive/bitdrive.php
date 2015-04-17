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

if (!defined('_PS_VERSION_')) {
    exit;
}

class BitDrive extends PaymentModule {
    /**
     * The configuration key for the "Pending Payment" order status.
     * @type string
     */
    const BD_OS_PENDING = 'BD_OS_PENDING';
    
    /**
     * The configuration key for the "Expired" order status.
     * @type string
     */
    const BD_OS_EXPIRED = 'BD_OS_EXPIRED';
    
    /**
     * The "Pending Payment" order status string.
     * @type string
     */
    const OS_PENDING = 'Pending Payment';
    
    /**
     * The "Expired" order status string.
     * @type string
     */
    const OS_EXPIRED = 'Expired';
    
    /**
     * The "BITDRIVE_IPN_SECRET" configuration key.
     * @type string
     */
    const IPN_SECRET_KEY = 'BITDRIVE_IPN_SECRET';
    
    /**
     * The "BITDRIVE_MERCHANT_ID" configuration key.
     * @type string
     */
    const MERCHANT_ID_KEY = 'BITDRIVE_MERCHANT_ID';
    
    /**
     * Errors with POST data on the admin configuration page.
     * @type array
     */
    private $_postErrors = array();
    
    /**
     * The BitDrive checkout URL.
     * @type string
     */
    private $_checkoutUrl = 'https://www.bitdrive.io/pay';
    
    /**
     * The HTML to be displayed for admin configuration.
     * @type string
     */
    private $_html = '';
    
    /**
     * The BitDrive merchant ID.
     * @type string
     */
    private $_merchantId;
    
    /**
     * The IPN secret to verify notifications from BitDrive.
     * @type string
     */
    private $_ipnSecret;
    
    /**
     * Default constructor to create a new instance.
     */
    public function __construct() {
        $this->author = 'IntricateWare Inc.';
        $this->name = 'bitdrive';
	$this->ps_versions_compliancy = array('min' => '1.5'); 
        $this->tab = 'payments_gateways';
        $this->version = '1.015.0415';

	$this->currencies = true;
	$this->currencies_mode = 'radio';
        
        $config = Configuration::getMultiple(array(self::MERCHANT_ID_KEY, self::IPN_SECRET_KEY));
	$this->_merchantId = isset($config[self::MERCHANT_ID_KEY]) ? $config[self::MERCHANT_ID_KEY] : null;
        $this->_ipnSecret = isset($config[self::IPN_SECRET_KEY]) ? $config[self::IPN_SECRET_KEY] : null;

        parent::__construct();

        $this->_errors = array();
	$this->page = basename(__FILE__, '.php');
        
        $this->displayName = $this->l('BitDrive Payments');
        $this->description = $this->l('Accept bitcoin payments using BitDrive Standard Checkout.');
        $this->confirmUninstall = $this->l('Are you sure you want to remove this payment method?');
	
        if (!isset($this->_merchantId)) {
	    $this->warning = $this->l('The merchant ID must be configured in order to use this module correctly.');
        }
        
	if (!sizeof(Currency::checkPaymentCurrencies($this->id))) {
	    $this->warning = $this->l('No currency set for this payment method.');
	}
    }
    
    /**
     * Define the extra order states for the payment module.
     */
    public function defineOrderStates() {
        $this->_defineOrderState(self::BD_OS_PENDING, 'Pending Payment', 'Cyan');
        $this->_defineOrderState(self::BD_OS_EXPIRED, 'Expired', 'Magenta');
    }
    
    /**
     * Get the checkout URL.
     * 
     * @return string
     */
    public function getCheckoutUrl() {
        return $this->_checkoutUrl;
    }
    
    /**
     * Get the HTML content for the admin configuration.
     *
     * @return string
     */
    public function getContent() {
        $this->_html .= '<h2>'.$this->l('BitDrive Payments').'</h2>';
        if (Tools::isSubmit('btnSubmit'))
        {
            $this->_postValidation();
            if (!sizeof($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors AS $err) {
                    $this->_html .= '<div class="alert error">'. $err .'</div>';
                }
            }
        } else {
            $this->_html .= '<br />';
        }
        
        $this->_displayBitDrive();
        $this->_displayForm();
        
        return $this->_html;
    }
    
    /**
     * Get the translation of a particular string.
     *
     * @param string $key
     * 
     * @return string
     */
    public function getL($key)
    {
        $translations = array(
            'Cancel' => $this->l('Cancel'),
            'My cart' => $this->l('My cart'),
            'Return to shop' => $this->l('Return to shop'),
        );
        
        if (!isset($translations[$key])) {
          return $key;
        }
        
	return $translations[$key];
    }
    
    /**
     * Get the URL for the PrestaShop installation with HTTPS enabled if available.
     *
     * @return string
     */
    public static function getShopDomainSsl($http = false, $entities = false)
    {
        if (!($domain = Configuration::get('PS_SHOP_DOMAIN_SSL'))) {
            $domain = Tools::getHttpHost();
        }
        if ($entities) {
            $domain = htmlspecialchars($domain, ENT_COMPAT, 'UTF-8');
        }
        if ($http) {
            $domain = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').$domain;
        }
        
        return $domain;
    }
    
    /**
     * The "payment" hook for the payment module.
     *
     * @return mixed
     */
    public function hookPayment($params) {
	if (!$this->active) {
	    return;
	}
        
        $this->defineOrderStates();
        return $this->display(__FILE__, 'standard/bitdrive.tpl');
    }
    
    /**
     * The "paymentReturn" hook for the payment module.
     *
     * @return mixed
     */
    public function hookPaymentReturn($params) {
        if (!$this->active) {
            return;
        }

        return $this->display(__FILE__, 'confirmation.tpl');
    }
    
    /**
     * Install the payment module and register the necessary hooks.
     *
     * @return boolean true on success, false otherwise
     */
    public function install() {
        if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn')) {
            return false;
        }
        
        return true;
    }

    /**
     * Uninstall the payment module.
     *
     * @return boolean true on success, false otherwise
     */
    public function uninstall() {
        if (!Configuration::deleteByName(self::MERCHANT_ID_KEY)
            || !Configuration::deleteByName(self::IPN_SECRET_KEY)
            || !Configuration::deleteByName(self::BD_OS_PENDING)
            || !Configuration::deleteByName(self::BD_OS_EXPIRED) 
            || !parent::uninstall()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Define a specific order state.
     *
     * @param string $stateConsant
     * @param string $stateName
     * @param string $colour
     */
    private function _defineOrderState($stateConstant, $stateName, $colour) {
        if (!Configuration::get($stateConstant)) {
            $row = Db::getInstance()->getRow(
                'SELECT `id_order_state` FROM `' . _DB_PREFIX_ .'order_state_lang` ' .
                'WHERE id_lang = 1 AND  name = \''.pSQL($stateName).'\''
            );
            if ($row && isset($row['id_order_state']) && intval($row['id_order_state']) > 0) {
		// order status exists in the table - define it.
		Configuration::updateValue($stateConstant, $row['id_order_state']);
            } else {
                Db::getInstance()->Execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state` (`unremovable`, `color`) VALUES (1, \'' . pSQL($colour) . '\')'
                );
		$stateId = Db::getInstance()->Insert_ID();
                
		Db::getInstance()->Execute(
                    'INSERT INTO `'._DB_PREFIX_.'order_state_lang` (`id_order_state`, `id_lang`, `name`) ' .
                    'VALUES(' . intval($stateId) . ', 1, \'' . pSQL($stateName) . '\')');
		
                Configuration::updateValue($stateConstant, $stateId);
            }
        }
    }
    
    /**
     * Update the HTML with the BitDrive payment module administration header and description.
     */
    private function _displayBitDrive() {
        $this->_html .= '<img src="../modules/bitdrive/bitdrive.png" style="float:left; margin-right:15px"><strong>' .
            $this->l('Accept bitcoin payments using BitDrive Standard Checkout.').'</strong><br /><br />' .
            $this->l('The BitDrive Payments extension enables the ability to accept bitcoin payments at checkout. ') .
            '<br />' .
            $this->l('The module provides the BitDrive Standard Checkout integration with support for '
                     . 'Instant Payment Notification (IPN) messages.').
            '<br /><br /><br />';
    }
    
    /**
     * Update the HTML with the BitDrive payment module administration configuration form.
     */
    private function _displayForm() {
        $this->_html .=
            '<form action="'.Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']).'" method="post">
                <fieldset>
                    <legend>'.$this->l('Settings').'</legend>
                    <table border="0" width="500" cellpadding="6" cellspacing="0" id="form">
                        <tr>
                            <td>'.$this->l('Merchant ID').'</td>
                            <td><input type="text" name="merchant_id" value="' .
                                htmlentities(Tools::getValue('merchant_id', $this->_merchantId), ENT_COMPAT, 'UTF-8') .
                                '" size="40" />
                            </td>
                        </tr>
                        <tr>
                            <td>'.$this->l('IPN Secret').'</td>
                            <td><input type="text" name="ipn_secret" value="' .
                                htmlentities(Tools::getValue('ipn_secret', $this->_ipnSecret), ENT_COMPAT, 'UTF-8') .
                                '" />
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center">
                                <input class="button" name="btnSubmit" value="'.$this->l('Update settings').'" type="submit" />
                            </td>
                        </tr>
                    </table>
                </fieldset>
            </form>';
    }
    
    /**
     * Process the data after the admin configuration form is submitted.
     */
    private function _postProcess() {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::BITDRIVE_MERCHANT_ID, Tools::getValue('merchant_id'));
            Configuration::updateValue(self::BITDRIVE_IPN_SECRET, Tools::getValue('ipn_secret'));
        }
        
        $this->_html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('ok').'" /> '.
            $this->l('Configuration settings updated').'</div>';
    }
    
    /**
     * Validate the admin configuration form.
     */
    private function _postValidation() {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('merchant_id')) {
                $this->_postErrors[] = $this->l('Merchant ID is required.');
            }
        }
    }
}
    
?>