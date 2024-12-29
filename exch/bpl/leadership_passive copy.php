<?php

namespace BPL\Leadership_Passive;

require_once 'bpl/mods/query.php';
require_once 'bpl/mods/helpers.php';

use function BPL\Mods\Database\Query\update;
use function BPL\Mods\Database\Query\insert;
use function BPL\Mods\Url_SEF\sef;
use function BPL\Mods\Url_SEF\qs;
use function BPL\Mods\Helpers\db;
use function BPL\Mods\Helpers\user;
use function BPL\Mods\Helpers\users;
use function BPL\Mods\Helpers\settings;

/**
 * Main function to process leadership passive bonuses for all users.
 * This function iterates through all users and processes their leadership passive bonuses.
 *
 * @since version
 */
function main()
{
	$slp = settings('leadership_passive');

	foreach (users() as $user) {
		process_user_leadership_passive($user, $slp);
	}
}

/**
 * Process leadership passive bonus for a single user.
 * This function calculates and updates the leadership passive bonus for a given user.
 *
 * @param object $user The user object.
 * @param object $slp  Leadership passive settings.
 *
 * @since version
 */
function process_user_leadership_passive($user, $slp)
{
	$account_type = $user->account_type;
	$count_directs = count(user_directs($user->user_id));

	$type_level = $slp->{$account_type . '_leadership_passive_level'};
	$required_directs = $slp->{$account_type . '_leadership_passive_sponsored'};
	$max_daily_income = $slp->{$account_type . '_leadership_passive_max_daily_income'};
	$income_max = $slp->{$account_type . '_leadership_passive_maximum'};

	$user_bonus_lp = $user->bonus_leadership_passive;
	$ulp = user_leadership_passive($user->id);
	$income_today = $ulp->income_today;

	// Check if the user qualifies for leadership passive bonus
	if ($type_level > 0 && $count_directs >= $required_directs) {
		$lp_total = bonus_total($user);
		$lp_add = $lp_total - $ulp->bonus_leadership_passive_last;

		if ($lp_add > 0) {
			// Apply daily and maximum income limits
			if ($max_daily_income > 0 && ($income_today + $lp_add) >= $max_daily_income) {
				$lp_add = non_zero($max_daily_income - $income_today);
			}

			if ($income_max > 0 && ($user_bonus_lp + $lp_add) >= $income_max) {
				$lp_add = non_zero($income_max - $user_bonus_lp);
			}

			// Update leadership passive and user records
			update_leadership_passive($lp_add, $lp_total, $user->id);
			update_user($lp_add, $user->id);
			log_activity($user, $lp_total);
		}
	}
}

/**
 * Insert a new record into the network_leadership_passive table.
 * This function is called when a user becomes eligible for leadership passive bonuses.
 *
 * @param int $user_id The user ID.
 *
 * @since version
 */
function insert_leadership_passive($user_id)
{
	// Check if the user already has a leadership passive record
	if (empty(user_leadership_passive($user_id))) {
		insert(
			'network_leadership_passive',
			['user_id'],
			[db()->quote($user_id)]
		);
	}
}

/**
 * Ensure the value is non-negative.
 *
 * @param mixed $value The value to check.
 * @return int|mixed The non-negative value.
 *
 * @since version
 */
function non_zero($value)
{
	return $value < 0 ? 0 : $value;
}

/**
 * Calculate the total leadership passive bonus for a user.
 * This function calculates the total bonus based on the user's account type and level.
 *
 * @param object $user The user object.
 * @return float|int The total bonus.
 *
 * @since version
 */
function bonus_total($user)
{
	$slp = settings('leadership_passive');
	$account_type = $user->account_type;
	$required_directs = $slp->{$account_type . '_leadership_passive_sponsored'};
	$type_level = $slp->{$account_type . '_leadership_passive_level'};

	$total = 0;

	// Check if the user has the required number of direct referrals
	if (count(user_directs($user->id)) >= $required_directs) {
		// Calculate bonus for each level
		for ($i = 1; $i <= $type_level; $i++) {
			$total += leadership_passive_level($i, $user);
		}
	}

	return $total;
}

/**
 * Calculate the leadership passive bonus for a specific level.
 * This function calculates the bonus for a given level based on the user's direct and indirect referrals.
 *
 * @param int $level The level.
 * @param object $user The user object.
 * @return float|int The bonus.
 *
 * @since version
 */
function leadership_passive_level($level, $user)
{
	$users = get_level_users($level, $user);
	return bonus_leadership_passive($level, $users);
}

