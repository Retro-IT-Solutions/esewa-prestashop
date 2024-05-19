<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Esewa extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'esewa';
        $this->tab = 'payments_gateways';
        $this->author = 'Retro IT Solutions';
        $this->version = '1.0.0';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('eSewa');
        $this->description = $this->l('Accept payments through eSewa.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        include_once($this->local_path . 'sql/install.php');
        if (
            !parent::install()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayShoppingCart')
            || !$this->registerHook('displayCheckout')
        ) {
            return false;
        }
        Configuration::updateValue('eSewa_payment_mode', '1');
        Configuration::updateValue('eSewa_test_product_code', 'EPAYTEST');
        Configuration::updateValue('eSewa_test_merchant_secret', '8gBm/:&EnhH.1/q');
        Configuration::updateValue('eSewa_live_product_code', null);
        Configuration::updateValue('eSewa_live_merchant_secret', null);
        return true;
    }

    public function uninstall()
    {
        include_once($this->local_path . 'sql/uninstall.php');
        Configuration::deleteByName('eSewa_payment_mode');
        Configuration::deleteByName('eSewa_test_product_code');
        Configuration::deleteByName('eSewa_test_merchant_secret');
        Configuration::deleteByName('eSewa_live_product_code');
        Configuration::deleteByName('eSewa_live_merchant_secret');

        return parent::uninstall();
    }

    public function getLogo()
    {
        return $this->_path . '/logo.png';
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $currency = $this->context->currency;
        if ($currency->iso_code != 'NPR') {
            return;
        }
        $additiona_information = Configuration::get('eSewa_payment_mode') == 2 ? 'Payment via eSewa, Securly and Relaibly.' : 'Payment via eSewa, Securly and Relaibly TEST MODE ENABLED. You can use testing accounts only';
        $payment_option = new PaymentOption();
        $payment_option->setModuleName($this->name)
            ->setCallToActionText('Pay by eSewa')
            ->setAdditionalInformation($additiona_information)
            ->setLogo(Media::getMediaPath($this->_path . 'views/img/eSewa_logo.png'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment'));
        return [$payment_option];
    }

    public function hookDisplayShoppingCart($params)
    {
        $cart_id = $this->context->cart->id;

        $db = Db::getInstance();
        $existing_row = $db->getValue('SELECT id_esewa FROM `' . _DB_PREFIX_ . 'esewa` WHERE cart_id = ' . (int)$cart_id);

        if ($existing_row) {
            $status_check_url = $this->context->link->getModuleLink($this->name, 'StatusCheck');

            $this->context->smarty->assign(array(
                'status_check_url' => $this->l($status_check_url),
                'button_text' => $this->l('Payment Check'),
                'esewa_image' => $this->_path . 'views/img/eSewa_logo.png'
            ));

            return $this->display(__FILE__, 'views/templates/hook/shopping_cart_button.tpl');
        }

        return;
    }

    public function hookDisplayCheckout($params)
    {
        $cart_id = $this->context->cart->id;

        $db = Db::getInstance();
        $existing_row = $db->getValue('SELECT id_esewa FROM `' . _DB_PREFIX_ . 'esewa` WHERE cart_id = ' . (int)$cart_id);

        if ($existing_row) {
            return $this->context->link->getModuleLink($this->name, 'statusCheck');
            $status_check_url = $this->context->link->getModuleLink($this->name, 'statusCheck');

            $this->context->smarty->assign(array(
                'status_check_url' => $this->l($status_check_url),
                'button_text' => $this->l('Payment Check'),
                'esewa_image' => $this->_path . 'views/img/eSewa_logo.png'
            ));

            return $this->display(__FILE__, 'views/templates/hook/shopping_cart_button.tpl');
        }

        return;
    }

    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitEsewaModule'))) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->controller->addJs($this->_path . 'views/js/back.js');
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEsewaModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'lanuages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('eSewa Mode'),
                        'name' => 'eSewa_payment_mode',
                        'hint' => $this->l('Choose Between Live Mode or Test Mode'),
                        'desc' => $this->l('eSewa payment mode'),
                        'options' => array(
                            'query' => $options = array(
                                array(
                                    'id_option' => 1,
                                    'name' => 'Test Mode'
                                ),
                                array(
                                    'id_option' => 2,
                                    'name' => 'Live Mode'
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-code"></i>',
                        'desc' => $this->l('Enter a valid product code'),
                        'name' => 'eSewa_live_product_code',
                        'label' => $this->l('Product Code'),
                        'id' => 'eSewa_live_product_code',
                        // 'required' => true, 
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter a valid merchant secret'),
                        'name' => 'eSewa_live_merchant_secret',
                        'label' => $this->l('Merchant Secret'),
                        'id' => 'eSewa_live_merchant_secret',
                        // 'required' => true, 
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-code"></i>',
                        'desc' => $this->l('Test Product Code'),
                        'name' => 'eSewa_test_product_code',
                        'label' => $this->l('Test Product Code'),
                        'id' => 'eSewa_test_product_code',
                        // 'required' => true, 
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Test Merchant Secret'),
                        'name' => 'eSewa_test_merchant_secret',
                        'label' => $this->l('Test Merchant Secret'),
                        'id' => 'eSewa_test_merchant_secret',
                        // 'required' => true, 
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'eSewa_payment_mode' => Configuration::get('eSewa_payment_mode', '1'),
            'eSewa_test_product_code' => Configuration::get('eSewa_test_product_code', 'EPAYTEST'),
            'eSewa_test_merchant_secret' => Configuration::get('eSewa_test_merchant_secret', '8gBm/:&EnhH.1/q'),
            'eSewa_live_product_code' => Configuration::get('eSewa_live_product_code'),
            'eSewa_live_merchant_secret' => Configuration::get('eSewa_live_merchant_secret'),
        );
    }

    protected function postProcess()
    {
        $esewa_test_product_code = Tools::getValue('eSewa_test_product_code');
        $esewa_test_merchant_secret = Tools::getValue('eSewa_test_merchant_secret');
        $esewa_live_product_code = Tools::getValue('eSewa_live_product_code');
        $esewa_live_merchant_secret = Tools::getValue('eSewa_live_merchant_secret');
        $esewa_payment_mode = Tools::getValue('eSewa_payment_mode');
        if (!empty($esewa_test_product_code) && !empty($esewa_test_merchant_secret) && !empty($esewa_live_product_code) && !empty($esewa_live_merchant_secret)) {
            Configuration::updateValue('eSewa_test_product_code', $esewa_test_product_code);
            Configuration::updateValue('eSewa_test_merchant_secret', $esewa_test_merchant_secret);
            Configuration::updateValue('eSewa_live_product_code', $esewa_live_product_code);
            Configuration::updateValue('eSewa_live_merchant_secret', $esewa_live_merchant_secret);
        }
        Configuration::updateValue('eSewa_payment_mode', $esewa_payment_mode);
    }
}
