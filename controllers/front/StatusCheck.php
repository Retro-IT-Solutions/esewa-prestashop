<?php
class EsewaStatusCheckModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($this->module->active == false) {
            die($this->module->l('This payment method is not available.'));
        }
        $order_id = Tools::getValue('order_id');
        $office = Tools::getValue('office');
        $order = new Order($order_id);

        // Get the total amount
        $total_amount = sprintf("%.2f", $order->total_paid);
        if (!$order_id && !Validate::isLoadedObject($order)) {
            if(isset($office) && !empty($office)) {
                header("Location:".$office);
                exit;
            }
            Tools::redirect('order?step=1');
        }

        $cart_id = $order->id_cart;

        $db = Db::getInstance();
        $existing_row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'esewa` WHERE cart_id = ' . (int)$cart_id);

        if ($existing_row) {
            $transaction_uuid = $existing_row['transaction_uuid'];
        } else {
            if(isset($office) && !empty($office)) {
                header("Location:".$office);
                exit;
            }
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'failure'));
            exit();
        }

        $customer_id = $this->getCustomerId($transaction_uuid);
        $customer = new Customer((int) $customer_id);

        if (!$this->isValidOrder($order, $customer)) {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $order->setCurrentState($payment_status);
            $order->update();
            if(isset($office) && !empty($office)) {
                header("Location:".$office);
                exit;
            }
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'failure'));
            exit();
        }


        $transaction_code = $this->esewaPaymentStatusCheckApi($total_amount, $transaction_uuid);

        if ($transaction_code !== null) {
            $esewa_secure_key = $this->getSecureKey($transaction_uuid);
            $secure_key = $order->secure_key;
            $payment_status = Configuration::get('PS_OS_PAYMENT');
        } else {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $order->setCurrentState($payment_status);
            $order->update();
            if(isset($office) && !empty($office)) {
                header("Location:".$office);
                exit;
            }
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'failure'));
            exit();
        }

        $order->setCurrentState($payment_status);
        $payments = $order->getOrderPayments();
        if (count($payments) > 0) {
            $payment = $payments[0];
            $payment->transaction_id = $transaction_code;
            $payment->update();
        }

        if ($order->update() && ($esewa_secure_key == $secure_key)) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */
            if(isset($office) && !empty($office)) {
                header("Location:".$office);
                exit;
            }
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart_id . '&id_module=' . (int)$this->module->id . '&id_order=' . $order_id . '&key=' . $secure_key);
        
        } else {
            /*
             * An error occured and is shown on a new page.
             */
            if(isset($office) && !empty($office)) {
                header("Location:".$office);
                exit;
            }
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
        // print_r($response_data);
        // exit;
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
}
