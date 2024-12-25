<?php

namespace BPL\Jumi\Join;

require_once 'bpl/mods/ajax.php';
require_once 'bpl/mods/query.php';
require_once 'bpl/mods/helpers.php';
require_once 'bpl/mods/terms.php';

use Exception;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;
use Joomla\CMS\HTML\HTMLHelper;

use function BPL\Mods\Ajax\check_input;
use function BPL\Mods\Ajax\check_position;

use function BPL\Mods\Database\Query\insert;

use function BPL\Mods\Url_SEF\sef;
use function BPL\Mods\Url_SEF\qs;

use function BPL\Mods\Helpers\db;
use function BPL\Mods\Helpers\user;
use function BPL\Mods\Helpers\settings;
use function BPL\Mods\Helpers\application;
use function BPL\Mods\Helpers\session_set;
use function BPL\Mods\Helpers\session_get;
use function BPL\Mods\Helpers\input_get;
use function BPL\Mods\Helpers\menu;
use function BPL\Mods\Helpers\time;

use function BPL\Mods\Terms\main as terms;

main();

/**
 *
 *
 * @since version
 */
function main()
{
	$str = menu();

	session_set('edit', false);

	if (input_get('username') === '') {
		try {
			$str .= view_form();
		} catch (Exception $e) {
		}
	} else {
		process_form();
	}

	echo $str;
}

/**
 *
 * @return string
 *
 * @since version
 */
function display_loader(): string
{
	return '
    <div class="wave">
        <div class="ball"></div>
        <div class="ball"></div>
        <div class="ball"></div>
        <div class="ball"></div>
        <div class="ball"></div>
    </div>
    <style>
        .wave {
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .ball {
            width: 10px; /* Further reduce width */
            height: 10px; /* Further reduce height */
            border-radius: 50%;
            margin: 0 3px; /* Reduce margin for closer spacing */
            background-color: #6c5ce7;
            animation: wave 1s ease-in-out infinite;
        }

        @keyframes wave {
            0% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px); /* Further reduce bounce height */
            }
            100% {
                transform: translateY(0);
            }
        }

        .ball:nth-child(2) {
            animation-delay: -0.2s;
        }

        .ball:nth-child(3) {
            animation-delay: -0.4s;
        }

        .ball:nth-child(4) {
            animation-delay: -0.6s;
        }

        .ball:nth-child(5) {
            animation-delay: -0.8s;
        }
    </style>';
}

/**
 *
 * @return string
 *
 * @since version
 */
function view_logo(): string
{
	$img = 'images/Logo_Yellow_P.gif';

	$logo1 = '<svg data-jdenticon-value="' . time() . '" width="80" height="80"></svg>';
	$logo2 = '<a href="../"><img src="' . $img . '" class="img-responsive" alt=""></a>';

	$str = '<div style="background-color: transparent; text-align: center; padding: 5px">' .
		(!1 ? $logo1 : $logo2) . /*('<a href="../">
<img src="' . $img . '" class="img-responsive" style="padding: 5px; margin-left: 33px" alt="">
</a>') .*/
		'</div>';

	$str .= identicon_js();

	return $str;
}

function identicon_js(): string
{
	return '<script src="https://cdn.jsdelivr.net/npm/jdenticon@3.1.1/dist/jdenticon.min.js" async
        integrity="sha384-l0/0sn63N3mskDgRYJZA6Mogihu0VY3CusdLMiwpJ9LFPklOARUcOiWEIGGmFELx" crossorigin="anonymous">
</script>';
}

/**
 * @return string
 *
 * @since version
 */
