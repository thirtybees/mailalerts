/**
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */
CREATE TABLE IF NOT EXISTS `PREFIX_mailalert_customer_oos` (
  `id_mailalert_customer_oos` int unsigned NOT NULL AUTO_INCREMENT,
  `id_customer` INT(11) unsigned NOT NULL,
  `customer_email` VARCHAR(128) NOT NULL,
  `id_product` INT(11) unsigned NOT NULL,
  `id_product_attribute` INT(11) unsigned NOT NULL,
  `id_shop` INT(11) unsigned NOT NULL,
  `id_lang` INT(11) unsigned NOT NULL,
  `date_add` DATETIME NOT NULL,
  UNIQUE KEY `cust_prod` (`id_customer`,`customer_email`,`id_product`,`id_product_attribute`,`id_shop`),
  PRIMARY KEY (`id_mailalert_customer_oos`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=CHARSET_TYPE COLLATE=COLLATE_TYPE;