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
ALTER TABLE `PREFIX_mailalert_customer_oos`
  ADD COLUMN `ip_hash` VARBINARY(32) NULL AFTER `id_lang`,
  ADD COLUMN `ip_mask` VARBINARY(16) NULL AFTER `ip_hash`,
  ADD COLUMN `user_agent` VARCHAR(255) NULL AFTER `ip_mask`;

CREATE INDEX `idx_mailalert_oos_hash` ON `PREFIX_mailalert_customer_oos` (`ip_hash`);
CREATE INDEX `idx_mailalert_oos_prod_shop` ON `PREFIX_mailalert_customer_oos` (`id_product`,`id_product_attribute`,`id_shop`);
CREATE INDEX `idx_mailalert_oos_date` ON `PREFIX_mailalert_customer_oos` (`date_add`);
