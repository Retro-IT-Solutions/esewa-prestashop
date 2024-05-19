<?php
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'esewa` (
    `id_esewa` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_esewa`),
    `cart_id` INT NOT NULL,
    `transaction_uuid` VARCHAR(255) NOT NULL
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}