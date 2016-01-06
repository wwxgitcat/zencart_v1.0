<?php
/**
 * @package admin
 * @copyright Copyright 2003-2012 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version GIT: $Id: Author: Ian Wilson  Tue Aug 7 15:17:58 2012 +0100 Modified in v1.5.1 $
 */
require ('includes/application_top.php');

require (DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

$action = (isset($_GET['action']) ? $_GET['action'] : '');
$customers_id = zen_db_prepare_input($_GET['cID']);
if (isset($_POST['cID'])) $customers_id = zen_db_prepare_input($_POST['cID']);

$error = false;
$processed = false;

define('CONST_SEND_MAIL_NO_ORDER', 1);

if(isset($_POST['reset']))
{
	zen_redirect('customers.php');
}




if (zen_not_null($action))
{
	switch($action)
	{
		case 'list_addresses':
			$addresses_query = "SELECT address_book_id, entry_firstname as firstname, entry_lastname as lastname,
                            entry_company as company, entry_street_address as street_address,
                            entry_suburb as suburb, entry_city as city, entry_postcode as postcode,
                            entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id
                    FROM   " . TABLE_ADDRESS_BOOK . "
                    WHERE  customers_id = :customersID
                    ORDER BY firstname, lastname";
			
			$addresses_query = $db->bindVars($addresses_query, ':customersID', $_GET['cID'], 'integer');
			$addresses = $db->Execute($addresses_query);
			$addressArray = array();
			while(!$addresses->EOF)
			{
				$format_id = zen_get_address_format_id($addresses->fields['country_id']);
				
				$addressArray[] = array(
					'firstname' => $addresses->fields['firstname'],
					'lastname' => $addresses->fields['lastname'],
					'address_book_id' => $addresses->fields['address_book_id'],
					'format_id' => $format_id,
					'address' => $addresses->fields 
				);
				$addresses->MoveNext();
			}
			?>
<fieldset>
	<legend><?php echo ADDRESS_BOOK_TITLE; ?></legend>
	<div class="alert forward"><?php echo sprintf(TEXT_MAXIMUM_ENTRIES, MAX_ADDRESS_BOOK_ENTRIES); ?></div>
	<br class="clearBoth" />
<?php
			/**
			 * Used to loop thru and display address book entries
			 */
			foreach($addressArray as $addresses)
			{
				?>
<h3 class="addressBookDefaultName"><?php echo zen_output_string_protected($addresses['firstname'] . ' ' . $addresses['lastname']); ?><?php if ($addresses['address_book_id'] == zen_get_customers_address_primary($_GET['cID'])) echo '&nbsp;' . PRIMARY_ADDRESS ; ?></h3>
	<address><?php echo zen_address_format($addresses['format_id'], $addresses['address'], true, ' ', '<br />'); ?></address>

	<br class="clearBoth" />
<?php } // end list ?>
<div class="buttonRow forward"><?php echo '<a href="' . zen_href_link(FILENAME_CUSTOMERS, 'action=list_addresses_done' . '&cID=' . $_GET['cID'] . ($_GET['page'] > 0 ? '&page=' . $_GET['page'] : ''), 'NONSSL') . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'; ?>


</fieldset>
<?php
			die();
			break;
		case 'list_addresses_done':
			$action = '';
			zen_redirect(zen_href_link(FILENAME_CUSTOMERS, 'cID=' . (int)$_GET['cID'] . '&page=' . $_GET['page'], 'NONSSL'));
			break;
		case 'status':
			if (isset($_POST['current']) && is_numeric($_POST['current']))
			{
				if ($_POST['current'] == CUSTOMERS_APPROVAL_AUTHORIZATION)
				{
					$sql = "update " . TABLE_CUSTOMERS . " set customers_authorization=0 where customers_id='" . (int)$customers_id . "'";
					$custinfo = $db->Execute("select customers_email_address, customers_firstname, customers_lastname
                                      from " . TABLE_CUSTOMERS . "
                                      where customers_id = '" . (int)$customers_id . "'");
					if ((int)CUSTOMERS_APPROVAL_AUTHORIZATION > 0 && (int)$_POST['current'] > 0 && $custinfo->RecordCount() > 0)
					{
						$message = EMAIL_CUSTOMER_STATUS_CHANGE_MESSAGE;
						$html_msg['EMAIL_MESSAGE_HTML'] = EMAIL_CUSTOMER_STATUS_CHANGE_MESSAGE;
						zen_mail($custinfo->fields['customers_firstname'] . ' ' . $custinfo->fields['customers_lastname'], $custinfo->fields['customers_email_address'], EMAIL_CUSTOMER_STATUS_CHANGE_SUBJECT, $message, STORE_NAME, EMAIL_FROM, $html_msg, 'default');
					}
				}
				else
				{
					$sql = "update " . TABLE_CUSTOMERS . " set customers_authorization='" . CUSTOMERS_APPROVAL_AUTHORIZATION . "' where customers_id='" . (int)$customers_id . "'";
				}
				$db->Execute($sql);
				$action = '';
				zen_redirect(zen_href_link(FILENAME_CUSTOMERS, 'cID=' . (int)$customers_id . '&page=' . $_GET['page'], 'NONSSL'));
			}
			$action = '';
			break;
		case 'update':
			$customers_firstname = zen_db_prepare_input(zen_sanitize_string($_POST['customers_firstname']));
			$customers_lastname = zen_db_prepare_input(zen_sanitize_string($_POST['customers_lastname']));
			$customers_email_address = zen_db_prepare_input($_POST['customers_email_address']);
			$customers_telephone = zen_db_prepare_input($_POST['customers_telephone']);
			$customers_fax = zen_db_prepare_input($_POST['customers_fax']);
			$customers_newsletter = zen_db_prepare_input($_POST['customers_newsletter']);
			$customers_group_pricing = (int)zen_db_prepare_input($_POST['customers_group_pricing']);
			$customers_email_format = zen_db_prepare_input($_POST['customers_email_format']);
			$customers_gender = zen_db_prepare_input($_POST['customers_gender']);
			$customers_dob = (empty($_POST['customers_dob']) ? zen_db_prepare_input('0001-01-01 00:00:00') : zen_db_prepare_input($_POST['customers_dob']));
			
			$customers_authorization = zen_db_prepare_input($_POST['customers_authorization']);
			$customers_referral = zen_db_prepare_input($_POST['customers_referral']);
			
			if (CUSTOMERS_APPROVAL_AUTHORIZATION == 2 and $customers_authorization == 1)
			{
				$customers_authorization = 2;
				$messageStack->add_session(ERROR_CUSTOMER_APPROVAL_CORRECTION2, 'caution');
			}
			
			if (CUSTOMERS_APPROVAL_AUTHORIZATION == 1 and $customers_authorization == 2)
			{
				$customers_authorization = 1;
				$messageStack->add_session(ERROR_CUSTOMER_APPROVAL_CORRECTION1, 'caution');
			}
			
			$default_address_id = zen_db_prepare_input($_POST['default_address_id']);
			$entry_street_address = zen_db_prepare_input($_POST['entry_street_address']);
			$entry_suburb = zen_db_prepare_input($_POST['entry_suburb']);
			$entry_postcode = zen_db_prepare_input($_POST['entry_postcode']);
			$entry_city = zen_db_prepare_input($_POST['entry_city']);
			$entry_country_id = zen_db_prepare_input($_POST['entry_country_id']);
			
			$entry_company = zen_db_prepare_input($_POST['entry_company']);
			$entry_state = zen_db_prepare_input($_POST['entry_state']);
			if (isset($_POST['entry_zone_id'])) $entry_zone_id = zen_db_prepare_input($_POST['entry_zone_id']);
			
			if (strlen($customers_firstname) < ENTRY_FIRST_NAME_MIN_LENGTH)
			{
				$error = true;
				$entry_firstname_error = true;
			}
			else
			{
				$entry_firstname_error = false;
			}
			
			if (strlen($customers_lastname) < ENTRY_LAST_NAME_MIN_LENGTH)
			{
				$error = true;
				$entry_lastname_error = true;
			}
			else
			{
				$entry_lastname_error = false;
			}
			
			if (ACCOUNT_DOB == 'true')
			{
				if (ENTRY_DOB_MIN_LENGTH > 0)
				{
					if (checkdate(substr(zen_date_raw($customers_dob), 4, 2), substr(zen_date_raw($customers_dob), 6, 2), substr(zen_date_raw($customers_dob), 0, 4)))
					{
						$entry_date_of_birth_error = false;
					}
					else
					{
						$error = true;
						$entry_date_of_birth_error = true;
					}
				}
			}
			else
			{
				$customers_dob = '0001-01-01 00:00:00';
			}
			
			if (strlen($customers_email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH)
			{
				$error = true;
				$entry_email_address_error = true;
			}
			else
			{
				$entry_email_address_error = false;
			}
			
			if (!zen_validate_email($customers_email_address))
			{
				$error = true;
				$entry_email_address_check_error = true;
			}
			else
			{
				$entry_email_address_check_error = false;
			}
			
			if (strlen($entry_street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH)
			{
				$error = true;
				$entry_street_address_error = true;
			}
			else
			{
				$entry_street_address_error = false;
			}
			
			if (strlen($entry_postcode) < ENTRY_POSTCODE_MIN_LENGTH)
			{
				$error = true;
				$entry_post_code_error = true;
			}
			else
			{
				$entry_post_code_error = false;
			}
			
			if (strlen($entry_city) < ENTRY_CITY_MIN_LENGTH)
			{
				$error = true;
				$entry_city_error = true;
			}
			else
			{
				$entry_city_error = false;
			}
			
			if ($entry_country_id == false)
			{
				$error = true;
				$entry_country_error = true;
			}
			else
			{
				$entry_country_error = false;
			}
			
			if (ACCOUNT_STATE == 'true')
			{
				if ($entry_country_error == true)
				{
					$entry_state_error = true;
				}
				else
				{
					$zone_id = 0;
					$entry_state_error = false;
					$check_value = $db->Execute("select count(*) as total
                                         from " . TABLE_ZONES . "
                                         where zone_country_id = '" . (int)$entry_country_id . "'");
					
					$entry_state_has_zones = ($check_value->fields['total'] > 0);
					if ($entry_state_has_zones == true)
					{
						$zone_query = $db->Execute("select zone_id
                                          from " . TABLE_ZONES . "
                                          where zone_country_id = '" . (int)$entry_country_id . "'
                                          and zone_name = '" . zen_db_input($entry_state) . "'");
						
						if ($zone_query->RecordCount() > 0)
						{
							$entry_zone_id = $zone_query->fields['zone_id'];
						}
						else
						{
							$error = true;
							$entry_state_error = true;
						}
					}
					else
					{
						if (strlen($entry_state) < (int)ENTRY_STATE_MIN_LENGTH)
						{
							$error = true;
							$entry_state_error = true;
						}
					}
				}
			}
			
			if (strlen($customers_telephone) < ENTRY_TELEPHONE_MIN_LENGTH)
			{
				$error = true;
				$entry_telephone_error = true;
			}
			else
			{
				$entry_telephone_error = false;
			}
			
			$check_email = $db->Execute("select customers_email_address
                                   from " . TABLE_CUSTOMERS . "
                                   where customers_email_address = '" . zen_db_input($customers_email_address) . "'
                                   and customers_id != '" . (int)$customers_id . "'");
			
			if ($check_email->RecordCount() > 0)
			{
				$error = true;
				$entry_email_address_exists = true;
			}
			else
			{
				$entry_email_address_exists = false;
			}
			
			if ($error == false)
			{
				
				$sql_data_array = array(
					'customers_firstname' => $customers_firstname,
					'customers_lastname' => $customers_lastname,
					'customers_email_address' => $customers_email_address,
					'customers_telephone' => $customers_telephone,
					'customers_fax' => $customers_fax,
					'customers_group_pricing' => $customers_group_pricing,
					'customers_newsletter' => $customers_newsletter,
					'customers_email_format' => $customers_email_format,
					'customers_authorization' => $customers_authorization,
					'customers_referral' => $customers_referral 
				);
				
				if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $customers_gender;
				if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = ($customers_dob == '0001-01-01 00:00:00' ? '0001-01-01 00:00:00' : zen_date_raw($customers_dob));
				
				zen_db_perform(TABLE_CUSTOMERS, $sql_data_array, 'update', "customers_id = '" . (int)$customers_id . "'");
				
				$db->Execute("update " . TABLE_CUSTOMERS_INFO . "
                      set customers_info_date_account_last_modified = now()
                      where customers_info_id = '" . (int)$customers_id . "'");
				
				if ($entry_zone_id > 0) $entry_state = '';
				
				$sql_data_array = array(
					'entry_firstname' => $customers_firstname,
					'entry_lastname' => $customers_lastname,
					'entry_street_address' => $entry_street_address,
					'entry_postcode' => $entry_postcode,
					'entry_city' => $entry_city,
					'entry_country_id' => $entry_country_id 
				);
				
				if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $entry_company;
				if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = $entry_suburb;
				
				if (ACCOUNT_STATE == 'true')
				{
					if ($entry_zone_id > 0)
					{
						$sql_data_array['entry_zone_id'] = $entry_zone_id;
						$sql_data_array['entry_state'] = '';
					}
					else
					{
						$sql_data_array['entry_zone_id'] = '0';
						$sql_data_array['entry_state'] = $entry_state;
					}
				}
				
				zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array, 'update', "customers_id = '" . (int)$customers_id . "' and address_book_id = '" . (int)$default_address_id . "'");
				
				zen_redirect(zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
					'cID',
					'action' 
				)) . 'cID=' . $customers_id, 'NONSSL'));
			}
			else if ($error == true)
			{
				$cInfo = new objectInfo($_POST);
				$processed = true;
			}
			
			break;
		case 'deleteconfirm':
			// demo active test
			if (zen_admin_demo())
			{
				$_GET['action'] = '';
				$messageStack->add_session(ERROR_ADMIN_DEMO, 'caution');
				zen_redirect(zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
					'cID',
					'action' 
				)), 'NONSSL'));
			}
			$customers_id = zen_db_prepare_input($_POST['cID']);
			
			if (isset($_POST['delete_reviews']) && ($_POST['delete_reviews'] == 'on'))
			{
				$reviews = $db->Execute("select reviews_id
                                   from " . TABLE_REVIEWS . "
                                   where customers_id = '" . (int)$customers_id . "'");
				while(!$reviews->EOF)
				{
					$db->Execute("delete from " . TABLE_REVIEWS_DESCRIPTION . "
                          where reviews_id = '" . (int)$reviews->fields['reviews_id'] . "'");
					$reviews->MoveNext();
				}
				
				$db->Execute("delete from " . TABLE_REVIEWS . "
                        where customers_id = '" . (int)$customers_id . "'");
			}
			else
			{
				$db->Execute("update " . TABLE_REVIEWS . "
                        set customers_id = null
                        where customers_id = '" . (int)$customers_id . "'");
			}
			
			$db->Execute("delete from " . TABLE_ADDRESS_BOOK . "
                      where customers_id = '" . (int)$customers_id . "'");
			
			$db->Execute("delete from " . TABLE_CUSTOMERS . "
                      where customers_id = '" . (int)$customers_id . "'");
			
			$db->Execute("delete from " . TABLE_CUSTOMERS_INFO . "
                      where customers_info_id = '" . (int)$customers_id . "'");
			
			$db->Execute("delete from " . TABLE_CUSTOMERS_BASKET . "
                      where customers_id = '" . (int)$customers_id . "'");
			
			$db->Execute("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . "
                      where customers_id = '" . (int)$customers_id . "'");
			
			$db->Execute("delete from " . TABLE_WHOS_ONLINE . "
                      where customer_id = '" . (int)$customers_id . "'");
			
			zen_redirect(zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
				'cID',
				'action' 
			)), 'NONSSL'));
			break;
		default:
			$customers = $db->Execute("select c.customers_id, c.customers_gender, c.customers_firstname,
                                          c.customers_lastname, c.customers_dob, c.customers_email_address,
                                          a.entry_company, a.entry_street_address, a.entry_suburb,
                                          a.entry_postcode, a.entry_city, a.entry_state, a.entry_zone_id,
                                          a.entry_country_id, c.customers_telephone, c.customers_fax,
                                          c.customers_newsletter, c.customers_default_address_id,
                                          c.customers_email_format, c.customers_group_pricing,
                                          c.customers_authorization, c.customers_referral
                                  from " . TABLE_CUSTOMERS . " c left join " . TABLE_ADDRESS_BOOK . " a
                                  on c.customers_default_address_id = a.address_book_id
                                  where a.customers_id = c.customers_id
                                  and c.customers_id = '" . (int)$customers_id . "'");
			
			$cInfo = new objectInfo($customers->fields);
	}
}
// Sort Listing
switch($_GET['list_order'])
{
	case "id-asc":
		$disp_order = "ci.customers_info_date_account_created";
		break;
	case "firstname":
		$disp_order = "c.customers_firstname";
		break;
	case "firstname-desc":
		$disp_order = "c.customers_firstname DESC";
		break;
	case "group-asc":
		$disp_order = "c.customers_group_pricing";
		break;
	case "group-desc":
		$disp_order = "c.customers_group_pricing DESC";
		break;
	case "lastname":
		$disp_order = "c.customers_lastname, c.customers_firstname";
		break;
	case "lastname-desc":
		$disp_order = "c.customers_lastname DESC, c.customers_firstname";
		break;
	case "company":
		$disp_order = "a.entry_company";
		break;
	case "company-desc":
		$disp_order = "a.entry_company DESC";
		break;
	case "login-asc":
		$disp_order = "ci.customers_info_date_of_last_logon";
		break;
	case "login-desc":
		$disp_order = "ci.customers_info_date_of_last_logon DESC";
		break;
	case "approval-asc":
		$disp_order = "c.customers_authorization";
		break;
	case "approval-desc":
		$disp_order = "c.customers_authorization DESC";
		break;
	case "gv_balance-asc":
		$disp_order = "cgc.amount, c.customers_lastname, c.customers_firstname";
		break;
	case "gv_balance-desc":
		$disp_order = "cgc.amount DESC, c.customers_lastname, c.customers_firstname";
		break;
	default:
		$disp_order = "ci.customers_info_date_account_created DESC";
}

$where = array();
if (isset($_POST['search']) && zen_not_null($_POST['search']))
{
	$keywords = zen_db_input(zen_db_prepare_input($_POST['search']));
	$where_tmp = "(c.customers_lastname like binary '%" . $keywords . "%' or c.customers_firstname like binary '%" . $keywords . "%' or c.customers_email_address like binary '%" . $keywords . "%' or c.customers_telephone rlike binary ':keywords:' or a.entry_company rlike binary ':keywords:' or a.entry_street_address rlike binary ':keywords:' or a.entry_city rlike binary ':keywords:' or a.entry_postcode rlike binary ':keywords:')";
	$where[] = $db->bindVars($where_tmp, ':keywords:', $keywords, 'regexp');
}
if (isset($_POST['send_mailed']))
{
	if ((int)$_POST['send_mailed'] == 1)
		$where[] = "csm.count > 0";
	else if ((int)$_POST['send_mailed'] == 2)
		$where[] = "csm.count IS NULL";

}
if (isset($_POST['customers_order_status']))
{
	$select_customer_id = array();
	$sql_filter = '';
	switch ($_POST['customers_order_status'])
	{
		case '0':
			break;
		case '1':
			$sql_filter = 'SELECT c.customers_id FROM '.TABLE_CUSTOMERS.' c LEFT JOIN '.TABLE_ORDERS.' o ON (c.customers_id=o.customers_id) WHERE o.orders_id is null';
			break;
		case '2':
			$sql_filter = 'SELECT c.customers_id FROM '.TABLE_CUSTOMERS.' c INNER JOIN '.TABLE_ORDERS.' o ON (c.customers_id=o.customers_id) GROUP BY c.customers_id HAVING COUNT(o.orders_id) =1';
			break;
		case '3':
			$sql_filter = 'SELECT c.customers_id FROM '.TABLE_CUSTOMERS.' c INNER JOIN '.TABLE_ORDERS.' o ON (c.customers_id=o.customers_id) GROUP BY c.customers_id HAVING COUNT(o.orders_id) =2';
			break;
		case '4':
			$sql_filter = 'SELECT c.customers_id FROM '.TABLE_CUSTOMERS.' c INNER JOIN '.TABLE_ORDERS.' o ON (c.customers_id=o.customers_id) GROUP BY c.customers_id HAVING COUNT(o.orders_id) >2';
			break;
	}
	if (!empty($sql_filter))
	{
		$result = $db->Execute($sql_filter);
		if ($result->RecordCount() > 0)
		{
			while (!$result->EOF)
			{
				$select_customer_id[] = $result->fields['customers_id'];
				$result->MoveNext();
			}
		}
		else
		{
			$select_customer_id[] = 0;
		}
	}
	$select_customer_id = array_unique($select_customer_id);

	if (count($select_customer_id) == 1 && $select_customer_id[0] == 0)
		$where[] = 'c.customers_id =0';
	else if (count($select_customer_id))
		$where[] = 'c.customers_id IN ('.implode(',', $select_customer_id).')';
}
if (!empty($where))
	$where = 'WHERE '.implode(' AND ', $where);

if (count($where) == 0)
	$where = '';

$new_fields = ', c.customers_telephone, a.entry_company, a.entry_street_address, a.entry_city, a.entry_postcode, c.customers_authorization, c.customers_referral';
$customers_query_raw = "select distinct c.customers_id, c.customers_lastname, c.customers_firstname, c.customers_email_address, c.customers_group_pricing, a.entry_country_id, a.entry_company, ci.customers_info_date_of_last_logon, ci.customers_info_date_account_created " . $new_fields . ",
    cgc.amount
    from " . TABLE_CUSTOMERS . " c
    left join " . TABLE_CUSTOMERS_INFO . " ci on c.customers_id= ci.customers_info_id
    left join " . TABLE_ADDRESS_BOOK . " a on c.customers_id = a.customers_id and c.customers_default_address_id = a.address_book_id " . "
    left join " . TABLE_COUPON_GV_CUSTOMER . " cgc on c.customers_id = cgc.customer_id
	left join customers_send_mail csm ON (csm.customers_id=c.customers_id)
	" . $where . " order by $disp_order";



if (isset($_POST['send_mail_btn']))
{
	$customer_send_mail = $db->Execute($customers_query_raw);
	while (!$customer_send_mail->EOF)
	{
		
		$variable = get_variable_customer($customer_send_mail->fields['customers_id']);
		$mail_tempalte = get_email_template(CONST_SEND_MAIL_NO_ORDER);
		$to_name = $variable['{customers_name}'];
		$to_address = $variable['{customers_email_address}'];
		
		
		$from_email_name = $variable['{store_name}'];
		$mail_info = get_mail_random();
		$variable = array_merge($variable, $mail_info);
		
		$from_email_address = $mail_info['smtp_user'];
		
		$email_subject = str_ireplace(array_keys($variable), array_values($variable), $mail_tempalte['subject']);
		$email_text = str_ireplace(array_keys($variable), array_values($variable), $mail_tempalte['content']);
		
		$send_status = jsend_mail($to_name, $to_address, $email_subject, $email_text, $from_email_name, $from_email_address, $variable);
			
		$temp_result = $db->Execute('SELECT count FROM customers_send_mail WHERE customers_id='.(int)$customer_send_mail->fields['customers_id'].' AND email_template_id='.(int)$mail_tempalte['email_template_id']);
		if ($temp_result->fields['count'] > 0)
		{
			zen_db_perform('customers_send_mail', array(
			'count' => (int)$temp_result->fields['count'] + 1
			), 'update', 'customers_id='.$customer_send_mail->fields['customers_id'].' and email_template_id='.$mail_tempalte['email_template_id']);
		}
		else
		{
			zen_db_perform('customers_send_mail', array(
			'customers_id' => $customer_send_mail->fields['customers_id'],
			'email_template_id' => $mail_tempalte['email_template_id'],
			'count' => 1
			));
		}
		$customer_send_mail->MoveNext();
	}
}



?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type"
	content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css"
	href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<script language="javascript" src="includes/menu.js"></script>
<script language="javascript" src="includes/general.js"></script>
<?php
if ($action == 'edit' || $action == 'update')
{
	?>
<script language="javascript"><!--

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";

  var customers_firstname = document.customers.customers_firstname.value;
  var customers_lastname = document.customers.customers_lastname.value;
<?php if (ACCOUNT_COMPANY == 'true') echo 'var entry_company = document.customers.entry_company.value;' . "\n"; ?>
<?php if (ACCOUNT_DOB == 'true') echo 'var customers_dob = document.customers.customers_dob.value;' . "\n"; ?>
  var customers_email_address = document.customers.customers_email_address.value;
  var entry_street_address = document.customers.entry_street_address.value;
  var entry_postcode = document.customers.entry_postcode.value;
  var entry_city = document.customers.entry_city.value;
  var customers_telephone = document.customers.customers_telephone.value;

<?php if (ACCOUNT_GENDER == 'true') { ?>
  if (document.customers.customers_gender[0].checked || document.customers.customers_gender[1].checked) {
  } else {
    error_message = error_message + "<?php echo JS_GENDER; ?>";
    error = 1;
  }
<?php } ?>

  if (customers_firstname == "" || customers_firstname.length < <?php echo ENTRY_FIRST_NAME_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_FIRST_NAME; ?>";
    error = 1;
  }

  if (customers_lastname == "" || customers_lastname.length < <?php echo ENTRY_LAST_NAME_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_LAST_NAME; ?>";
    error = 1;
  }

<?php if (ACCOUNT_DOB == 'true' && ENTRY_DOB_MIN_LENGTH !='') { ?>
  if (customers_dob == "" || customers_dob.length < <?php echo ENTRY_DOB_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_DOB; ?>";
    error = 1;
  }
<?php } ?>

  if (customers_email_address == "" || customers_email_address.length < <?php echo ENTRY_EMAIL_ADDRESS_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_EMAIL_ADDRESS; ?>";
    error = 1;
  }

  if (entry_street_address == "" || entry_street_address.length < <?php echo ENTRY_STREET_ADDRESS_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_ADDRESS; ?>";
    error = 1;
  }

  if (entry_postcode == "" || entry_postcode.length < <?php echo ENTRY_POSTCODE_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_POST_CODE; ?>";
    error = 1;
  }

  if (entry_city == "" || entry_city.length < <?php echo ENTRY_CITY_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_CITY; ?>";
    error = 1;
  }

<?php
	if (ACCOUNT_STATE == 'true')
	{
		?>
  if (document.customers.elements['entry_state'].type != "hidden") {
    if (document.customers.entry_state.value == '' || document.customers.entry_state.value.length < <?php echo ENTRY_STATE_MIN_LENGTH; ?> ) {
       error_message = error_message + "<?php echo JS_STATE; ?>";
       error = 1;
    }
  }
<?php
	}
	?>

  if (document.customers.elements['entry_country_id'].type != "hidden") {
    if (document.customers.entry_country_id.value == 0) {
      error_message = error_message + "<?php echo JS_COUNTRY; ?>";
      error = 1;
    }
  }

  minTelephoneLength = <?php echo (int)ENTRY_TELEPHONE_MIN_LENGTH; ?>;
  if (minTelephoneLength > 0 && customers_telephone.length < minTelephoneLength) {
    error_message = error_message + "<?php echo JS_TELEPHONE; ?>";
    error = 1;
  }

  if (error == 1) {
    alert(error_message);
    return false;
  } else {
    return true;
  }
}
//--></script>
<?php
}
?>
<script type="text/javascript">
  <!--
  function init()
  {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
  }
  // -->
</script>
</head>
<body onLoad="init()">
	<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

	<!-- body //-->
	<table border="0" width="100%" cellspacing="2" cellpadding="2">
		<tr>
			<!-- body_text //-->
			<td width="100%" valign="top"><table border="0" width="100%"
					cellspacing="0" cellpadding="2">
<?php
if ($action == 'edit' || $action == 'update')
{
	$newsletter_array = array(
		array(
			'id' => '1',
			'text' => ENTRY_NEWSLETTER_YES 
		),
		array(
			'id' => '0',
			'text' => ENTRY_NEWSLETTER_NO 
		) 
	);
	?>
      <tr>
						<td><table border="0" width="100%" cellspacing="0" cellpadding="0">
								<tr>
									<td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
									<td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?></td>
								</tr>
							</table></td>
					</tr>
					<tr>
						<td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr><?php
	
echo zen_draw_form('customers', FILENAME_CUSTOMERS, zen_get_all_get_params(array(
		'action' 
	)) . 'action=update', 'post', 'onsubmit="return check_form(customers);"', true) . zen_draw_hidden_field('default_address_id', $cInfo->customers_default_address_id);
	echo zen_hide_session_id();
	?>
        <td class="formAreaTitle"><?php echo CATEGORY_PERSONAL; ?></td>
					</tr>
					<tr>
						<td class="formArea"><table border="0" cellspacing="2"
								cellpadding="2">
<?php
	if (ACCOUNT_GENDER == 'true')
	{
		?>
          <tr>
									<td class="main"><?php echo ENTRY_GENDER; ?></td>
									<td class="main">
<?php
		if ($error == true && $entry_gender_error == true)
		{
			echo zen_draw_radio_field('customers_gender', 'm', false, $cInfo->customers_gender) . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . zen_draw_radio_field('customers_gender', 'f', false, $cInfo->customers_gender) . '&nbsp;&nbsp;' . FEMALE . '&nbsp;' . ENTRY_GENDER_ERROR;
		}
		else
		{
			echo zen_draw_radio_field('customers_gender', 'm', false, $cInfo->customers_gender) . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . zen_draw_radio_field('customers_gender', 'f', false, $cInfo->customers_gender) . '&nbsp;&nbsp;' . FEMALE;
		}
		?></td>
								</tr>
<?php
	}
	?>

<?php
	$customers_authorization_array = array(
		array(
			'id' => '0',
			'text' => CUSTOMERS_AUTHORIZATION_0 
		),
		array(
			'id' => '1',
			'text' => CUSTOMERS_AUTHORIZATION_1 
		),
		array(
			'id' => '2',
			'text' => CUSTOMERS_AUTHORIZATION_2 
		),
		array(
			'id' => '3',
			'text' => CUSTOMERS_AUTHORIZATION_3 
		),
		array(
			'id' => '4',
			'text' => CUSTOMERS_AUTHORIZATION_4 
		)  // banned
		);
	?>
          <tr>
									<td class="main"><?php echo CUSTOMERS_AUTHORIZATION; ?></td>
									<td class="main">
              <?php echo zen_draw_pull_down_menu('customers_authorization', $customers_authorization_array, $cInfo->customers_authorization); ?>
            </td>
								</tr>

								<tr>
									<td class="main"><?php echo ENTRY_FIRST_NAME; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_firstname_error == true)
		{
			echo zen_draw_input_field('customers_firstname', htmlspecialchars($cInfo->customers_firstname, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', 50)) . '&nbsp;' . ENTRY_FIRST_NAME_ERROR;
		}
		else
		{
			echo $cInfo->customers_firstname . zen_draw_hidden_field('customers_firstname');
		}
	}
	else
	{
		echo zen_draw_input_field('customers_firstname', htmlspecialchars($cInfo->customers_firstname, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', 50), true);
	}
	?></td>
								</tr>
								<tr>
									<td class="main"><?php echo ENTRY_LAST_NAME; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_lastname_error == true)
		{
			echo zen_draw_input_field('customers_lastname', htmlspecialchars($cInfo->customers_lastname, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', 50)) . '&nbsp;' . ENTRY_LAST_NAME_ERROR;
		}
		else
		{
			echo $cInfo->customers_lastname . zen_draw_hidden_field('customers_lastname');
		}
	}
	else
	{
		echo zen_draw_input_field('customers_lastname', htmlspecialchars($cInfo->customers_lastname, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', 50), true);
	}
	?></td>
								</tr>
<?php
	if (ACCOUNT_DOB == 'true')
	{
		?>
          <tr>
									<td class="main"><?php echo ENTRY_DATE_OF_BIRTH; ?></td>
									<td class="main">

<?php
		if ($error == true)
		{
			if ($entry_date_of_birth_error == true)
			{
				echo zen_draw_input_field('customers_dob', ($cInfo->customers_dob == '0001-01-01 00:00:00' ? '' : zen_date_short($cInfo->customers_dob)), 'maxlength="10"') . '&nbsp;' . ENTRY_DATE_OF_BIRTH_ERROR;
			}
			else
			{
				echo $cInfo->customers_dob . ($customers_dob == '0001-01-01 00:00:00' ? 'N/A' : zen_draw_hidden_field('customers_dob'));
			}
		}
		else
		{
			echo zen_draw_input_field('customers_dob', ($customers_dob == '0001-01-01 00:00:00' ? '' : zen_date_short($cInfo->customers_dob)), 'maxlength="10"', true);
		}
		?></td>
								</tr>
<?php
	}
	?>
          <tr>
									<td class="main"><?php echo ENTRY_EMAIL_ADDRESS; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_email_address_error == true)
		{
			echo zen_draw_input_field('customers_email_address', htmlspecialchars($cInfo->customers_email_address, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', 50)) . '&nbsp;' . ENTRY_EMAIL_ADDRESS_ERROR;
		}
		elseif ($entry_email_address_check_error == true)
		{
			echo zen_draw_input_field('customers_email_address', htmlspecialchars($cInfo->customers_email_address, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', 50)) . '&nbsp;' . ENTRY_EMAIL_ADDRESS_CHECK_ERROR;
		}
		elseif ($entry_email_address_exists == true)
		{
			echo zen_draw_input_field('customers_email_address', htmlspecialchars($cInfo->customers_email_address, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', 50)) . '&nbsp;' . ENTRY_EMAIL_ADDRESS_ERROR_EXISTS;
		}
		else
		{
			echo $customers_email_address . zen_draw_hidden_field('customers_email_address');
		}
	}
	else
	{
		echo zen_draw_input_field('customers_email_address', htmlspecialchars($cInfo->customers_email_address, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', 50), true);
	}
	?></td>
								</tr>
							</table></td>
					</tr>
<?php
	if (ACCOUNT_COMPANY == 'true')
	{
		?>
      <tr>
						<td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr>
						<td class="formAreaTitle"><?php echo CATEGORY_COMPANY; ?></td>
					</tr>
					<tr>
						<td class="formArea"><table border="0" cellspacing="2"
								cellpadding="2">
								<tr>
									<td class="main"><?php echo ENTRY_COMPANY; ?></td>
									<td class="main">
<?php
		if ($error == true)
		{
			if ($entry_company_error == true)
			{
				echo zen_draw_input_field('entry_company', htmlspecialchars($cInfo->entry_company, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', 50)) . '&nbsp;' . ENTRY_COMPANY_ERROR;
			}
			else
			{
				echo $cInfo->entry_company . zen_draw_hidden_field('entry_company');
			}
		}
		else
		{
			echo zen_draw_input_field('entry_company', htmlspecialchars($cInfo->entry_company, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', 50));
		}
		?></td>
								</tr>
							</table></td>
					</tr>
<?php
	}
	?>
      <tr>
						<td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr>
						<td class="formAreaTitle"><?php echo CATEGORY_ADDRESS; ?></td>
					</tr>
					<tr>
						<td class="formArea"><table border="0" cellspacing="2"
								cellpadding="2">
								<tr>
									<td class="main"><?php echo ENTRY_STREET_ADDRESS; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_street_address_error == true)
		{
			echo zen_draw_input_field('entry_street_address', htmlspecialchars($cInfo->entry_street_address, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', 50)) . '&nbsp;' . ENTRY_STREET_ADDRESS_ERROR;
		}
		else
		{
			echo $cInfo->entry_street_address . zen_draw_hidden_field('entry_street_address');
		}
	}
	else
	{
		echo zen_draw_input_field('entry_street_address', htmlspecialchars($cInfo->entry_street_address, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', 50), true);
	}
	?></td>
								</tr>
<?php
	if (ACCOUNT_SUBURB == 'true')
	{
		?>
          <tr>
									<td class="main"><?php echo ENTRY_SUBURB; ?></td>
									<td class="main">
<?php
		if ($error == true)
		{
			if ($entry_suburb_error == true)
			{
				echo zen_draw_input_field('suburb', htmlspecialchars($cInfo->entry_suburb, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', 50)) . '&nbsp;' . ENTRY_SUBURB_ERROR;
			}
			else
			{
				echo $cInfo->entry_suburb . zen_draw_hidden_field('entry_suburb');
			}
		}
		else
		{
			echo zen_draw_input_field('entry_suburb', htmlspecialchars($cInfo->entry_suburb, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', 50));
		}
		?></td>
								</tr>
<?php
	}
	?>
          <tr>
									<td class="main"><?php echo ENTRY_POST_CODE; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_post_code_error == true)
		{
			echo zen_draw_input_field('entry_postcode', htmlspecialchars($cInfo->entry_postcode, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', 10)) . '&nbsp;' . ENTRY_POST_CODE_ERROR;
		}
		else
		{
			echo $cInfo->entry_postcode . zen_draw_hidden_field('entry_postcode');
		}
	}
	else
	{
		echo zen_draw_input_field('entry_postcode', htmlspecialchars($cInfo->entry_postcode, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', 10), true);
	}
	?></td>
								</tr>
								<tr>
									<td class="main"><?php echo ENTRY_CITY; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_city_error == true)
		{
			echo zen_draw_input_field('entry_city', htmlspecialchars($cInfo->entry_city, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', 50)) . '&nbsp;' . ENTRY_CITY_ERROR;
		}
		else
		{
			echo $cInfo->entry_city . zen_draw_hidden_field('entry_city');
		}
	}
	else
	{
		echo zen_draw_input_field('entry_city', htmlspecialchars($cInfo->entry_city, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', 50), true);
	}
	?></td>
								</tr>
<?php
	if (ACCOUNT_STATE == 'true')
	{
		?>
          <tr>
									<td class="main"><?php echo ENTRY_STATE; ?></td>
									<td class="main">
<?php
		$entry_state = zen_get_zone_name($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state);
		if ($error == true)
		{
			if ($entry_state_error == true)
			{
				if ($entry_state_has_zones == true)
				{
					$zones_array = array();
					$zones_values = $db->Execute("select zone_name
                                        from " . TABLE_ZONES . "
                                        where zone_country_id = '" . zen_db_input($cInfo->entry_country_id) . "'
                                        order by zone_name");
					
					while(!$zones_values->EOF)
					{
						$zones_array[] = array(
							'id' => $zones_values->fields['zone_name'],
							'text' => $zones_values->fields['zone_name'] 
						);
						$zones_values->MoveNext();
					}
					echo zen_draw_pull_down_menu('entry_state', $zones_array) . '&nbsp;' . ENTRY_STATE_ERROR;
				}
				else
				{
					echo zen_draw_input_field('entry_state', htmlspecialchars(zen_get_zone_name($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state), ENT_COMPAT, CHARSET, TRUE)) . '&nbsp;' . ENTRY_STATE_ERROR;
				}
			}
			else
			{
				echo $entry_state . zen_draw_hidden_field('entry_zone_id') . zen_draw_hidden_field('entry_state');
			}
		}
		else
		{
			echo zen_draw_input_field('entry_state', htmlspecialchars(zen_get_zone_name($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state), ENT_COMPAT, CHARSET, TRUE));
		}
		
		?></td>
								</tr>
<?php
	}
	?>
          <tr>
									<td class="main"><?php echo ENTRY_COUNTRY; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_country_error == true)
		{
			echo zen_draw_pull_down_menu('entry_country_id', zen_get_countries(), $cInfo->entry_country_id) . '&nbsp;' . ENTRY_COUNTRY_ERROR;
		}
		else
		{
			echo zen_get_country_name($cInfo->entry_country_id) . zen_draw_hidden_field('entry_country_id');
		}
	}
	else
	{
		echo zen_draw_pull_down_menu('entry_country_id', zen_get_countries(), $cInfo->entry_country_id);
	}
	?></td>
								</tr>
							</table></td>
					</tr>
					<tr>
						<td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr>
						<td class="formAreaTitle"><?php echo CATEGORY_CONTACT; ?></td>
					</tr>
					<tr>
						<td class="formArea"><table border="0" cellspacing="2"
								cellpadding="2">
								<tr>
									<td class="main"><?php echo ENTRY_TELEPHONE_NUMBER; ?></td>
									<td class="main">
<?php
	if ($error == true)
	{
		if ($entry_telephone_error == true)
		{
			echo zen_draw_input_field('customers_telephone', htmlspecialchars($cInfo->customers_telephone, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_telephone', 15)) . '&nbsp;' . ENTRY_TELEPHONE_NUMBER_ERROR;
		}
		else
		{
			echo $cInfo->customers_telephone . zen_draw_hidden_field('customers_telephone');
		}
	}
	else
	{
		echo zen_draw_input_field('customers_telephone', htmlspecialchars($cInfo->customers_telephone, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_telephone', 15), true);
	}
	?></td>
								</tr>
<?php
	if (ACCOUNT_FAX_NUMBER == 'true')
	{
		?>
          <tr>
									<td class="main"><?php echo ENTRY_FAX_NUMBER; ?></td>
									<td class="main">
<?php
		if ($processed == true)
		{
			echo $cInfo->customers_fax . zen_draw_hidden_field('customers_fax');
		}
		else
		{
			echo zen_draw_input_field('customers_fax', htmlspecialchars($cInfo->customers_fax, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_fax', 15));
		}
		?></td>
								</tr>
<?php } ?>
        </table></td>
					</tr>
					<tr>
						<td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr>
						<td class="formAreaTitle"><?php echo CATEGORY_OPTIONS; ?></td>
					</tr>
					<tr>
						<td class="formArea"><table border="0" cellspacing="2"
								cellpadding="2">

								<tr>
									<td class="main"><?php echo ENTRY_EMAIL_PREFERENCE; ?></td>
									<td class="main">
<?php
	if ($processed == true)
	{
		if ($cInfo->customers_email_format)
		{
			echo $customers_email_format . zen_draw_hidden_field('customers_email_format');
		}
	}
	else
	{
		$email_pref_text = ($cInfo->customers_email_format == 'TEXT') ? true : false;
		$email_pref_html = !$email_pref_text;
		echo zen_draw_radio_field('customers_email_format', 'HTML', $email_pref_html) . '&nbsp;' . ENTRY_EMAIL_HTML_DISPLAY . '&nbsp;&nbsp;&nbsp;' . zen_draw_radio_field('customers_email_format', 'TEXT', $email_pref_text) . '&nbsp;' . ENTRY_EMAIL_TEXT_DISPLAY;
	}
	?></td>
								</tr>
								<tr>
									<td class="main"><?php echo ENTRY_NEWSLETTER; ?></td>
									<td class="main">
<?php
	if ($processed == true)
	{
		if ($cInfo->customers_newsletter == '1')
		{
			echo ENTRY_NEWSLETTER_YES;
		}
		else
		{
			echo ENTRY_NEWSLETTER_NO;
		}
		echo zen_draw_hidden_field('customers_newsletter');
	}
	else
	{
		echo zen_draw_pull_down_menu('customers_newsletter', $newsletter_array, (($cInfo->customers_newsletter == '1') ? '1' : '0'));
	}
	?></td>
								</tr>
								<tr>
									<td class="main"><?php echo ENTRY_PRICING_GROUP; ?></td>
									<td class="main">
<?php
	if ($processed == true)
	{
		if ($cInfo->customers_group_pricing)
		{
			$group_query = $db->Execute("select group_name, group_percentage from " . TABLE_GROUP_PRICING . " where group_id = '" . (int)$cInfo->customers_group_pricing . "'");
			echo $group_query->fields['group_name'] . '&nbsp;' . $group_query->fields['group_percentage'] . '%';
		}
		else
		{
			echo ENTRY_NONE;
		}
		echo zen_draw_hidden_field('customers_group_pricing', $cInfo->customers_group_pricing);
	}
	else
	{
		$group_array_query = $db->execute("select group_id, group_name, group_percentage from " . TABLE_GROUP_PRICING);
		$group_array[] = array(
			'id' => 0,
			'text' => TEXT_NONE 
		);
		while(!$group_array_query->EOF)
		{
			$group_array[] = array(
				'id' => $group_array_query->fields['group_id'],
				'text' => $group_array_query->fields['group_name'] . '&nbsp;' . $group_array_query->fields['group_percentage'] . '%' 
			);
			$group_array_query->MoveNext();
		}
		echo zen_draw_pull_down_menu('customers_group_pricing', $group_array, $cInfo->customers_group_pricing);
	}
	?></td>
								</tr>

								<tr>
									<td class="main"><?php echo CUSTOMERS_REFERRAL; ?></td>
									<td class="main">
              <?php echo zen_draw_input_field('customers_referral', htmlspecialchars($cInfo->customers_referral, ENT_COMPAT, CHARSET, TRUE), zen_set_field_length(TABLE_CUSTOMERS, 'customers_referral', 15)); ?>
            </td>
								</tr>
							</table></td>
					</tr>

					<tr>
						<td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr>
						<td align="right" class="main"><?php echo zen_image_submit('button_update.gif', IMAGE_UPDATE) . ' <a href="' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array('action')), 'NONSSL') .'">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'; ?></td>
					</tr>
					</form>
<?php
}
else
{
	?>
      <tr>
		<td>
			<form name="search" id="search" action="<?php echo zen_href_link(FILENAME_CUSTOMERS, '', 'NONSSL');?>" method="post">
				<table border="0" width="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td>
							搜索:<?php echo zen_draw_input_field('search', isset($_POST['search']) ? $_POST['search'] : '') . zen_hide_session_id();?>&nbsp;&nbsp;&nbsp;
							订单状态:<?php echo zen_draw_pull_down_menu('customers_order_status', array(
								array('id' => 0, 'text' => '所有用户'),
								array('id' => 1, 'text' => '没有订单的用户'),
								array('id' => 2, 'text' => '一个订单的用户'),
								array('id' => 3, 'text' => '二个订单的用户'),
								array('id' => 4, 'text' => '多个订单的用户')
							), isset($_POST['customers_order_status']) ? (int)$_POST['customers_order_status'] : '0',
								'id="customers_order_status"')?>
								
							邮件发送状态:<?php echo zen_draw_pull_down_menu('send_mailed', array(
								array('id' => 0, 'text' => '所有用户'),
								array('id' => 1, 'text' => '已发邮件'),
								array('id' => 2, 'text' => '未发邮件')
							), isset($_POST['send_mailed']) ? (int)$_POST['send_mailed'] : '0',
								'id="send_mailed"')?>
						</td>
					</tr>
					<tr>
						<td>
							<input type="submit" name="filter" value="搜索" />
							<input type="submit" name="reset" value="重置" />
							<input type="submit" name="send_mail_btn" value="批量发送未下单邮件" />
						</td>
					</tr>
				</table>
			</form></td>
		</tr>
		<tr>
		<td>
			<table border="0" width="100%" cellspacing="0" cellpadding="0">
			<tr>
             <td valign="top"><table border="0" width="100%"
											cellspacing="0" cellpadding="2">
											<tr class="dataTableHeadingRow">
												<td class="dataTableHeadingContent" align="center"
													valign="top">
                  <?php echo TABLE_HEADING_ID; ?>
                </td>
												<td class="dataTableHeadingContent" align="left"
													valign="top">
                  <?php echo (($_GET['list_order']=='lastname' or $_GET['list_order']=='lastname-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_LASTNAME . '</span>' : TABLE_HEADING_LASTNAME); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=lastname', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='lastname' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=lastname-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='lastname-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>
												<td class="dataTableHeadingContent" align="left"
													valign="top">
                  <?php echo (($_GET['list_order']=='firstname' or $_GET['list_order']=='firstname-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_FIRSTNAME . '</span>' : TABLE_HEADING_FIRSTNAME); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=firstname', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='firstname' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=firstname-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='firstname-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</span>'); ?></a>
												</td>
												<td class="dataTableHeadingContent" align="left"
													valign="top">
                  <?php echo (($_GET['list_order']=='company' or $_GET['list_order']=='company-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_COMPANY . '</span>' : TABLE_HEADING_COMPANY); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=company', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='company' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=company-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='company-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>
												<td class="dataTableHeadingContent" align="left"
													valign="top">
                  <?php echo (($_GET['list_order']=='id-asc' or $_GET['list_order']=='id-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_ACCOUNT_CREATED . '</span>' : TABLE_HEADING_ACCOUNT_CREATED); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=id-asc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='id-asc' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=id-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='id-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>

												<td class="dataTableHeadingContent" align="left"
													valign="top">
                  <?php echo (($_GET['list_order']=='login-asc' or $_GET['list_order']=='login-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_LOGIN . '</span>' : TABLE_HEADING_LOGIN); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=login-asc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='login-asc' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=login-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='login-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>

												<td class="dataTableHeadingContent" align="left"
													valign="top">
                  <?php echo (($_GET['list_order']=='group-asc' or $_GET['list_order']=='group-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_PRICING_GROUP . '</span>' : TABLE_HEADING_PRICING_GROUP); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=group-asc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='group-asc' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=group-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='group-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>

<?php if (MODULE_ORDER_TOTAL_GV_STATUS == 'true') { ?>
                <td class="dataTableHeadingContent" align="left"
													valign="top" width="75">
                  <?php echo (($_GET['list_order']=='gv_balance-asc' or $_GET['list_order']=='gv_balance-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_GV_AMOUNT . '</span>' : TABLE_HEADING_GV_AMOUNT); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=gv_balance-asc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='gv_balance-asc' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=gv_balance-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='gv_balance-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>
<?php } ?>
				<td class="dataTableHeadingContent" align="center" valign="top">
				邮件发送状态
				</td>
                <td class="dataTableHeadingContent" align="center"
													valign="top">
                  <?php echo (($_GET['list_order']=='approval-asc' or $_GET['list_order']=='approval-desc') ? '<span class="SortOrderHeader">' . TABLE_HEADING_AUTHORIZATION_APPROVAL . '</span>' : TABLE_HEADING_AUTHORIZATION_APPROVAL); ?><br>
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=approval-asc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='approval-asc' ? '<span class="SortOrderHeader">Asc</span>' : '<span class="SortOrderHeaderLink">Asc</b>'); ?></a>&nbsp;
													<a
													href="<?php echo zen_href_link(basename($PHP_SELF) . '?list_order=approval-desc', '', 'NONSSL'); ?>"><?php echo ($_GET['list_order']=='approval-desc' ? '<span class="SortOrderHeader">Desc</span>' : '<span class="SortOrderHeaderLink">Desc</b>'); ?></a>
												</td>

												<td class="dataTableHeadingContent" align="right"
													valign="top"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
											</tr>
<?php
	
	// Split Page
	// reset page when page is unknown
	if (($_GET['page'] == '' or $_GET['page'] == '1') and $_GET['cID'] != '')
	{
		$check_page = $db->Execute($customers_query_raw);
		$check_count = 1;
		if ($check_page->RecordCount() > MAX_DISPLAY_SEARCH_RESULTS_CUSTOMER)
		{
			while(!$check_page->EOF)
			{
				if ($check_page->fields['customers_id'] == $_GET['cID'])
				{
					break;
				}
				$check_count++;
				$check_page->MoveNext();
			}
			$_GET['page'] = round((($check_count / MAX_DISPLAY_SEARCH_RESULTS_CUSTOMER) + (fmod_round($check_count, MAX_DISPLAY_SEARCH_RESULTS_CUSTOMER) != 0 ? .5 : 0)), 0);
			// zen_redirect(zen_href_link(FILENAME_CUSTOMERS, 'cID=' . $_GET['cID'] . (isset($_GET['page']) ? '&page=' . $_GET['page'] : ''), 'NONSSL'));
		}
		else
		{
			$_GET['page'] = 1;
		}
	}
	
	$customers_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS_CUSTOMER, $customers_query_raw, $customers_query_numrows);
	$customers = $db->Execute($customers_query_raw);
	while(!$customers->EOF)
	{
		$sql = "select customers_info_date_account_created as date_account_created,
                                   customers_info_date_account_last_modified as date_account_last_modified,
                                   customers_info_date_of_last_logon as date_last_logon,
                                   customers_info_number_of_logons as number_of_logons
                            from " . TABLE_CUSTOMERS_INFO . "
                            where customers_info_id = '" . $customers->fields['customers_id'] . "'";
		$info = $db->Execute($sql);
		
		// if no record found, create one to keep database in sync
		if (!isset($info->fields) || !is_array($info->fields))
		{
			$insert_sql = "insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created)
                       values ('" . (int)$customers->fields['customers_id'] . "', '0', now())";
			$db->Execute($insert_sql);
			$info = $db->Execute($sql);
		}
		
		if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ($_GET['cID'] == $customers->fields['customers_id']))) && !isset($cInfo))
		{
			$country = $db->Execute("select countries_name
                                 from " . TABLE_COUNTRIES . "
                                 where countries_id = '" . (int)$customers->fields['entry_country_id'] . "'");
			
			$reviews = $db->Execute("select count(*) as number_of_reviews
                                 from " . TABLE_REVIEWS . " where customers_id = '" . (int)$customers->fields['customers_id'] . "'");
			
			$customer_info = array_merge((array)$country->fields, (array)$info->fields, (array)$reviews->fields);
			
			$cInfo_array = array_merge($customers->fields, $customer_info);
			$cInfo = new objectInfo($cInfo_array);
		}
		
		$group_query = $db->Execute("select group_name, group_percentage from " . TABLE_GROUP_PRICING . " where
                                     group_id = '" . $customers->fields['customers_group_pricing'] . "'");
		
		if ($group_query->RecordCount() < 1)
		{
			$group_name_entry = TEXT_NONE;
		}
		else
		{
			$group_name_entry = $group_query->fields['group_name'];
		}
		
		if (isset($cInfo) && is_object($cInfo) && ($customers->fields['customers_id'] == $cInfo->customers_id))
		{
			echo '          <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
				'cID',
				'action' 
			)) . 'cID=' . $cInfo->customers_id . '&action=edit', 'NONSSL') . '\'">' . "\n";
		}
		else
		{
			echo '          <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
				'cID' 
			)) . 'cID=' . $customers->fields['customers_id'], 'NONSSL') . '\'">' . "\n";
		}
		
		$zc_address_book_count_list = zen_get_customers_address_book($customers->fields['customers_id']);
		$zc_address_book_count = $zc_address_book_count_list->RecordCount();
		?>
                <td class="dataTableContent" align="right"><?php echo $customers->fields['customers_id'] . ($zc_address_book_count == 1 ? TEXT_INFO_ADDRESS_BOOK_COUNT . $zc_address_book_count : '<a href="' . zen_href_link(FILENAME_CUSTOMERS, 'action=list_addresses' . '&cID=' . $customers->fields['customers_id'] . ($_GET['page'] > 0 ? '&page=' . $_GET['page'] : ''), 'NONSSL') . '">' . TEXT_INFO_ADDRESS_BOOK_COUNT . $zc_address_book_count . '</a>'); ?></td>
											<td class="dataTableContent"><?php echo $customers->fields['customers_lastname']; ?></td>
											<td class="dataTableContent"><?php echo $customers->fields['customers_firstname']; ?></td>
											<td class="dataTableContent"><?php echo $customers->fields['entry_company']; ?></td>
											<td class="dataTableContent"><?php echo zen_date_short($info->fields['date_account_created']); ?></td>
											<td class="dataTableContent"><?php echo zen_date_short($customers->fields['customers_info_date_of_last_logon']); ?></td>
											<td class="dataTableContent"><?php echo $group_name_entry; ?></td>
<?php if (MODULE_ORDER_TOTAL_GV_STATUS == 'true') { ?>
                <td class="dataTableContent" align="right"><?php echo $currencies->format($customers->fields['amount']); ?></td>
<?php } ?>
				<td class="dataTableContent" align="center">
				<?php $result_tmp = $db->Execute('SELECT * FROM customers_send_mail csm JOIN email_template et ON (csm.email_template_id=et.email_template_id) WHERE csm.customers_id='.$customers->fields['customers_id']);
				if ($result_tmp->RecordCount() > 0):
					while (!$result_tmp->EOF):
				?>
				<p style="color:red;"><?php echo $result_tmp->fields['name'];?></p>
				<?php $result_tmp->MoveNext(); endwhile;?>
				<?php else:?>
				<p>未发邮件</p>
				<?php endif;?>
				</td>
                <td class="dataTableContent" align="center">
                <?php if ($customers->fields['customers_authorization'] == 4) { ?>
                <?php echo zen_image(DIR_WS_IMAGES . 'icon_red_off.gif', IMAGE_ICON_STATUS_OFF); ?>
                <?php } else { ?>
                  <?php
			
if ($customers->fields['customers_authorization'] == 0)
			{
				echo zen_draw_form('setstatus', FILENAME_CUSTOMERS, 'action=status&cID=' . $customers->fields['customers_id'] . (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') . (isset($_GET['search']) ? '&search=' . $_GET['search'] : ''));
				?>
                    <input type="image"
												src="<?php echo DIR_WS_IMAGES ?>icon_green_on.gif"
												title="<?php echo IMAGE_ICON_STATUS_ON; ?>" /> <input
												type="hidden" name="current"
												value="<?php echo $customers->fields['customers_authorization']; ?>" />
												</form>
                  <?php
			
}
			else
			{
				echo zen_draw_form('setstatus', FILENAME_CUSTOMERS, 'action=status&cID=' . $customers->fields['customers_id'] . (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') . (isset($_GET['search']) ? '&search=' . $_GET['search'] : ''));
				?>
                    <input type="image"
												src="<?php echo DIR_WS_IMAGES ?>icon_red_on.gif"
												title="<?php echo IMAGE_ICON_STATUS_OFF; ?>" /> <input
												type="hidden" name="current"
												value="<?php echo $customers->fields['customers_authorization']; ?>" />
												</form>
                  <?php } ?>
                <?php } ?>
                </td>
											<td class="dataTableContent" align="right">
											<a href="<?php echo zen_href_link(FILENAME_ORDERS, 'cID='.$customers->fields['customers_id']);?>">Show Order</a>&nbsp;&nbsp;
											<?php if (isset($cInfo) && is_object($cInfo) && ($customers->fields['customers_id'] == $cInfo->customers_id)) { echo zen_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', ''); } else { echo '<a href="' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array('cID')) . 'cID=' . $customers->fields['customers_id'] . ($_GET['page'] > 0 ? '&page=' . $_GET['page'] : ''), 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;
											</td>
											</tr>
<?php
		$customers->MoveNext();
	}
	?>
              <tr>
												<td colspan="5"><table border="0" width="100%"
														cellspacing="0" cellpadding="2">
														<tr>
															<td class="smallText" valign="top"><?php echo $customers_split->display_count($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS_CUSTOMER, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_CUSTOMERS); ?></td>
															<td class="smallText" align="right"><?php echo $customers_split->display_links($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS_CUSTOMER, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], zen_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?></td>
														</tr>
<?php
	if (isset($_GET['search']) && zen_not_null($_GET['search']))
	{
		?>
                  <tr>
															<td align="right" colspan="2"><?php echo '<a href="' . zen_href_link(FILENAME_CUSTOMERS, '', 'NONSSL') . '">' . zen_image_button('button_reset.gif', IMAGE_RESET) . '</a>'; ?></td>
														</tr>
<?php
	}
	?>
                </table></td>
											</tr>
										</table></td>
<?php
	$heading = array();
	$contents = array();
	
	switch($action)
	{
		case 'confirm':
			$heading[] = array(
				'text' => '<b>' . TEXT_INFO_HEADING_DELETE_CUSTOMER . '</b>' 
			);
			
			$contents = array(
				'form' => zen_draw_form('customers', FILENAME_CUSTOMERS, zen_get_all_get_params(array(
					'cID',
					'action',
					'search' 
				)) . 'action=deleteconfirm', 'post', '', true) . zen_draw_hidden_field('cID', $cInfo->customers_id) 
			);
			$contents[] = array(
				'text' => TEXT_DELETE_INTRO . '<br><br><b>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>' 
			);
			if (isset($cInfo->number_of_reviews) && ($cInfo->number_of_reviews) > 0) $contents[] = array(
				'text' => '<br />' . zen_draw_checkbox_field('delete_reviews', 'on', true) . ' ' . sprintf(TEXT_DELETE_REVIEWS, $cInfo->number_of_reviews) 
			);
			$contents[] = array(
				'align' => 'center',
				'text' => '<br />' . zen_image_submit('button_delete.gif', IMAGE_DELETE) . ' <a href="' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
					'cID',
					'action' 
				)) . 'cID=' . $cInfo->customers_id, 'NONSSL') . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>' 
			);
			break;
		default:
			if (isset($_GET['search'])) $_GET['search'] = zen_output_string_protected($_GET['search']);
			if (isset($cInfo) && is_object($cInfo))
			{
				$customers_orders = $db->Execute("select o.orders_id, o.date_purchased, o.order_total, o.currency, o.currency_value,
                                          cgc.amount
                                          from " . TABLE_ORDERS . " o
                                          left join " . TABLE_COUPON_GV_CUSTOMER . " cgc on o.customers_id = cgc.customer_id
                                          where customers_id='" . $cInfo->customers_id . "' order by date_purchased desc");
				
				$heading[] = array(
					'text' => '<b>' . TABLE_HEADING_ID . $cInfo->customers_id . ' ' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>' 
				);
				
				$contents[] = array(
					'align' => 'center',
					'text' => '<a href="' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
						'cID',
						'action',
						'search' 
					)) . 'cID=' . $cInfo->customers_id . '&action=edit', 'NONSSL') . '">' . zen_image_button('button_edit.gif', IMAGE_EDIT) . '</a> <a href="' . zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array(
						'cID',
						'action',
						'search' 
					)) . 'cID=' . $cInfo->customers_id . '&action=confirm', 'NONSSL') . '">' . zen_image_button('button_delete.gif', IMAGE_DELETE) . '</a><br />' . ($customers_orders->RecordCount() != 0 ? '<a href="' . zen_href_link(FILENAME_ORDERS, 'cID=' . $cInfo->customers_id, 'NONSSL') . '">' . zen_image_button('button_orders.gif', IMAGE_ORDERS) . '</a>' : '') . ' <a href="' . zen_href_link(FILENAME_MAIL, 'origin=customers.php&mode=NONSSL&selected_box=tools&customer=' . $cInfo->customers_email_address . '&cID=' . $cInfo->customers_id, 'NONSSL') . '">' . zen_image_button('button_email.gif', IMAGE_EMAIL) . '</a>' 
				);
				$contents[] = array(
					'text' => '<br />' . TEXT_DATE_ACCOUNT_CREATED . ' ' . zen_date_short($cInfo->date_account_created) 
				);
				$contents[] = array(
					'text' => '<br />' . TEXT_DATE_ACCOUNT_LAST_MODIFIED . ' ' . zen_date_short($cInfo->date_account_last_modified) 
				);
				$contents[] = array(
					'text' => '<br />' . TEXT_INFO_DATE_LAST_LOGON . ' ' . zen_date_short($cInfo->date_last_logon) 
				);
				$contents[] = array(
					'text' => '<br />' . TEXT_INFO_NUMBER_OF_LOGONS . ' ' . $cInfo->number_of_logons 
				);
				
				$customer_gv_balance = zen_user_has_gv_balance($cInfo->customers_id);
				$contents[] = array(
					'text' => '<br />' . TEXT_INFO_GV_AMOUNT . ' ' . $currencies->format($customer_gv_balance) 
				);
				
				$contents[] = array(
					'text' => '<br />' . TEXT_INFO_NUMBER_OF_ORDERS . ' ' . $customers_orders->RecordCount() 
				);
				if ($customers_orders->RecordCount() != 0)
				{
					$contents[] = array(
						'text' => TEXT_INFO_LAST_ORDER . ' ' . zen_date_short($customers_orders->fields['date_purchased']) . '<br />' . TEXT_INFO_ORDERS_TOTAL . ' ' . $currencies->format($customers_orders->fields['order_total'], true, $customers_orders->fields['currency'], $customers_orders->fields['currency_value']) 
					);
				}
				$contents[] = array(
					'text' => '<br />' . TEXT_INFO_COUNTRY . ' ' . $cInfo->countries_name 
				);
				$contents[] = array(
					'text' => '<br />' . TEXT_INFO_NUMBER_OF_REVIEWS . ' ' . $cInfo->number_of_reviews 
				);
				$contents[] = array(
					'text' => '<br />' . CUSTOMERS_REFERRAL . ' ' . $cInfo->customers_referral 
				);
			}
			break;
	}
	
	if ((zen_not_null($heading)) && (zen_not_null($contents)))
	{
		echo '            <td width="25%" valign="top">' . "\n";
		
		$box = new box();
		echo $box->infoBox($heading, $contents);
		
		echo '            </td>' . "\n";
	}
	?>
          </tr>
							</table></td>
					</tr>
<?php
}
?>
    </table></td>
			<!-- body_text_eof //-->
		</tr>
	</table>
	<!-- body_eof //-->

	<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
	<br>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
