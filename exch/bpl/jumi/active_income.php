<?php

namespace BPL\Jumi\Active_Income;

require_once 'bpl/mods/income.php';
require_once 'bpl/mods/account_summary.php';
require_once 'bpl/mods/helpers.php';

use Joomla\CMS\Uri\Uri;

use function BPL\Mods\Account_Summary\row_referral_link;
use function BPL\Mods\Account_Summary\row_username;
use function BPL\Mods\Account_Summary\row_account_type;

//use function BPL\Mods\Account_Summary\row_balance;
use function BPL\Mods\Account_Summary\row_efund;
use function BPL\Mods\Account_Summary\row_points;
use function BPL\Mods\Account_Summary\row_daily_incentive;
use function BPL\Mods\Account_Summary\row_merchant;

use function BPL\Mods\Income\income_marketing;
use function BPL\Mods\Url_SEF\sef;

use function BPL\Mods\Helpers\application;
use function BPL\Mods\Helpers\session_get;
use function BPL\Mods\Helpers\settings;
use function BPL\Mods\Helpers\page_validate;
use function BPL\Mods\Helpers\page_reload;
use function BPL\Mods\Helpers\menu;
use function BPL\Mods\Helpers\user;
use function BPL\Mods\Helpers\db;

main();

/**
 *
 *
 * @since version
 */
function main()
{
	$user_id = session_get('user_id');

	page_validate();

	$str = menu();

	switch (session_get('usertype'))
	{
		case 'Admin':
			$str .= admin($user_id);

			break;
		case 'Member':
			$str .= member($user_id);

			break;
	}

	echo $str;
}

/**
 * @param $user_id
 * @param $type
 *
 * @return array|mixed
 *
 * @since version
 */
function user_power($user_id, $type)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_power_' . $type .
		' WHERE user_id = ' . $db->quote($user_id)
	)->loadObjectList();
}

/**
 * @param $user_id
 *
 * @return mixed|null
 *
 * @since version
 */
function binary($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_binary ' .
		'WHERE user_id = ' .
		$db->quote($user_id)
	)->loadObject();
}

/**
 * @param $user_id
 *
 * @return mixed|null
 *
 * @since version
 */
function user_binary($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users u ' .
		'INNER JOIN network_binary b ' .
		'ON u.id = b.user_id ' .
		'WHERE user_id = ' . $db->quote($user_id)
	)->loadObject();
}

/**
 * @param $user_id
 *
 * @return mixed|null
 *
 * @since version
 */
function user_unilevel($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_unilevel ' .
		'WHERE user_id = ' . $db->quote($user_id)
	)->loadObject();
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_income_active_total($user_id): string
{
	return '<tr>
	        <td><a href="javascript:void(0)">Total Income</a>:</td>
	        <td>' . number_format(income_marketing($user_id), 8) . ' ' .
		settings('ancillaries')->currency .
		'</td>
	    </tr>';
}

/**
 * @param $user_id
 *
 * @return array|mixed
 *
 * @since version
 */
