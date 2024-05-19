<?php

use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Current;

class EsewaSuccessModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($this->module->active == false) {
            die;
        }

        // Default Values which are test values
        $esewa_product_code = Configuration::get('eSewa_test_product_code');
        $esewa_merchant_secret = Configuration::get('eSewa_test_merchant_secret');
        $esewa_payment_mode = Configuration::get('eSewa_payment_mode');

        // Live Mode Check
        if ($esewa_payment_mode == 2) {
            $esewa_product_code = Configuration::get('eSewa_live_product_code');
            $esewa_merchant_secret = Configuration::get('eSewa_live_merchant_secret');
        }

        $encode_data = Tools::getValue('data');
        if (!isset($encode_data) || empty($encode_data)) {
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
            exit();
        }

        // Decode response
        $json_payload = base64_decode($encode_data);
        $transaction_data = json_decode($json_payload, true);
        $cart_id = $this->getCartId($transaction_data['transaction_uuid']);
        $cart = new Cart((int) $cart_id);
        $customer_id = $this->getCustomerId($transaction_data['transaction_uuid']);
        $total_amount = $this->context->cart->getOrderTotal(true);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || $total_amount == 0) {
            Tools::redirect('order?step=1');
        }

        if (!$this->isTransactionDataValid($transaction_data, $esewa_merchant_secret, $esewa_product_code, $total_amount)) {
            $failure_url = $this->context->link->getModuleLink($this->module->name, 'failure');
            Tools::redirect($failure_url);
            exit();
        }

        $transaction_code = $transaction_data['transaction_code'];
        $esewa_secure_key = $this->getSecureKey($transaction_data['transaction_uuid']);
        $total_amount = $transaction_data['total_amount'];

        Context::getContext()->cart = new Cart((int) $cart_id);
        Context::getContext()->customer = new Customer((int) $customer_id);
        Context::getContext()->language = new Language((int) Context::getContext()->customer->id_lang);
        Context::getContext()->currency = new Currency((int) Context::getContext()->cart->id_currency);

        $secure_key = Context::getContext()->customer->secure_key;
        $customer = new Customer((int) $customer_id);

        if ($this->isValidOrder($cart, $customer, $total_amount)) {
            $payment_status = Configuration::get('PS_OS_PAYMENT');
            $message = "Paid with eSewa.";
        } else {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $message = 'An error occured while processing payment.';
        }

        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;
        // validate order
        $this->module->validateOrder($cart_id, $payment_status, $cart->getOrderTotal(), $module_name, $message, array('transaction_id' => $transaction_code), $currency_id, false, $secure_key);

        // If the order has been validated we try to retrieve it
        $order_id = Order::getByCartId((int) $cart_id);

        if ($order_id && ($secure_key == $esewa_secure_key)) {
            //The order has been placed so we redirect the customer on the confirmation page.
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart_id . '&id_module=' . (int)$this->module->id . '&id_order=' . (int)$this->module->currentOrder . '&key=' . $secure_key);
        } else {
            /*
             * An error occured and is shown on a new page.
             */
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
        }
    }

    protected function isValidOrder($cart, $customer, $total_amount)
    {

        if (!Validate::isLoadedObject($cart)) {
            return false;
        }

        if (!Validate::isLoadedObject($customer)) {
            return false;
        }
        return true;
    }

    // extract cart id from transaction uuid
    protected function getCartId($transaction_unique_id)
    {
        $position = strpos($transaction_unique_id, '-cid-');
        $extracted_id = substr($transaction_unique_id, 0, $position);
        return $extracted_id;
    }

    // extract customer id from tracsaction uuid
    protected function getCustomerId($transaction_unique_id)
    {
        $start = strpos($transaction_unique_id, "-cid-");
        $end = strpos($transaction_unique_id, "-cus-");
        $extracted_id = substr($transaction_unique_id, $start + strlen('-cid-'), $end - ($start + strlen('-cid-')));
        return $extracted_id;
    }

    // extract secure key
    protected function getSecureKey($transaction_unique_id)
    {
        $start = strpos($transaction_unique_id, "-cus-");
        $end = strpos($transaction_unique_id, "-seckey-");
        $secure_key = substr($transaction_unique_id, $start + strlen('-cus-'), $end - ($start + strlen('-cus-')));
        return $secure_key;
    }

    protected function isTransactionDataValid($transaction_data, $esewa_merchant_secret, $esewa_product_code, $total_amount)
    {
        if (!isset($transaction_data['status']) || empty($transaction_data['status']) || $transaction_data === 'COMPLETE') {
            return false;
        }

        $esewa_signature = $transaction_data['signature'];
        $merchant_signature = $this->generateSignature($transaction_data, $esewa_merchant_secret, $esewa_product_code, $total_amount);
        if ($esewa_signature !== $merchant_signature) {
            return false;
        }
        return true;
    }

    protected function generateSignature($transaction_data, $esewa_merchant_secret, $esewa_product_code, $total_amount)
    {
        $merchant_secret = htmlspecialchars_decode($esewa_merchant_secret);
        $input_string = "transaction_code={$transaction_data['transaction_code']},status={$transaction_data['status']},total_amount={$total_amount},transaction_uuid={$transaction_data['transaction_uuid']},product_code={$esewa_product_code},signed_field_names={$transaction_data['signed_field_names']}";
        $signature = hash_hmac('sha256', $input_string, $merchant_secret, true);
        $base64_signature = base64_encode($signature);
        return $base64_signature;
    }
}
