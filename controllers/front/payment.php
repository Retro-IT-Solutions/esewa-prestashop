<?php
class EsewaPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($this->module->active == false) {
            die($this->module->l('This payment method is not available.'));
        }

        $cart = $this->context->cart;
        // Get the total amount
        $total_amount = $this->context->cart->getOrderTotal(true);
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || $total_amount == 0) {
            Tools::redirect('order?step=1');
        }


        $esewa_payment_mode = Configuration::get('eSewa_payment_mode');

        // Default Values which are test values
        $esewa_url = 'https://rc-epay.esewa.com.np/api/epay/main/v2/form';
        $esewa_product_code = Configuration::get('eSewa_test_product_code');
        $esewa_merchant_secret = Configuration::get('eSewa_test_merchant_secret');

        if ($esewa_payment_mode == '2') {
            // Live values
            $esewa_url = 'https://epay.esewa.com.np/api/epay/main/v2/form';
            $esewa_product_code = Configuration::get('eSewa_live_product_code');
            $esewa_merchant_secret = Configuration::get('eSewa_live_merchant_secret');
        }

        // Get the total amount of products in the cart
        $total_products_amount = $this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);

        // Get the total shipping cost
        $total_delivery_charge = $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING);

        // Get the total tax amount applied on products in the cart
        $total_tax_amount = $this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS) - $this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);

        $product_service_charge = 0;

        // Calculate the total amount including tax, service charge, and delivery charge
        $product_code = $esewa_product_code;
        $cart_id = $this->context->cart->id;
        $customer_id = $this->context->customer->id;
        $transaction_unique_id = $cart_id . '-cid-' . $customer_id . '-cus-' . $this->context->customer->secure_key . '-seckey-' . uniqid();
        $failure_url = $this->context->link->getModuleLink($this->module->name, 'failure', array('cart_id' => $cart_id, 'secure_key' => $this->context->customer->secure_key));
        $success_url = $this->context->link->getModuleLink($this->module->name, 'success');
        $signed_filed_names = "total_amount,transaction_uuid,product_code";
        $signature = $this->getEsewaSignature(sprintf("%.2f", $total_amount), $transaction_unique_id, $product_code, $esewa_merchant_secret);

        $this->context->smarty->assign(array(
            'esewa_url' => $esewa_url,
            'total_products_amount' => $total_products_amount,
            'total_tax_amount' => $total_tax_amount,
            'total_delivery_charge' => $total_delivery_charge,
            'product_service_charge' => $product_service_charge,
            'total_amount' => $total_amount,
            'product_code' => $product_code,
            'transaction_unique_id' => $transaction_unique_id,
            'failure_url' => $failure_url,
            'success_url' => $success_url,
            'signed_filed_names' => $signed_filed_names,
            'signature' => $signature
        ));

        $this->module->validateOrder(
            $cart_id,
            Configuration::get('PS_OS_BANKWIRE'),
            $cart->getOrderTotal(),
            $this->module->displayName,
            "Paid with eSewa.",
            array('transaction_id' => null),
            (int) Context::getContext()->currency->id,
            false,
            $this->context->customer->secure_key
        );
        $order_id = Order::getOrderByCartId($cart_id);
        $this->savePaymentMetaData($cart_id, $transaction_unique_id);

        if ($order_id) {
            $this->setTemplate('module:esewa/views/templates/front/payment_form.tpl');
        } else {
            return $this->setTemplate('module:esewa/views/templates/front/payment_failure.tpl');
        }
    }

    private function getEsewaSignature($total_amount, $transaction_unique_id, $product_code, $esewa_merchant_secret)
    {
        $input_string = "total_amount={$total_amount},transaction_uuid={$transaction_unique_id},product_code={$product_code}";
        $merchant_secret = htmlspecialchars_decode($esewa_merchant_secret);
        $signature = hash_hmac('sha256', $input_string, $merchant_secret, true);
        $base64_signature = base64_encode($signature);
        return $base64_signature;
    }

    // save payment with cart id and transaction id in database
    private function savePaymentMetaData($cart_id, $transaction_uuid)
    {
        $db = Db::getInstance();
        $existing_row = $db->getValue('SELECT id_esewa FROM `' . _DB_PREFIX_ . 'esewa` WHERE cart_id = ' . (int)$cart_id);

        if ($existing_row) {
            $query = 'UPDATE `' . _DB_PREFIX_ . 'esewa` SET `transaction_uuid` = \'' . pSQL($transaction_uuid) . '\' WHERE `cart_id` = ' . (int)$cart_id;
        } else {
            $query = 'INSERT INTO `' . _DB_PREFIX_ . 'esewa` (`cart_id`, `transaction_uuid`) VALUES (' . (int)$cart_id . ', \'' . pSQL($transaction_uuid) . '\')';
        }
        $db->execute($query);
    }
}