function user_direct($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE sponsor_id = ' . $db->quote($user_id)
	)->loadObjectList();
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_direct_referral($user_id): string
{
	$settings_plans       = settings('plans');
	$settings_ancillaries = settings('ancillaries');

	$str = '';

	if ($settings_plans->direct_referral &&
		$settings_ancillaries->referral_mode === 'standard')
	{
		$str .= '<tr>
	            <td><a href="javascript:void(0)">Sponsored Members</a>:</td>
	            <td>' . count(user_direct($user_id)) . '<a style="float:right" href="' .
			sef(13) . '">View All Sponsored Members</a>
	            </td>
	        </tr>
	        <tr>
	            <td><a href="javascript:void(0)">' .
			$settings_plans->direct_referral_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->income_referral, 8) .
			' ' . $settings_ancillaries->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_indirect_referral($user_id): string
{
	$settings_plans = settings('plans');

	$user = user($user_id);

	$str = '';

	if ($settings_plans->indirect_referral &&
		settings('indirect_referral')->{$user->account_type . '_indirect_referral_level'})
	{
		$str .= '<tr>
	            <td><a href="javascript:void(0)">' .
			$settings_plans->indirect_referral_name . '</a>:</td>
	            <td>' . number_format($user->bonus_indirect_referral, 8) .
			' ' . settings('ancillaries')->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param         $user_id
 *
 * @return string
 *
 * @since version
 */
function row_binary($user_id): string
{
//	$app = application();

	$sp = settings('plans');
	$sb = settings('binary');

	$binary = binary($user_id);

	$str = '';

//	$flushout = $sb->hedge === 'flushout';

	if ($binary && ($sp->binary_pair || $sp->redundant_binary))
	{
		$user_binary = user_binary($user_id);

		$status = $user_binary->status;

//		if ($status === 'inactive')
//		{
//			$app->redirect(Uri::root(true) . '/' .
//				sef(2), 'Reactivate Your ' . $sp->binary_pair_name . '!', 'notice');
//		}

//		$account_type = $user_binary->account_type;

//		$reactivate_count = $binary->reactivate_count;
//		$income_cycle     = $binary->income_cycle;
//
//		$s_capping_cycle_max = $sb->{$account_type . '_capping_cycle_max'};
//		$s_maximum_income    = $sb->{$account_type . '_maximum_income'};

//		$reactivate = $reactivate_count >= $s_capping_cycle_max ?
//			($income_cycle >= $s_maximum_income ?
//				'<a style="float:right; color: orange" href="' . sef(110) . '">Upgrade Account</a>' : '') :
//			(($status === 'active'
//				|| $status === 'reactivated'
//				|| $status === 'graduate'
//			) ? '' :
//				'<a style="float:right" href="' . sef(120) . '">Reactivate Binary</a>');

		switch ($status)
		{
			case 'active':
				$flag = 'Active';
				break;
			case 'reactivated':
				$flag = 'Reactivated';
				break;
			case 'graduate':
				$flag = 'Maxed Out';
				break;
			default:
				$flag = 'Inactive';
				break;
		}

		$str .= '<tr>
            <td><a href="javascript:void(0)">' . $sp->binary_pair_name . ' (' . $flag . ')' . '</a>:</td>
           	<td>' . number_format($binary->income_cycle, 8) . ' ' .
			settings('ancillaries')->currency . /*($flushout ? '' : $reactivate) .*/
			'</td></tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_leadership_binary($user_id): string
{
	$settings_plans = settings('plans');

	$str = '';

	if ($settings_plans->leadership_binary &&
		$settings_plans->binary_pair &&
		binary($user_id))
	{
		$str .= '<tr>
	        <td><a href="javascript:void(0)">' .
			$settings_plans->leadership_binary_name . '</a>:</td>
	        <td>' . number_format(user($user_id)->bonus_leadership, 8) .
			' ' . settings('ancillaries')->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_leadership_passive($user_id): string
{
	$settings_plans = settings('plans');

	return $settings_plans->leadership_passive ? '<tr>
	            <td><a href="javascript:void(0)">' .
		$settings_plans->leadership_passive_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->bonus_leadership_passive, 8) .
		' ' . settings('ancillaries')->currency . '</td>
	        </tr>' : '';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_matrix($user_id): string
{
	$settings_plans = settings('plans');

	return $settings_plans->matrix ? '<tr>
	            <td><a href="javascript:void(0)">' .
		$settings_plans->matrix_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->bonus_matrix, 8) .
		' ' . settings('ancillaries')->currency . '</td>
	        </tr>' : '';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_harvest($user_id): string
{
	$settings_plans = settings('plans');

	return $settings_plans->harvest ? '<tr>
	        <td><a href="javascript:void(0)">' . $settings_plans->harvest_name . '</a>:</td>
	        <td>' . number_format(user($user_id)->bonus_harvest, 8) .
		' ' . settings('ancillaries')->currency . '</td>
	    </tr>' : '';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_power($user_id): string
{
	$settings_plans = settings('plans');

	$str = '';

	if ($settings_plans->power &&
		(user_power($user_id, 'executive') ||
			user_power($user_id, 'regular') ||
			user_power($user_id, 'associate') ||
			user_power($user_id, 'basic')))
	{
		$str .= '<tr>
	            <td><a href="javascript:void(0)">' .
			$settings_plans->power_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->bonus_power, 8) .
			' ' . settings('ancillaries')->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_unilevel($user_id): string
{
	$sp  = settings('plans');
	$sul = settings('unilevel');

	$unilevel_maintain = user_unilevel($user_id)->period_unilevel ?? 0;

	$user = user($user_id);

	$str = '';

	if ($sp->unilevel
		&& $sul->{$user->account_type . '_unilevel_level'}
		&& !empty(user_unilevel($user_id)))
	{
		$requirement  = $sul->{$user->account_type . '_unilevel_maintenance'};
		$offset       = abs($unilevel_maintain - $requirement);
		$has_maintain = $unilevel_maintain >= $requirement;

		$str .= '<tr>
	            <td><a href="javascript:void(0)">' .
			$sp->unilevel_name . '</a>:</td>
	            <td>' . number_format($user->unilevel, 8) .
			' ' . settings('ancillaries')->currency .
			'<a style="float:right" href="' . ($has_maintain ? sef(109) : sef(9)) . '">' .
			($has_maintain ? 'Active' : $offset . ' pts. to activate') . '</a></td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_royalty($user_id): string
{
	$settings_plans = settings('plans');

	return $settings_plans->royalty ? '<tr>
	            <td><a href="javascript:void(0)">' .
		$settings_plans->royalty_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->rank_reward, 8) .
		' ' . settings('ancillaries')->currency . '</td>
	        </tr>' : '';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_upline_support($user_id): string
{
	$settings_plans = settings('plans');

	return $settings_plans->upline_support ? '<tr>
	            <td><a href="javascript:void(0)">' .
		$settings_plans->upline_support_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->upline_support, 8) .
		' ' . settings('ancillaries')->currency . '</td>
	        </tr>' : '';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_passup($user_id): string
{
	$settings_plans = settings('plans');

	return $settings_plans->passup ? '<tr>
	            <td><a href="javascript:void(0)">' .
		$settings_plans->passup_name . '</a>:</td>
	            <td>' . number_format(user($user_id)->passup_bonus, 8) .
		' ' . settings('ancillaries')->currency . '</td>
	        </tr>' : '';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_elite($user_id): string
{
	$settings_plans = settings('plans');

	$user = user($user_id);

	$str = '';

	if ($settings_plans->elite_reward && $user->elite)
	{
		$str .= '<tr>
	            <td><a href="javascript:void(0)">' .
			$settings_plans->elite_reward_name . '</a>:</td>
	            <td>' . number_format($user->elite_reward, 8) .
			' ' . settings('ancillaries')->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_stockist($user_id): string
{
	$user = user($user_id);

	$str = '';

	if ($user->account_type === 'regular' && settings('entry')->regular_global)
	{
		$str .= '<tr>
	            <td><a href="javascript:void(0)">Stockist Bonus</a>:</td>
	            <td>' . number_format($user->stockist_bonus, 8) .
			' ' . settings('ancillaries')->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_franchise($user_id): string
{
	$user = user($user_id);

	$str = '';

	if ($user->account_type === 'executive' && settings('entry')->executive_global)
	{
		$str .= '<tr>
	            <td><a href="javascript:void(0)">Franchise Bonus</a>:</td>
	            <td>' . number_format($user->franchise_bonus, 8) .
			' ' . settings('ancillaries')->currency .
			'</td>
	        </tr>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function row_balance($user_id): string
{
	$sa = settings('ancillaries');
	$sp = settings('plans');

	$user = user($user_id);

	$field_balance = $sa->withdrawal_mode === 'standard' ?
		'balance' : 'payout_transfer';

	$reactivate = $user->status_global === 'active' ? '' :
		'<a style="float:right" href="' . sef(130) . '">Reactivate Account</a>';

	return '<tr>
	        <td><a href="javascript:void(0)">Balance</a>:</td>
	        <td>' . number_format(user($user_id)->{$field_balance}, 8) .
		' ' . $sa->currency . (!$sp->account_freeze ? '' : $reactivate) .
		'</td>
	    </tr>';
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function table_binary_summary($user_id): string
{
	$sp = settings('plans');

	$binary = binary($user_id);

	$str = '';

	if ($binary && ($sp->binary_pair || $sp->redundant_binary))
	{
		$str .= '<hr class="uk-grid-divider">
		    <h3>' . $sp->binary_pair_name . ' Summary</h3>
		    <table class="category table table-striped table-bordered table-hover">
		        <tr>
		            <td>Group Points (A/B):</td>
		            <td>' . $binary->ctr_left . ' / ' .
			$binary->ctr_right . '<a style="float:right" href="' . sef(14) . '">View All Downlines</a>
		            </td>
		        </tr>
		        <tr>
		            <td><a href="javascript:void(0)">Counted Matches</a>:</td>
		            <td>' . number_format($binary->pairs, 8) . '</td>
		        </tr>';

		$str .= settings('binary')->{user($user_id)->account_type . '_pairs_safety'} ? '<tr>
	            <td><a href="javascript:void(0)">Match Reward</a>:</td>
	            <td>' . $binary->income_cycle . '</td>
	        </tr>
	        <tr>
	            <td><a href="javascript:void(0)">Reward Points</a>:</td>
	            <td>' . $binary->income_giftcheck . '</td>
	        </tr>
	        <tr>
	            <td><a href="javascript:void(0)">Flush Out (pts.)</a>:</td>
	            <td>' . number_format($binary->income_flushout) . '</td>
	        </tr>
	        <tr>
	            <td><a href="javascript:void(0)">Total Match Reward Today</a>:</td>
	            <td>' . $binary->pairs_today_total . '</td>
	        </tr>' : '';

		$str .= '</table>';
	}

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function admin($user_id): string
{
	$str = page_reload();

	$str .= '<h2>Profit Chart</h2>
	<table class="category table table-striped table-bordered table-hover">';

	$str .= core($user_id);

	return $str;
}

/**
 *
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function member($user_id): string
{
	$str = page_reload();

	$sp = settings('plans');

	if ($sp->binary_pair)
	{
		$user_binary = user_binary($user_id);

		$status = $user_binary->status;

		$reactivate_count  = $user_binary->reactivate_count;
		$capping_cycle_max = settings('binary')->{$user_binary->account_type . '_capping_cycle_max'};

		if ($status === 'inactive' && $reactivate_count < $capping_cycle_max)
		{
			$str .= '<div class="uk-width-1-1 uk-grid-margin uk-row-first">
                    <div class="uk-alert uk-alert-warning" data-uk-alert="">
                        <a class="uk-alert-close uk-close"></a>
                        <p>Reactivate Your ' . $sp->binary_pair_name . '!</p>
                    </div>
                </div>';
		}
	}

	$str .= '<h2>Dashboard</h2>
    <table class="category table table-striped table-bordered table-hover" style="width: 100%;">';

	$str .= core($user_id);

	$str .= user_info(user($user_id));

	return $str;
}

function user_info($user): string
{
	$str = '<h3>Rewards Summary</h3>';

	$str .= '<table class="category table table-striped table-bordered table-hover">';

	$str .= row_referral_link($user);
	//$str .= row_username($user);
	//$str .= row_account_type($user);
//	$str .= row_balance($user);
	//$str .= row_efund($user);
	$str .= row_points($user);
	$str .= row_daily_incentive($user);
	$str .= row_merchant($user);

	$str .= '</table>';

	return $str;
}

/**
 * @param $user_id
 *
 * @return string
 *
 * @since version
 */
function core($user_id): string
{
	$str = row_direct_referral($user_id);
	$str .= row_indirect_referral($user_id);
	$str .= row_binary($user_id);
	$str .= row_leadership_binary($user_id);
	$str .= row_leadership_passive($user_id);
	$str .= row_matrix($user_id);
	$str .= row_harvest($user_id);
	$str .= row_power($user_id);
	$str .= row_unilevel($user_id);
	$str .= row_royalty($user_id);
	$str .= row_upline_support($user_id);
	$str .= row_passup($user_id);
	$str .= row_elite($user_id);
	$str .= row_stockist($user_id);
	$str .= row_franchise($user_id);
	$str .= row_income_active_total($user_id);
	$str .= row_balance($user_id);
	$str .= '</table>';

	$str .= table_binary_summary($user_id);

	return $str;
}