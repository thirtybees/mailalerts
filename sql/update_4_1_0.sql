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
ALTER TABLE `PREFIX_mailalert_customer_oos` DROP PRIMARY KEY;

ALTER TABLE `PREFIX_mailalert_customer_oos` ADD `id_mailalert_customer_oos` INT(11) unsigned NOT NULL;

ALTER TABLE `PREFIX_mailalert_customer_oos` ADD `date_add` DATETIME NOT NULL;

SET @rownum = 0;
UPDATE `PREFIX_mailalert_customer_oos` SET `id_mailalert_customer_oos` = @rownum:=@rownum+1 WHERE `id_mailalert_customer_oos` = 0;

UPDATE `PREFIX_mailalert_customer_oos` SET `date_add` = now();

ALTER TABLE `PREFIX_mailalert_customer_oos` ADD PRIMARY KEY(`id_mailalert_customer_oos`);

ALTER TABLE `PREFIX_mailalert_customer_oos` MODIFY `id_mailalert_customer_oos` INT(11) unsigned NOT NULL AUTO_INCREMENT;

ALTER TABLE `PREFIX_mailalert_customer_oos` ADD UNIQUE KEY `cust_prod` (`id_customer`,`customer_email`,`id_product`,`id_product_attribute`,`id_shop`);
