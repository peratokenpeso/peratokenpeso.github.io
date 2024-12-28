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
	// Fetch leadership passive settings
	$slp = settings('leadership_passive');

	// Iterate through each user and process their leadership passive bonuses
	foreach (users() as $user) {
		process_user_leadership_passive($user, $slp);
	}
}

/**
 * Process leadership passive bonus for a single user.
 * This function calculates and updates the leadership passive bonus for a given user based on their account type and direct referrals.
 *
 * @param object $user The user object.
 * @param object $slp  Leadership passive settings.
 */
function process_user_leadership_passive($user, $slp)
{
	// Fetch user account type and count of direct referrals
	$account_type = $user->account_type;
	$count_directs = count(user_directs($user->user_id));

	// Fetch leadership passive settings for the user's account type
	$type_level = $slp->{$account_type . '_leadership_passive_level'};
	$required_directs = $slp->{$account_type . '_leadership_passive_sponsored'};
	$max_daily_income = $slp->{$account_type . '_leadership_passive_max_daily_income'};
	$income_max = $slp->{$account_type . '_leadership_passive_maximum'};

	// Fetch user's current leadership passive bonus and income today
	$user_bonus_lp = $user->bonus_leadership_passive;
	$ulp = user_leadership_passive($user->id);
	$income_today = $ulp->income_today;

	// Check if the user qualifies for leadership passive bonus
	if ($type_level > 0 && $count_directs >= $required_directs) {
		// Calculate total leadership passive bonus
		$lp_total = bonus_total($user);
		$lp_add = $lp_total - $ulp->bonus_leadership_passive_last;

		// Apply daily and maximum income limits
		if ($lp_add > 0) {
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
 * Ensure the value is non-negative.
 * This function returns the value if it is non-negative; otherwise, it returns 0.
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
	// Fetch leadership passive settings
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
	// Fetch users for the given level
	$users = get_level_users($level, $user);
	return bonus_leadership_passive($level, $users, [
		'top_up' => false,
		'fast_track' => true,
		'fixed_daily' => false,
		'compound_daily' => false
	]);
}

/**
 * Retrieve direct referrals for a given list of users.
 * This function takes an array of users and returns their direct referrals.
 * It is used to traverse the user network level by level.
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
	// Iterate through each level to fetch users
	for ($i = 1; $i <= $level; $i++) {
		$users = level_directs($users);
	}
	return $users;
}

/**
 * Calculate the total passive income for a user based on selected components.
 * This function allows for dynamic selection of which income components to include.
 *
 * @param object $user The user object.
 * @param array $options An associative array specifying which components to include.
 *                       Example: ['top_up' => true, 'fast_track' => true, 'fixed_daily' => true, 'compound_daily' => true]
 * @return float|int The total passive income.
 *
 * @since version
 */
function calculate_passive_income(
	$user,
	$options = [
		'top_up' => true,
		'fast_track' => true,
		'fixed_daily' => true,
		'compound_daily' => true
	]
) {
	$passive = 0;

	// Add top-up interest if enabled
	if ($options['top_up'] && isset($user->top_up_interest)) {
		$passive += $user->top_up_interest;
	}

	// Add fast-track interest if enabled
	if ($options['fast_track'] && isset($user->fast_track_interest)) {
		$passive += $user->fast_track_interest;
	}

	// Add fixed daily interest if enabled
	if ($options['fixed_daily'] && isset($user->fixed_daily_interest)) {
		$passive += $user->fixed_daily_interest;
	}

	// Add compound daily interest if enabled
	if ($options['compound_daily'] && isset($user->compound_daily_interest)) {
		$passive += $user->compound_daily_interest;
	}

	return $passive;
}

/**
 * Calculate the leadership passive bonus for a specific level and users.
 * This function calculates the bonus based on the user's passive income and share percentage.
 *
 * @param int $level The level.
 * @param array $users The users.
 * @param array $options An associative array specifying which components to include in passive income calculation.
 *                       Example: ['top_up' => true, 'fast_track' => true, 'fixed_daily' => true, 'compound_daily' => true]
 * @return float|int The bonus.
 *
 * @since version
 */
function bonus_leadership_passive(
	$level,
	$users,
	$options = [
		'top_up' => true,
		'fast_track' => true,
		'fixed_daily' => true,
		'compound_daily' => true
	]
) {
	$bonus = 0;

	if (!empty($users)) {
		foreach ($users as $user) {
			// Fetch user account type and leadership passive settings
			$account_type = $user->account_type;
			$slp = settings('leadership_passive');

			// Calculate share and share cut percentages
			$share = $slp->{$account_type . '_leadership_passive_share_' . $level} / 100;
			$share_cut = $slp->{$account_type . '_leadership_passive_share_cut_' . $level} / 100;

			// Calculate total passive income based on selected components
			$passive = calculate_passive_income($user, $options);
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

	// Sum up members for each level
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
	// Fetch user and leadership passive settings
	$user = user($user_id);
	$account_type = $user->account_type;
	$slp = settings('leadership_passive');

	$required_directs = $slp->{$account_type . '_leadership_passive_sponsored'};
	$level = $slp->{$account_type . '_leadership_passive_level'};

	// Determine status (active/inactive)
	$status = count(user_directs($user->id)) >= $required_directs ? '' : ' (inactive)';
	$currency = settings('ancillaries')->currency;

	// Generate HTML table
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

	// Add rows for each level
	for ($i = 1; $i <= $level; $i++) {
		$str .= view_row($i, $user);
	}

	// Add total row
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
	// Fetch members and bonus for the given level
	$members = members_level($level, $user);
	$bonus = leadership_passive_level($level, $user);

	// Fetch share and share cut percentages
	$slp = settings('leadership_passive');
	$share = $slp->{$user->account_type . '_leadership_passive_share_' . $level};
	$share_cut = $slp->{$user->account_type . '_leadership_passive_share_cut_' . $level};
	$percentage = $share * $share_cut / 100;

	// Generate HTML row
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

	// Update balance or payout transfer based on withdrawal mode
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
 * Log leadership passive entry activity.
 * This function logs the activity when a user enters the leadership passive program.
 *
 * @param int $insert_id The user ID.
 * @param string $code_type The code type.
 * @param string $username The username.
 * @param string $sponsor The sponsor username.
 * @param string $date The activity date.
 * @param string $prov The provision type.
 *
 * @since version
 */
function logs_leadership_passive($insert_id, $code_type, $username, $sponsor, $date, $prov)
{
	$db = db();

	$settings_plans = settings('plans');

	$sponsor_id = '';

	// Fetch sponsor ID if available
	$user_sponsor = user_username($sponsor);
	if (!empty($user_sponsor)) {
		$sponsor_id = $user_sponsor[0]->id;
	}

	// Generate activity message
	$activity = '<b>' . ucwords($settings_plans->leadership_passive_name) . ' Entry: </b> <a href="' .
		sef(44) . qs() . 'uid=' . $insert_id . '">' . $username . '</a> has entered into ' .
		ucwords($settings_plans->leadership_passive_name) . ' upon ' .
		ucfirst(settings('entry')->{$code_type . '_package_name'}) . source($prov) . '.';

	// Insert activity into the database
	insert(
		'network_activity',
		[
			'user_id',
			'sponsor_id',
			'activity',
			'activity_date'
		],
		[
			$db->quote($insert_id),
			$db->quote($sponsor_id),
			$db->quote($activity),
			$db->quote($date)
		]
	);
}

/**
 * Determine the source of the leadership passive entry.
 * This function returns the source type based on the provision.
 *
 * @param string $prov The provision type.
 * @return string The source type.
 *
 * @since version
 */
function source($prov): string
{
	$source = ' Sign Up';

	if ($prov === 'activate') {
		$source = ' Activation';
	} elseif ($prov === 'upgrade') {
		$source = ' Upgrade';
	}

	return $source;
}

/**
 * Fetch user details by username.
 * This function retrieves user details based on the username.
 *
 * @param string $username The username.
 * @return array The user details.
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

	// Generate activity message
	$activity = '<b>' . settings('plans')->leadership_passive_name .
		' Bonus: </b> <a href="' . sef(44) . qs() . 'uid=' . $user->id . '">' .
		$user->username . '</a> has earned ' . number_format($bonus, 2) .
		' ' . settings('ancillaries')->currency;

	// Insert activity into the database
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
			$db->quote($activity),
			($db->quote(time()))
		]
	);
}