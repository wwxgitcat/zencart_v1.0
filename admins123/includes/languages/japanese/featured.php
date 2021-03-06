<?php
/**
 * @package admin
 * @copyright Copyright 2003-2010 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: featured.php 18695 2011-05-04 05:24:19Z drbyte $
 * @Simplified Chinese version   http://www.zen-cart.cn
 */
define('HEADING_TITLE', '推荐商品');

define('TABLE_HEADING_PRODUCTS', '商品名称');
define('TABLE_HEADING_PRODUCTS_MODEL', '型号');
define('TABLE_HEADING_PRODUCTS_PRICE', '价格/特价/促销');
define('TABLE_HEADING_PRODUCTS_PERCENTAGE', '百分比');
define('TABLE_HEADING_AVAILABLE_DATE', '生效日');
define('TABLE_HEADING_EXPIRES_DATE', '到期日');
define('TABLE_HEADING_STATUS', '状态');
define('TABLE_HEADING_ACTION', '操作');

define('TEXT_FEATURED_PRODUCT', '商品:');
define('TEXT_FEATURED_EXPIRES_DATE', '到期日:');
define('TEXT_FEATURED_AVAILABLE_DATE', '生效日:');

define('TEXT_INFO_DATE_ADDED', '添加日期:');
define('TEXT_INFO_LAST_MODIFIED', '最后编辑:');
define('TEXT_INFO_NEW_PRICE', '新价格:');
define('TEXT_INFO_ORIGINAL_PRICE', '原价:');
define('TEXT_INFO_PERCENTAGE', '百分比:');
define('TEXT_INFO_AVAILABLE_DATE', '生效日:');
define('TEXT_INFO_EXPIRES_DATE', '到期日:');
define('TEXT_INFO_STATUS_CHANGE', '状态改变:');
define('TEXT_IMAGE_NONEXISTENT', '没有图像存在');

define('TEXT_INFO_HEADING_DELETE_FEATURED', '删除推荐商品');
define('TEXT_INFO_DELETE_INTRO', '你确定要删除推荐商品?');

define('SUCCESS_FEATURED_PRE_ADD', '成功: 预添加推荐商品 ... 请更新日期 ...');
define('WARNING_FEATURED_PRE_ADD_EMPTY', '警告: 没有指定商品编号 ... 没有添加 ...');
define('WARNING_FEATURED_PRE_ADD_DUPLICATE', '警告: 该商品已经是推荐商品 ... 没有添加 ...');
define('WARNING_FEATURED_PRE_ADD_BAD_PRODUCTS_ID', '警告: 商品编号不对 ... 没有添加 ...');
define('TEXT_INFO_HEADING_PRE_ADD_FEATURED', '按商品编号添加推荐商品');
define('TEXT_INFO_PRE_ADD_INTRO', '如果数据库比较大，可以通过商品编号手工添加推荐商品');
define('TEXT_PRE_ADD_PRODUCTS_ID', '请输入预添加商品编号: ');
define('TEXT_INFO_MANUAL', '手工添加推荐商品的编号');
?>