function view_form(): string
{
	$usertype = session_get('usertype');

	$admintype = session_get('admintype');
	$sid = input_get('s');

	$s_username = session_get('s_username');
	$s_email = session_get('s_email');
	$s_password = session_get('s_password');
	$s_sponsor = session_get('s_sponsor');
	$edit = session_get('edit');

	$sponsor = sponsor();

	$str = '<div' . ($usertype === '' ? ' style="margin-top: -100px;"' : '') . '>';

	$str .= $usertype === '' ? view_logo() : '';
	$str .= '<h1>Register</h1>
    <p>Please fill up all fields marked *';
	$str .= '</p>';
	$str .= '<form name="regForm" method="post" 
		enctype="multipart/form-data" onsubmit="submit.disabled = true; return true;" onsubmit="return validateForm()">
        <table class="category table table-striped table-bordered table-hover">
            <tr>
                <td><label for="username">Username: *</label></td>
                <td><input type="text"
                           name="username"
                           id="username"
                           value="' . $s_username . '" size="40"
                           required="required"
                           style="float:left"><a href="javascript:void(0)" onClick="checkInput(\'username\')" 
                           class="uk-button uk-button-primary" style="float:left">Check Availability</a>
                    <div style="width:200px; height:20px; font-weight:bold; float:left; padding:7px 0 0 10px;"
                         id="usernameDiv"></div>
                </td>
            </tr>
            <tr>
                <td><label for="email">Email:</label></td>
                <td><input type="text" name="email" value="' . $s_email . '" id="email" size="40"></td>
            </tr>
            <tr>
                <td><label for="password1">Password: *</label></td>
                <td><input type="password"
                           name="password1"
                           value="' . $s_password . '"
                           id="password1"
                           size="40"
                           required></td>
            </tr>
            <tr>
                <td><label for="password2">Confirm Password: *</label></td>
                <td><input type="password"
                           name="password2"
                           value="' . $s_password . '"
                           id="password2"
                           size="40"
                           required></td>
            </tr>';

	if (settings('plans')->direct_referral) {
		$str .= '<tr>
               <td><label for="sponsor">Sponsor Username: *</label></td>
               <td><input type="text"
                          name="sponsor"
                          id="sponsor"
                          size="40"
                          value="' . ($s_sponsor && !isset($sponsor) ? $s_sponsor : $sponsor) . '"
                          required="required"';
		$str .= ($sid !== '' ? ' readonly' : '');
		$str .= ' style="float:left"><a href="javascript:void(0)" onClick="checkInput(\'sponsor\')"
class="uk-button uk-button-primary" style="float:left">Check Availability</a>
	    <div style="width:200px; height:20px; font-weight:bold; float:left; padding:7px 0 0 10px;"
	         id="sponsorDiv"></div>
	    </td>
	    </tr>';
	}

	$str .= (($edit && $admintype === 'Super') ? '<tr>
            <td><label for="date">Date Registered:</label></td>
            <td><input type="text" name="date" value="' /*.
date('Y - m - d G:i', $s_date == '' ? 0 : $s_date)*/
		. '" id="date"
                       size="40"></td>
        </tr>' : '');
	$str .= '<tr>
        <td colspan="2">
            <div style="float: left">
                <label><input id="terms"
                              type="checkbox"
                              style="margin-top: -3px"> <a href="javascript:void(0)"
                                                           data-uk-modal="{target:\'#modal-1\'}">
                        I Agree to the Terms &amp; Conditions </a>
                </label>
            </div>
            <div style="text-align: center">
				<input id="register"
					onClick="disableMenu()"
						type="submit"
						value="Register"
						name="submit"
						class="uk-button
						uk-button-primary">';
	$str .= (!$usertype ? '<span style="float: right; font-weight: bold"><a
                    href="' . sef(43) . '">Log In</a></span>' : '');
	$str .= '</div>
        </td>
    </tr>
    </table>
	' . HTMLHelper::_('form.token') .
		'</form>';

	$str .= '<div>';

	$str .= terms();
	$str .= check_input();
	$str .= check_position();
	$str .= js();

	return $str;
}

/**
 * // *
 * @param $username
 * @param $password1
 * @param $password2
 * @param $code
 * @param $sponsor
 * @param $admintype
 * @param $edit
 *
 * @since version
 */
function validate_input(
	$username,
	$password1,
	$password2,
	$code,
	$sponsor,
	$admintype,
	$edit
) {
	$settings_plans = settings('plans');
	$settings_ancillaries = settings('ancillaries');

	$payment_mode = $settings_ancillaries->payment_mode;

	if ($edit && $admintype === 'Super') {
		$date = input_get('date', '', 'RAW');
	}

	$app = application();

	if ($username === '') {
		$err = 'Please specify your Username.<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if ($password1 === '') {
		$err = 'Please specify your Password.<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if ($password2 === '') {
		$err = 'Please specify your Password confirmation.<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if ($edit && !isset($date)) {
		$err = 'Please specify your Registration Date.<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if (count(user_username_unblock($username))) {
		$err = 'Username already taken.<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if ($payment_mode === 'CODE' && $code === '') {
		$err = 'Please specify your Registration Code.<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if ($password1 !== $password2) {
		$err = 'Your Passwords do not match!<br>';
		$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
	}

	if ($settings_plans->direct_referral) {
		$user_sponsor = user_username($sponsor);

		if ($sponsor === '') {
			$err = 'Please specify your Sponsor Username.<br>';
			$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
		}

		if (empty($user_sponsor) || (empty(user_username_active($sponsor)))) {
			$err = 'Invalid Sponsor Username!<br>';
			$app->redirect(Uri::root(true) . '/' . sef(144), $err, 'error');
		}
	}
}

/**
 * @since version
 */
function process_form()
{
	echo display_loader();

	$admintype = session_get('admintype');
	$edit = session_get('edit');

	$username = input_get('username');
	$password1 = input_get('password1');
	$password2 = input_get('password2');
	$code = input_get('code');
	$sponsor = input_get('sponsor');
	$email = input_get('email', '', 'RAW');

	$app = application();

	Session::checkToken() or $app->redirect(Uri::root(true) .
		'/' . sef(144), 'Invalid Transaction!', 'error');

	$email = substr($email, 0, 60);

	session_set_date($admintype, $edit);

	session_set('s_username', $username);
	session_set('s_email', $email);
	session_set('s_password', $password1);
	//	session_set('s_sponsor', $sponsor);

	validate_input(
		$username,
		$password1,
		$password2,
		$code,
		$sponsor,
		$admintype,
		$edit
	);

	if (insert_user($username, $password1, $sponsor, $email, $admintype, $edit)) {
		$app->redirect(Uri::root(true) . '/' . sef(144), $username .
			' has joined successfully!', 'success');
	} else {
		$app->redirect(Uri::root(true) . '/' . sef(144), 'Something went wrong!', 'error');
	}
}

/**
 * @param $username
 * @param $password
 * @param $sponsor
 * @param $email
 * @param $admintype
 * @param $edit
 *
 * @return false|mixed
 *
 * @since version
 */
function insert_user($username, $password, $sponsor, $email, $admintype, $edit)
{
	$db = db();

	$payment_mode = settings('ancillaries')->payment_mode;

	$email = substr($email, 0, 60);

	$date = input_get_date($admintype, $edit);

	// sponsor
	$sponsor_id = '1';

	if (settings('plans')->direct_referral) {
		$user_sponsor = user_username($sponsor);

		if (!empty($user_sponsor)) {
			$sponsor_id = $user_sponsor[0]->id;
		}
	}

	$columns_user_insert = [
		'username',
		'password',
		'sponsor_id',
		'date_registered',
		'date_activated',
		'email'
	];

	$date_registered = ($edit && isset($date) ? $db->quote($date) : $db->quote(time()));

	if ($payment_mode === 'CODE') {
		$date_activated = $date_registered;
	} else {
		$date_activated = ($edit && (int) $date !== 0 ? $db->quote($date) : $db->quote(0));
	}

	$values_user_insert = [
		$db->quote($username),
		$db->quote(md5($password)),
		$db->quote($sponsor_id),
		$date_registered,
		$date_activated,
		$db->quote($email)
	];

	if ($payment_mode === 'ECASH') {
		$columns_user_insert[] = 'account_type';
		$values_user_insert[] = $db->quote('starter');
	}

	return insert(
		'network_users',
		$columns_user_insert,
		$values_user_insert
	);
}

/**
 * @param $code
 *
 * @return string
 *
 * @since version
 */
function code_type($code): string
{
	$code_type = 'starter';

	$code_user = code_user($code);

	if ($code_user) {
		$code_type = $code_user[0]->type;
	}

	return $code_type;
}

/**
 * @param $code
 *
 * @return array|mixed
 *
 * @since version
 */
function code_user($code)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_codes ' .
		'WHERE code = ' . $db->quote($code)/* .
' AND user_id = ' . $db->quote($insert_id)*/
	)->loadObjectList();
}

