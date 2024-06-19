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
        $customer_id = $this->getCustomerId($transaction_data['transaction_uuid']);
        $order_id = Order::getOrderByCartId($cart_id);
        $order = new Order($order_id);
        $total_amount = sprintf("%.2f", $order->total_paid);

        if (!$order_id) {
            Tools::redirect('order?step=1');
        }

        if (!$this->isTransactionDataValid($transaction_data, $esewa_merchant_secret, $esewa_product_code, $total_amount)) {
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
        }

        $transaction_code = $transaction_data['transaction_code'];
        $esewa_secure_key = $this->getSecureKey($transaction_data['transaction_uuid']);
        $total_amount = $transaction_data['total_amount'];

        Context::getContext()->customer = new Customer((int) $customer_id);

        $secure_key = Context::getContext()->customer->secure_key;
        $customer = new Customer((int) $customer_id);

        if ($this->isValidOrder($order, $customer)) {
            $payment_status = Configuration::get('PS_OS_PAYMENT');
        } else {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $order->setCurrentState($payment_status);
            $order->update();
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
        }

        // change status
        $order->setCurrentState($payment_status);
        $payments = $order->getOrderPayments();
        if (count($payments) > 0) {
            $payment = $payments[0];
            $payment->transaction_id = $transaction_code;
            $payment->update();
        }

        if ($order->update() && ($secure_key == $esewa_secure_key)) {
            //The order has been placed so we redirect the customer on the confirmation page.
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart_id . '&id_module=' . (int)$this->module->id . '&id_order=' . $order_id . '&key=' . $secure_key);
        } else {
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
        }
    }

    protected function isValidOrder($order, $customer)
    {
        if (!Validate::isLoadedObject($order)) {
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
