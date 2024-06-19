<?php

class EsewaFailureModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        $order_id = Order::getOrderByCartId($cart_id);
        $order = new Order($order_id);

        if ($order->secure_key === $secure_key) {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $order->setCurrentState($payment_status);
            $order->update();
            $this->setTemplate('module:esewa/views/templates/front/payment_failure.tpl');
        }

        $this->setTemplate('module:esewa/views/templates/front/payment_failure.tpl');
    }
}
