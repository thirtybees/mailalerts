<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace MailAlertModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailAlert
 */
class MailAlert extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'mailalert_customer_oos',
        'primary' => 'id_customer',
        'fields'  => [
            'id_customer'          => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'customer_email'       => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'required' => true],
            'id_product'           => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_shop'              => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_lang'              => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
        ],
    ];
    public $id_customer;
    public $customer_email;
    public $id_product;
    public $id_product_attribute;
    public $id_shop;
    public $id_lang;
    // @codingStandardsIgnoreEnd

    /**
     * @param int      $idCustomer
     * @param int      $idProduct
     * @param int      $idProductAttribute
     * @param int|null $idShop
     * @param int|null $idLang
     * @param string   $guestEmail
     *
     * @return int
     */
    public static function customerHasNotification($idCustomer, $idProduct, $idProductAttribute, $idShop = null, $idLang = null, $guestEmail = '')
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        if (!$idLang) {
            $idLang = \Context::getContext()->language->id;
        }

        $customer = new \Customer($idCustomer);
        $customerEmail = $customer->email;
        $guestEmail = pSQL($guestEmail);

        $idCustomer = (int) $idCustomer;
        $customerEmail = pSQL($customerEmail);
        $where = $idCustomer == 0 ? "customer_email = '$guestEmail'" : "(id_customer=$idCustomer OR customer_email='$customerEmail')";
        $sql = '
			SELECT *
			FROM `'._DB_PREFIX_.self::$definition['table'].'`
			WHERE '.$where.'
			AND `id_product` = '.(int) $idProduct.'
			AND `id_product_attribute` = '.(int) $idProductAttribute.'
			AND `id_shop` = '.(int) $idShop;

        return count(\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql));
    }

    /**
     * @param int        $idCustomer
     * @param int        $idLang
     * @param \Shop|null $shop
     *
     * @return array|false|null|\PDOStatement
     */
    public static function getMailAlerts($idCustomer, $idLang, \Shop $shop = null)
    {
        if (!\Validate::isUnsignedId($idCustomer) || !\Validate::isUnsignedId($idLang)) {
            die (\Tools::displayError());
        }

        if (!$shop) {
            $shop = \Context::getContext()->shop;
        }

        $customer = new \Customer($idCustomer);
        $products = static::getProducts($customer, $idLang);
        $productsNumber = count($products);

        if (empty($products) === true || !$productsNumber) {
            return [];
        }

        for ($i = 0; $i < $productsNumber; ++$i) {
            $obj = new \Product((int) $products[$i]['id_product'], false, (int) $idLang);
            if (!\Validate::isLoadedObject($obj)) {
                continue;
            }

            if (isset($products[$i]['id_product_attribute']) &&
                \Validate::isUnsignedInt($products[$i]['id_product_attribute'])
            ) {
                $attributes = self::getProductAttributeCombination($products[$i]['id_product_attribute'], $idLang);
                $products[$i]['attributes_small'] = '';

                if ($attributes) {
                    foreach ($attributes as $row) {
                        $products[$i]['attributes_small'] .= $row['attribute_name'].', ';
                    }
                }

                $products[$i]['attributes_small'] = rtrim($products[$i]['attributes_small'], ', ');
                $products[$i]['id_shop'] = $shop->id;

                /* Get cover */
                $attrgrps = $obj->getAttributesGroups((int) $idLang);
                foreach ($attrgrps as $attrgrp) {
                    if ($attrgrp['id_product_attribute'] == (int) $products[$i]['id_product_attribute']
                        && $images = \Product::_getAttributeImageAssociations((int) $attrgrp['id_product_attribute'])
                    ) {
                        $products[$i]['cover'] = $obj->id.'-'.array_pop($images);
                        break;
                    }
                }
            }

            if (!isset($products[$i]['cover']) || !$products[$i]['cover']) {
                $images = $obj->getImages((int) $idLang);
                foreach ($images as $image) {
                    if ($image['cover']) {
                        $products[$i]['cover'] = $obj->id.'-'.$image['id_image'];
                        break;
                    }
                }
            }

            if (!isset($products[$i]['cover'])) {
                $products[$i]['cover'] = \Language::getIsoById($idLang).'-default';
            }

            $products[$i]['link'] = $obj->getLink();
            $products[$i]['link_rewrite'] = $obj->link_rewrite;
        }

        return ($products);
    }

    /**
     * @param \Customer $customer
     * @param int      $idLang
     *
     * @return array|false|null|\PDOStatement
     */
    public static function getProducts($customer, $idLang)
    {
        $listShopIds = \Shop::getContextListShopID(false);

        $sql = '
			SELECT ma.`id_product`, p.`quantity` AS product_quantity, pl.`name`, ma.`id_product_attribute`
			FROM `'._DB_PREFIX_.self::$definition['table'].'` ma
			JOIN `'._DB_PREFIX_.'product` p ON (p.`id_product` = ma.`id_product`)
			'.\Shop::addSqlAssociation('product', 'p').'
			LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.`id_product` = p.`id_product` AND pl.id_shop IN ('.implode(', ', $listShopIds).'))
			WHERE product_shop.`active` = 1
			AND (ma.`id_customer` = '.(int) $customer->id.' OR ma.`customer_email` = \''.pSQL($customer->email).'\')
			AND pl.`id_lang` = '.(int) $idLang.\Shop::addSqlRestriction(false, 'ma');

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * @param int $idProductAttribute
     * @param int $idLang
     *
     * @return array|false|null|\PDOStatement
     */
    public static function getProductAttributeCombination($idProductAttribute, $idLang)
    {
        $sql = '
			SELECT al.`name` AS attribute_name
			FROM `'._DB_PREFIX_.'product_attribute_combination` pac
			LEFT JOIN `'._DB_PREFIX_.'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
			LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int) $idLang.')
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int) $idLang.')
			LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
			'.\Shop::addSqlAssociation('product_attribute', 'pa').'
			WHERE pac.`id_product_attribute` = '.(int) $idProductAttribute;

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * @param int $idProduct
     * @param int $idProductAttribute
     */
    public static function sendCustomerAlert($idProduct, $idProductAttribute)
    {
        $link = new \Link();
        $context = \Context::getContext()->cloneContext();
        $customers = self::getCustomers($idProduct, $idProductAttribute);

        foreach ($customers as $customer) {
            $idShop = (int) $customer['id_shop'];
            $idLang = (int) $customer['id_lang'];
            $context->shop->id = $idShop;
            $context->language->id = $idLang;

            $product = new \Product((int) $idProduct, false, $idLang, $idShop);
            $productLink = $link->getProductLink($product, $product->link_rewrite, null, null, $idLang, $idShop);
            $templateVars = [
                '{product}'      => (is_array($product->name) ? $product->name[$idLang] : $product->name),
                '{product_link}' => $productLink,
            ];

            if ($customer['id_customer']) {
                $customer = new \Customer((int) $customer['id_customer']);
                $customerEmail = $customer->email;
                $customerId = (int) $customer->id;
            } else {
                $customerId = 0;
                $customerEmail = $customer['customer_email'];
            }

            $mailIso = \Language::getIsoById($idLang);

            $dirMail = false;
            if (file_exists(_PS_THEME_DIR_."modules/mailalerts/mails/$mailIso/customer_qty.txt") &&
                file_exists(_PS_THEME_DIR_."modules/mailalerts/mails/$mailIso/customer_qty.html")) {
                $dirMail = _PS_THEME_DIR_."modules/mailalerts/mails/";
            } elseif (file_exists(__DIR__."/../mails/$mailIso/customer_qty.txt") &&
                file_exists(__DIR__."/../mails/$mailIso/customer_qty.html")
            ) {
                $dirMail = __DIR__.'/../mails/';
            } elseif (file_exists(_PS_MAIL_DIR_.$mailIso.'/customer_qty.txt') &&
                file_exists(_PS_MAIL_DIR_.$mailIso.'/customer_qty.html')) {
                $dirMail = _PS_MAIL_DIR_;
            } elseif (\Language::getIdByIso('en')) {
                $idLang = (int) \Language::getIdByIso('en');
                $dirMail = __DIR__.'/../mails/';
            }

            if ($dirMail) {
                \Mail::Send(
                    $idLang,
                    'customer_qty',
                    \Mail::l('Product available', $idLang),
                    $templateVars,
                    (string) $customerEmail,
                    null,
                    (string) \Configuration::get('PS_SHOP_EMAIL', null, null, $idShop),
                    (string) \Configuration::get('PS_SHOP_NAME', null, null, $idShop),
                    null,
                    null,
                    $dirMail,
                    false,
                    $idShop
                );
            }

            \Hook::exec(
                'actionModuleMailAlertSendCustomer',
                [
                    'product'     => (is_array($product->name) ? $product->name[$idLang] : $product->name),
                    'link'        => $productLink,
                    'customer'    => $customer,
                    'product_obj' => $product,
                ]
            );

            static::deleteAlert((int) $customerId, (string) $customerEmail, (int) $idProduct, (int) $idProductAttribute, $idShop);
        }
    }

    /**
     * @param int $idProduct
     * @param int $idProductAttribute
     *
     * @return array|false|null|\PDOStatement
     */
    public static function getCustomers($idProduct, $idProductAttribute)
    {
        $sql = '
			SELECT id_customer, customer_email, id_shop, id_lang
			FROM `'._DB_PREFIX_.self::$definition['table'].'`
			WHERE `id_product` = '.(int) $idProduct.' AND `id_product_attribute` = '.(int) $idProductAttribute;

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * @param int      $idCustomer
     * @param int      $customerEmail
     * @param int      $idProduct
     * @param int      $idProductAttribute
     * @param int|null $idShop
     *
     * @return bool
     */
    public static function deleteAlert($idCustomer, $customerEmail, $idProduct, $idProductAttribute, $idShop = null)
    {
        $sql = '
			DELETE FROM `'._DB_PREFIX_.self::$definition['table'].'`
			WHERE '.(($idCustomer > 0) ? '(`customer_email` = \''.pSQL($customerEmail).'\' OR `id_customer` = '.(int) $idCustomer.')' :
                '`customer_email` = \''.pSQL($customerEmail).'\'').
            ' AND `id_product` = '.(int) $idProduct.'
			AND `id_product_attribute` = '.(int) $idProductAttribute.'
			AND `id_shop` = '.($idShop != null ? (int) $idShop : (int) \Context::getContext()->shop->id);

        return \Db::getInstance()->execute($sql);
    }

    /**
     * @param \Address $address
     * @param string   $lineSep
     * @param array    $fieldsStyle
     *
     * @return string
     */
    public static function getFormatedAddress(\Address $address, $lineSep, $fieldsStyle = [])
    {
        return \AddressFormat::generateAddress($address, ['avoid' => []], $lineSep, ' ', $fieldsStyle);
    }
}
