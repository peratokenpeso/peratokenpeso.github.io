<?php

namespace BPL\Jumi\Fixed_Daily;

require_once 'bpl/ajax/ajaxer/table_fixed_daily.php';
require_once 'bpl/mods/time_remaining.php';
require_once 'bpl/mods/table_daily_interest.php';
require_once 'bpl/mods/helpers.php';

use Exception;

use function BPL\Ajax\Ajaxer\Table_Fixed_Daily\main as ajax_table_fixed_daily;
use function BPL\Mods\Table_Daily_Interest\main as table_daily;

use function BPL\Mods\Url_SEF\sef;

use function BPL\Mods\Helpers\session_get;
use function BPL\Mods\Helpers\page_validate;
use function BPL\Mods\Helpers\menu;
use function BPL\Mods\Helpers\db;
use function BPL\Mods\Helpers\settings;
use function BPL\Mods\Helpers\user;

main();

/**
 *
 *
 * @since version
 */
function main()
{
	page_validate();

	$str = menu();

	try
	{
		$str .= fixed_daily(session_get('user_id'));
	}
	catch (Exception $e)
	{
	}

	echo $str;
}

/**
 * @throws Exception
 *
 * @since version
 */
function fixed_daily($user_id): string
{
	$settings_plans = settings('plans');
	$settings_entry = settings('entry');

	$user = user($user_id);

	$str = '';

	if ($user->account_type !== 'starter')
	{
		$str .= '<h2>' . $settings_entry->{$user->account_type .
			'_package_name'} . ' ' . $settings_plans->fixed_daily_name .
			'<span style="float: right; font-size: x-large; font-weight: bold"><span style="float: right">
	        <a href="' . sef(18) .
			'" class="uk-button uk-button-primary">Wallet</a></span></span></h2>
			<div class="table-responsive">
				<table class="category table table-bordered table-hover">
					<tr>
						<td rowspan="3" style="text-align: center; width: 33%; vertical-align: middle">
							<div class="table-responsive" id="table_fixed_daily">' .
			table_fixed_daily($user_id) . '</div>
						</td>
					</tr>
				</table>
			</div>';

		$str .= ajax_table_fixed_daily($user_id);
	}

	return $str;
}

/**
 * @param $user_id
 *
 * @return mixed|null
 *
 * @since version
 */
function user_fixed_daily($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_fixed_daily ' .
		'WHERE user_id = ' . $db->quote($user_id)
	)->loadObject();
}

/**
 * @throws Exception
 *
 * @since version
 */
function table_fixed_daily($user_id): string
{
	$settings_investment = settings('investment');

	$user = user($user_id);

	$account_type = $user->account_type;

//	$entry    = settings('entry')->{$account_type . '_entry'};
	$principal = $settings_investment->{$account_type . '_fixed_daily_principal'};
	$interval  = $settings_investment->{$account_type . '_fixed_daily_interval'};
	$maturity  = $settings_investment->{$account_type . '_fixed_daily_maturity'};

	$user_fixed_daily = user_fixed_daily($user_id);

	return table_daily(
		$principal,
		$user->date_activated,
		$user_fixed_daily->value_last,
		$user_fixed_daily->day,
		$user_fixed_daily->processing,
		$maturity,
		$interval
	);
}