/**
 * @param $insert_id
 * @param $code_type
 * @param $username
 * @param $sponsor
 *
 *
 * @since version
 */
function log_registration_activity($insert_id, $code_type, $username, $sponsor)
{
	$db = db();

	// sponsor
	$sponsor_id = '';
	$sponsor_name = '';

	$user_sponsor = user_username($sponsor);

	if (!empty($user_sponsor)) {
		$sponsor_id = $user_sponsor[0]->id;
		$sponsor_name = $user_sponsor[0]->username;
	}

	$code_type_mod = settings('entry')->{$code_type . '_package_name'};

	$activity = '<b>Sign up: </b><a href="' . sef(44) . qs() . 'uid=' . $insert_id . '">' .
		$username . ' (' . ucfirst($code_type_mod) . ')' . '</a>, sponsored by <a href="' .
		sef(44) . qs() . 'uid=' . $sponsor_id . '">' . $sponsor_name . '</a>.';

	$field = [
		'user_id',
		'sponsor_id',
		'activity',
		'activity_date'
	];

	$value = [
		$db->quote($insert_id),
		$db->quote($sponsor_id),
		$db->quote($activity),
		$db->quote(time())
	];

	// activity
	insert('network_activity', $field, $value);
}

/**
 * @param $insert_id
 * @param $code_type
 * @param $username
 * @param $sponsor
 *
 *
 * @since version
 */
