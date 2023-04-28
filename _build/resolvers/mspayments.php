<?php

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */

if ($transport->xpdo) {
    $modx = &$transport->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            if ($miniShop2 = $modx->getService('miniShop2')) {
                $miniShop2->addService(
                    'payment',
                    'XPayment',
                    '{core_path}components/xpay/handlers/xpay.paymenthandler.class.php'
                );
                if (!$payment = $modx->getObject('msPayment', ['name' => 'XPayment'])) {
                    $payment = $modx->newObject('msPayment');
                    $payment->fromArray(array(
                        'name' => 'XPayment', 'active' => 1, 'class' => 'XPayment',
                    ));
                    $payment->save();
                }
            }
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            if ($miniShop2 = $modx->getService('miniShop2')) {
                $miniShop2->removeService('payment', 'XPayment');
            }
            if ($payment = $modx->getObject('msPayment', ['name' => 'XPayment'])) {
                $payment->remove();
            }            
            break;
    
    }
}
return true;
