<?php
$sql = array();
$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'esewa`';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}