function log_registration_transactions($insert_id, $code_type, $username, $sponsor)
{
	$db = db();

	$entry = settings('entry')->{$code_type . '_entry'};

	// sponsor
	$sponsor_id = '';
	$sponsor_name = '';

	$user_sponsor = user_username($sponsor);

	if (!empty($user_sponsor)) {
		$sponsor_id = $user_sponsor[0]->id;
		$sponsor_name = $user_sponsor[0]->username;
	}

	$details = '<b>Sign up: ' . number_format($entry, 2) . settings('ancillaries')->currency .
		' paid by </b><a href="' . sef(44) . qs() . 'uid=' . $insert_id . '">' . $username .
		' (' . ucfirst(settings('entry')->{$code_type . '_package_name'}) . ')' .
		'</a>, sponsored by <a href="' . sef(44) . qs() . 'uid=' . $sponsor_id . '">' . $sponsor_name . '</a>.';

	// transactions
	insert(
		'network_transactions',
		[
			'user_id',
			'transaction',
			'details',
			'value',
			'balance',
			'transaction_date'
		],
		[
			$db->quote($insert_id),
			$db->quote('Sign Up'),
			$db->quote($details),
			$db->quote($entry),
			$db->quote(0),
			$db->quote(time())
		]
	);
}

/**
 *
 * @return string
 *
 * @since version
 */
function js(): string
{
	$str = '<script>';
	$str .= 'function validateForm() {
            if (document.forms["regForm"]["username"].value === ""
                || document.forms["regForm"]["password1"].value === ""
                || document.forms["regForm"]["password2"].value === ""
                || document.forms["regForm"]["code"].value === ""
                || document.forms["regForm"]["sponsor"].value === ""
                || document.forms["regForm"]["upline"].value === "") {
                alert("Please specify all required info.");
                
                return false;
            } else {
                return true;
            }
        }';

	$str .= 'function disableMenu() {
				document.getElementById("menu").disabled = true;
			}';

	$str .= '(function ($) {
            $("#register").attr("disabled", true);

            $("#terms").change(function () {
                if (this.checked) {
                    $("#register").attr("disabled", false);
                } else {
                    $("#register").attr("disabled", true);
                }
                
                return false;
            });
        })(jQuery);';
	$str .= '</script>';

	return $str;
}

/**
 *
 * @return string
 *
 * @since version
 */
function sponsor(): string
{
	$sid = input_get('s');
	$user_id = session_get('user_id');

	$sponsor = '';

	if ($sid !== '') {
		$sponsor = $sid;
	} elseif ($user_id !== '') {
		$sponsor = user($user_id)->username ?? '';
	}

	return $sponsor;
}

/**
 * @param $username
 *
 * @return array|mixed
 *
 * @since version
 */
function user_username($username)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE username = ' . $db->quote($username)
	)->loadObjectList();
}

/**
 * @param $username
 *
 * @return array|mixed
 *
 * @since version
 */
function user_username_active($username)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE username = ' . $db->quote($username) .
		' AND account_type <> ' . $db->quote('starter')
	)->loadObjectList();
}

/**
 * @param $username
 *
 * @return array|mixed
 *
 * @since version
 */
function user_username_unblock($username)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE username = ' . $db->quote($username) .
		' AND block = ' . $db->quote(0)
	)->loadObjectList();
}

/**
 *
 * @param $admintype
 * @param $edit
 *
 * @return string
 *
 * @since version
 */
function input_get_date($admintype, $edit): string
{
	$date = time();

	if ($edit && $admintype === 'Super') {
		$date = input_get('date', 0, 'RAW');
	}

	return $date;
}

/**
 * @param $admintype
 * @param $edit
 *
 *
 * @since version
 */
function session_set_date($admintype, $edit)
{
	$date = input_get_date($admintype, $edit);

	if ($edit && $admintype === 'Super' && (int) $date !== 0) {
		session_set('s_date', $date);
	}
}