/**
 * Get users for a specific level.
 * This function retrieves users for a given level based on the user's direct and indirect referrals.
 *
 * @param int $level The level.
 * @param object $user The user object.
 * @return array The users.
 *
 * @since version
 */
function get_level_users($level, $user)
{
	$users = [$user];
	for ($i = 1; $i <= $level; $i++) {
		$users = level_directs($users);
	}
	return $users;
}

/**
 * Calculate the leadership passive bonus for a specific level and users.
 * This function calculates the bonus based on the user's passive income and share percentage.
 *
 * @param int $level The level.
 * @param array $users The users.
 * @return float|int The bonus.
 *
 * @since version
 */
function bonus_leadership_passive($level, $users)
{
	$bonus = 0;

	if (!empty($users)) {
		foreach ($users as $user) {
			$account_type = $user->account_type;
			$slp = settings('leadership_passive');

			$share = $slp->{$account_type . '_leadership_passive_share_' . $level} / 100;
			$share_cut = $slp->{$account_type . '_leadership_passive_share_cut_' . $level} / 100;

			$passive = $user->top_up_interest + $user->fast_track_interest + $user->fixed_daily_interest + $user->compound_daily_interest;
			$bonus += $passive * $share * $share_cut;
		}
	}

	return $bonus;
}

/**
 * Get the number of members for a specific level.
 * This function counts the number of users for a given level.
 *
 * @param int $level The level.
 * @param object $user The user object.
 * @return int The number of members.
 *
 * @since version
 */
function members_level($level, $user)
{
	$users = get_level_users($level, $user);
	return count($users);
}

/**
 * Calculate the total number of members across all levels.
 * This function sums up the number of users across all levels.
 *
 * @param object $user The user object.
 * @return int The total number of members.
 *
 * @since version
 */
function members_total($user)
{
	$total = 0;
	$slp = settings('leadership_passive');
	$type_level = $slp->{$user->account_type . '_leadership_passive_level'};

	for ($i = 1; $i <= $type_level; $i++) {
		$total += members_level($i, $user);
	}

	return $total;
}

/**
 * Generate the view for a user's leadership passive status.
 * This function generates an HTML table displaying the user's leadership passive details.
 *
 * @param int $user_id The user ID.
 * @return string The HTML string.
 *
 * @since version
 */
function view($user_id): string
{
	$user = user($user_id);
	$account_type = $user->account_type;
	$slp = settings('leadership_passive');

	$required_directs = $slp->{$account_type . '_leadership_passive_sponsored'};
	$level = $slp->{$account_type . '_leadership_passive_level'};

	$status = count(user_directs($user->id)) >= $required_directs ? '' : ' (inactive)';
	$currency = settings('ancillaries')->currency;

	$str = '<h3>List ' . settings('plans')->leadership_passive_name . '</h3>
        <table class="category table table-striped table-bordered table-hover">
            <thead>
            <tr>
                <th><div style="text-align: center"><h4>Level</h4></div></th>
                <th><div style="text-align: center"><h4>Member</h4></div></th>
                <th><div style="text-align: center"><h4>Bonus (' . $currency . ')</h4></div></th>
                <th><div style="text-align: center"><h4>Allocation (%)</h4></div></th>
            </tr>
            </thead>
            <tbody>';

	for ($i = 1; $i <= $level; $i++) {
		$str .= view_row($i, $user);
	}

	$str .= '<tr>
                <td><div style="text-align: center"><strong>Total' . $status . '</strong></div></td>
                <td><div style="text-align: center">' . members_total($user) . '</div></td>
                <td><div style="text-align: center">' . number_format(bonus_total($user), 8) . '</div></td>
                <td><div style="text-align: center">N/A</div></td>
            </tr>
            </tbody>
        </table>';

	return $str;
}

/**
 * Generate a table row for a specific level.
 * This function generates an HTML table row for a given level.
 *
 * @param int $level The level.
 * @param object $user The user object.
 * @return string The HTML string.
 *
 * @since version
 */
