<?php
class EsewaStatusCheckModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($this->module->active == false) {
            die($this->module->l('This payment method is not available.'));
        }

        $cart = $this->context->cart;
        $cart_id = $this->context->cart->id;
        // Get the total amount
        $total_amount = $this->context->cart->getOrderTotal(true);
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || $total_amount == 0) {
            Tools::redirect('order?step=1');
        }

        $db = Db::getInstance();
        $existing_row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'esewa` WHERE cart_id = ' . (int)$cart_id);

        if ($existing_row) {
            $transaction_uuid = $existing_row['transaction_uuid'];
        } else {
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
        }


        $transaction_code = $this->esewaPaymentStatusCheckApi($total_amount, $transaction_uuid);

        if ($transaction_code !== null) {
            $payment_status = Configuration::get('PS_OS_PAYMENT');
            $message = "Paid with eSewa.";
            $module_name = $this->module->displayName;
            $currency_id = (int) Context::getContext()->currency->id;
            $secure_key = $this->getSecureKey($transaction_uuid);
            $this->module->validateOrder(
                $cart_id,
                $payment_status,
                $cart->getOrderTotal(),
                $module_name,
                $message,
                array('transaction_id' => $transaction_code),
                $currency_id,
                false,
                $secure_key
            );
        } else {
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'failure'));
            exit();
        }

        $customer_id = $this->getCustomerId($transaction_uuid);
        Context::getContext()->customer = new Customer((int) $customer_id);
        $order_id = Order::getByCartId((int) $cart_id);

        if ($order_id && ($secure_key == $secure_key)) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart_id . '&id_module=' . (int)$this->module->id . '&id_order=' . (int)$this->module->currentOrder . '&key=' . $secure_key);
        
        } else {
            /*
             * An error occured and is shown on a new page.
             */
            return $this->setTemplate('module:esewa/views/templates/front/error.tpl');
        }
    }

    private function esewaPaymentStatusCheckApi($total_amount, $transaction_uuid)
    {
        $esewa_payment_mode = Configuration::get('eSewa_payment_mode');

        // Default Values which are test values
        $esewa_product_code = Configuration::get('eSewa_test_product_code');
        $esewa_status_check_api = 'https://uat.esewa.com.np/api/epay/transaction/status/';

        if ($esewa_payment_mode == '2') {
            // Live values
            $esewa_product_code = Configuration::get('eSewa_live_product_code');
            $esewa_status_check_api = 'https://epay.esewa.com.np/api/epay/transaction/status/';
        }

        $query_string = http_build_query([
            'product_code' => $esewa_product_code,
            'total_amount' => $total_amount,
            'transaction_uuid' => $transaction_uuid
        ]);
        $request_url = $request_url = $esewa_status_check_api . '?' . $query_string;

        $curl = curl_init();

        // Set cURL options
        curl_setopt($curl, CURLOPT_URL, $request_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Execute cURL request
        $response = curl_exec($curl);

        // Close cURL session
        curl_close($curl);

        // Parse JSON response
        $response_data = json_decode($response, true);
        if ($response_data['status'] === 'COMPLETE') {
            return $response_data['ref_id'];
        } else {
            return null;
        }
    }

    protected function getSecureKey($transaction_unique_id)
    {
        $start = strpos($transaction_unique_id, "-cus-");
        $end = strpos($transaction_unique_id, "-seckey-");
        $secure_key = substr($transaction_unique_id, $start + strlen('-cus-'), $end - ($start + strlen('-cus-')));
        return $secure_key;
    }

    protected function getCustomerId($transaction_unique_id)
    {
        $start = strpos($transaction_unique_id, "-cid-");
        $end = strpos($transaction_unique_id, "-cus-");
        $extracted_id = substr($transaction_unique_id, $start + strlen('-cid-'), $end - ($start + strlen('-cid-')));
        return $extracted_id;
    }
}
