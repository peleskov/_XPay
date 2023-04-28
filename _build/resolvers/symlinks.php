<?php
/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */
if ($transport->xpdo) {
    $modx =& $transport->xpdo;

    $dev = MODX_BASE_PATH . 'Extras/XPay/';
    /** @var xPDOCacheManager $cache */
    $cache = $modx->getCacheManager();
    if (file_exists($dev) && $cache) {
        if (!is_link($dev . 'assets/components/xpay')) {
            $cache->deleteTree(
                $dev . 'assets/components/xpay/',
                ['deleteTop' => true, 'skipDirs' => false, 'extensions' => []]
            );
            symlink(MODX_ASSETS_PATH . 'components/xpay/', $dev . 'assets/components/xpay');
        }
        if (!is_link($dev . 'core/components/xpay')) {
            $cache->deleteTree(
                $dev . 'core/components/xpay/',
                ['deleteTop' => true, 'skipDirs' => false, 'extensions' => []]
            );
            symlink(MODX_CORE_PATH . 'components/xpay/', $dev . 'core/components/xpay');
        }
    }
}

return true;