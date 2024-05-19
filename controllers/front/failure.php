<?php

class EsewaFailureModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Set the template
        $this->setTemplate('module:esewa/views/templates/front/payment_failure.tpl');
    }
}