function view_row($level, $user): string
{
	$members = members_level($level, $user);
	$bonus = leadership_passive_level($level, $user);

	$slp = settings('leadership_passive');
	$share = $slp->{$user->account_type . '_leadership_passive_share_' . $level};
	$share_cut = $slp->{$user->account_type . '_leadership_passive_share_cut_' . $level};
	$percentage = $share * $share_cut / 100;

	$str = '<tr>
                <td><div style="text-align: center"' . ($level === 1 ? ' style="color: red"' : '') . '>
                    <strong>' . ($level === 1 ? ' (Direct)' : $level) . '</strong></div></td>
                <td><div style="text-align: center"' . ($level === 1 ? ' style="color: red"' : '') . '>' .
		($level === 1 ? '(' . $members . ')' : $members) . '</div></td>
                <td><div style="text-align: center"' . ($level === 1 ? ' style="color: red"' : '') . '>' .
		($level === 1 ? '(' . number_format($bonus, 8) . ')' : number_format($bonus, 8)) . '</div></td>
                <td><div style="text-align: center"' . ($level === 1 ? ' style="color: red"' : '') . '>' .
		($level === 1 ? '(' . number_format($percentage, 2) . ')' : number_format($percentage, 2)) . '</div></td>
            </tr>';

	return $str;
}

/**
 * Retrieve direct referrals for a given list of users.
 * This function takes an array of users and returns their direct referrals.
 *
 * @param array $lvl_1 An array of user objects for whom direct referrals are to be fetched.
 * @return array An array of direct referrals for the provided users.
 *
 * @since version
 */
function level_directs(array $lvl_1 = []): array
{
	$lvl_directs = [];

	if (!empty($lvl_1)) {
		foreach ($lvl_1 as $s1) {
			$directs = user_directs($s1->id);

			if (!empty($directs)) {
				foreach ($directs as $direct) {
					$lvl_directs[] = $direct;
				}
			}
		}
	}

	return $lvl_directs;
}

/**
 * Get direct referrals for a given sponsor.
 * This function retrieves all direct referrals for a given sponsor ID.
 *
 * @param int $sponsor_id The sponsor ID.
 * @return array The list of direct referrals.
 *
 * @since version
 */
function user_directs($sponsor_id): array
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE account_type <> ' . $db->quote('starter') .
		'AND sponsor_id = ' . $db->quote($sponsor_id)
	)->loadObjectList();
}

/**
 * Get leadership passive details for a user.
 * This function retrieves leadership passive details for a given user ID.
 *
 * @param int $user_id The user ID.
 * @return mixed|null The leadership passive details.
 *
 * @since version
 */
function user_leadership_passive($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_leadership_passive ' .
		'WHERE user_id = ' . $db->quote($user_id)
	)->loadObject();
}

/**
 * Update leadership passive details for a user.
 * This function updates the leadership passive bonus and related fields for a given user.
 *
 * @param float $leadership_add The bonus to add.
 * @param float $leadership The total bonus.
 * @param int $user_id The user ID.
 *
 * @since version
 */
function update_leadership_passive($leadership_add, $leadership, $user_id)
{
	$db = db();

	update(
		'network_leadership_passive',
		[
			'bonus_leadership_passive = bonus_leadership_passive + ' . $leadership_add,
			'bonus_leadership_passive_now = bonus_leadership_passive_now + ' . $leadership_add,
			'bonus_leadership_passive_last = ' . $db->quote($leadership),
			'income_today = income_today + ' . $leadership_add
		],
		['user_id = ' . $db->quote($user_id)]
	);
}

/**
 * Update user details with the leadership passive bonus.
 * This function updates the user's balance or payout transfer based on the leadership passive bonus.
 *
 * @param float $bonus The bonus to add.
 * @param int $user_id The user ID.
 *
 * @since version
 */
function update_user($bonus, $user_id)
{
	$field_user = ['bonus_leadership_passive = bonus_leadership_passive + ' . $bonus];

	if (settings('ancillaries')->withdrawal_mode === 'standard') {
		$field_user[] = 'balance = balance + ' . $bonus;
	} else {
		$field_user[] = 'payout_transfer = payout_transfer + ' . $bonus;
	}

	update(
		'network_users',
		$field_user,
		['id = ' . db()->quote($user_id)]
	);
}

/**
 * Log leadership passive bonus activity.
 * This function logs the activity when a user earns a leadership passive bonus.
 *
 * @param object $user The user object.
 * @param float $bonus The bonus amount.
 *
 * @since version
 */
function log_activity($user, $bonus)
{
	$db = db();

	insert(
		'network_activity',
		[
			'user_id',
			'sponsor_id',
			'activity',
			'activity_date'
		],
		[
			$db->quote($user->id),
			$db->quote($user->sponsor_id),
			$db->quote(
				'<b>' . settings('plans')->leadership_passive_name .
				' Bonus: </b> <a href="' . sef(44) . qs() . 'uid=' . $user->id . '">' .
				$user->username . '</a> has earned ' . number_format($bonus, 2) .
				' ' . settings('ancillaries')->currency
			),
			($db->quote(time()))
		]
	);
}