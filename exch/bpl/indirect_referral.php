<?php

namespace BPL\Indirect_Referral;

require_once 'bpl/mods/query.php';
require_once 'bpl/mods/cd_filter.php';
require_once 'bpl/mods/helpers.php';

use function BPL\Mods\Database\Query\update;
use function BPL\Mods\Database\Query\insert;
use function BPL\Mods\Commission_Deduct\Filter\main as cd_filter;
use function BPL\Mods\Url_SEF\sef;
use function BPL\Mods\Url_SEF\qs;
use function BPL\Mods\Helpers\db;
use function BPL\Mods\Helpers\settings;

/**
 * Main function to process indirect referral bonuses for all users.
 * This function iterates through all users and processes their indirect referral bonuses.
 *
 * @since version
 */
function main()
{
	// Fetch indirect referral settings
	$settings_ir = settings('indirect_referral');

	// Fetch all users
	$users = users();

	// Process indirect referral bonus for each user
	foreach ($users as $user) {
		process_user_indirect_referral_bonus($user, $settings_ir);
	}
}

/**
 * Process indirect referral bonus for a single user.
 * This function calculates and updates the indirect referral bonus for a given user.
 *
 * @param object $user The user object.
 * @param object $settings_ir Indirect referral settings.
 *
 * @since version
 */
function process_user_indirect_referral_bonus($user, $settings_ir)
{
	$account_type = $user->account_type;
	$user_id = $user->id;
	$user_bonus_ir = $user->bonus_indirect_referral;

	// Fetch direct referrals for the user
	$sponsored = user_direct($user_id);

	// Fetch indirect referral level, required directs, income limits, and maximum income from settings
	$type_level = $settings_ir->{$account_type . '_indirect_referral_level'};
	$type_directs = $settings_ir->{$account_type . '_indirect_referral_sponsored'};
	$income_limit_cycle = $settings_ir->{$account_type . '_indirect_referral_max_daily_income'};
	$income_max = $settings_ir->{$account_type . '_indirect_referral_maximum'};

	// Fetch user's indirect referral details
	$user_ir = user_indirect($user_id);
	$income_today = $user_ir->income_today;

	// Check if the user qualifies for indirect referral bonus
	if ($type_level && count($sponsored) >= $type_directs) {
		// Calculate total indirect referral bonus
		$ir_total = calculate_total_indirect_referral_bonus($user_id, $type_level);
		$ir_add = $ir_total - $user_ir->bonus_indirect_last;

		if ($ir_add > 0) {
			// Apply daily and maximum income limits
			if ($income_limit_cycle > 0 && ($income_today + $ir_add) >= $income_limit_cycle) {
				$ir_add = non_zero($income_limit_cycle - $income_today);
			}

			if ($income_max > 0 && ($user_bonus_ir + $ir_add) >= $income_max) {
				$ir_add = non_zero($income_max - $user_bonus_ir);
			}

			// Update indirect referral and user records
			update_bonus_ir($ir_total, $ir_add, $user);
		}
	}
}

/**
 * Calculate the total indirect referral bonus for a user.
 * This function calculates the total bonus based on the user's indirect referrals across all levels.
 *
 * @param int $user_id The user ID.
 * @param int $level The maximum indirect referral level.
 * @return float The total indirect referral bonus.
 *
 * @since version
 */
function calculate_total_indirect_referral_bonus($user_id, $level): float
{
	$total_bonus = 0;

	// Iterate through each level
	for ($current_level = 1; $current_level <= $level; $current_level++) {
		// Fetch users for the current level
		$level_users = get_level_users($user_id, $current_level);

		// Calculate bonus for the current level
		$total_bonus += calculate_level_bonus($level_users, $current_level);
	}

	return $total_bonus;
}

/**
 * Fetch users for a specific level.
 * This function retrieves users for a given level based on the user's direct and indirect referrals.
 *
 * @param int $user_id The user ID.
 * @param int $level The level.
 * @return array The list of users for the level.
 *
 * @since version
 */
function get_level_users($user_id, $level): array
{
	$users = [$user_id];

	// Iterate through each level to fetch indirect referrals
	for ($i = 1; $i < $level; $i++) {
		$new_users = [];

		foreach ($users as $user) {
			$directs = user_direct($user);
			$new_users = array_merge($new_users, $directs);
		}

		$users = $new_users;
	}

	return $users;
}

/**
 * Calculate the indirect referral bonus for a specific level and users.
 * This function calculates the bonus based on the user's account type and share percentage.
 *
 * @param array $users The list of users for the level.
 * @param int $level The level.
 * @return float The indirect referral bonus.
 *
 * @since version
 */
function calculate_level_bonus($users, $level): float
{
	$bonus = 0;

	$settings_ir = settings('indirect_referral');

	foreach ($users as $user) {
		$account_type = $user->account_type;

		// Fetch share for the level
		$indirect_share = $settings_ir->{$account_type . '_indirect_referral_share_' . $level};

		// Calculate the bonus for the user
		$share = is_cd($account_type) ? 0 : $indirect_share;
		$bonus += $share;
	}

	return $bonus;
}

/**
 * Check if the account type includes 'cd'.
 * This function checks if the account type includes the 'cd' suffix.
 *
 * @param string $account_type The account type.
 * @return bool True if the account type includes 'cd', otherwise false.
 *
 * @since version
 */
function is_cd($account_type): bool
{
	$code_type_arr = explode('_', $account_type);
	return in_array('cd', $code_type_arr, true);
}

/**
 * Update indirect referral bonus for a user.
 * This function updates the indirect referral bonus and related fields for a given user.
 *
 * @param float $total The total bonus.
 * @param float $add The bonus to add.
 * @param object $user The user object.
 *
 * @since version
 */
function update_bonus_ir($total, $add, $user)
{
	$db = db();
	$user_id = $user->id;
	$username = $user->username;
	$sponsor_id = $user->sponsor_id;

	// Fetch entry and freeze settings
	$se = settings('entry');
	$sf = settings('freeze');

	$account_type = $user->account_type;
	$income_cycle_global = $user->income_cycle_global;

	// Calculate freeze limit
	$entry = $se->{$account_type . '_entry'};
	$factor = $sf->{$account_type . '_percentage'} / 100;
	$freeze_limit = $entry * $factor;

	$status = $user->status_global;

	// Check if income cycle exceeds freeze limit
	if ($income_cycle_global >= $freeze_limit) {
		if ($status === 'active') {
			// Update user status and flushout income
			update(
				'network_users',
				[
					'status_global = ' . $db->quote('inactive'),
					'income_flushout = income_flushout + ' . $add
				],
				['id = ' . $db->quote($user_id)]
			);
		}

		// Update indirect referral record
		update_network_ir($total, 0, $user_id);
	} else {
		$diff = $freeze_limit - $income_cycle_global;

		if ($diff < $add) {
			$flushout_global = $add - $diff;

			if ($user->status_global === 'active') {
				// Update user bonus and status
				$field_user = ['bonus_indirect_referral = bonus_indirect_referral + ' . $diff];
				$field_user[] = 'status_global = ' . $db->quote('inactive');
				$field_user[] = 'income_cycle_global = income_cycle_global + ' . cd_filter($user_id, $diff);
				$field_user[] = 'income_flushout = income_flushout + ' . $flushout_global;

				// Update balance or payout transfer based on withdrawal mode
				if (settings('ancillaries')->withdrawal_mode === 'standard') {
					$field_user[] = 'balance = balance + ' . cd_filter($user_id, $diff);
				} else {
					$field_user[] = 'payout_transfer = payout_transfer + ' . cd_filter($user_id, $diff);
				}

				update(
					'network_users',
					$field_user,
					['id = ' . $db->quote($user_id)]
				);
			}

			// Update indirect referral record and log activity
			update_network_ir($total, $diff, $user_id);
			log_activity($diff, $user_id, $sponsor_id, $username);
		} else {
			// Update user bonus
			$field_user = ['bonus_indirect_referral = bonus_indirect_referral + ' . $add];
			$field_user[] = 'income_cycle_global = income_cycle_global + ' . cd_filter($user_id, $add);

			// Update balance or payout transfer based on withdrawal mode
			if (settings('ancillaries')->withdrawal_mode === 'standard') {
				$field_user[] = 'balance = balance + ' . cd_filter($user_id, $add);
			} else {
				$field_user[] = 'payout_transfer = payout_transfer + ' . cd_filter($user_id, $add);
			}

			update(
				'network_users',
				$field_user,
				['id = ' . $db->quote($user_id)]
			);

			// Update indirect referral record and log activity
			update_network_ir($total, $add, $user_id);
			log_activity($add, $user_id, $sponsor_id, $username);
		}
	}
}

/**
 * Update indirect referral record for a user.
 * This function updates the indirect referral bonus and related fields in the network_indirect table.
 *
 * @param float $total The total bonus.
 * @param float $add The bonus to add.
 * @param int $user_id The user ID.
 *
 * @since version
 */
function update_network_ir($total, $add, $user_id)
{
	$db = db();

	update(
		'network_indirect',
		[
			'bonus_indirect = bonus_indirect + ' . $add,
			'bonus_indirect_now = bonus_indirect_now + ' . $add,
			'bonus_indirect_last = ' . $db->quote($total),
			'income_today = income_today + ' . $add
		],
		['user_id = ' . $db->quote($user_id)]
	);
}

/**
 * Log indirect referral bonus activity.
 * This function logs the activity when a user earns an indirect referral bonus.
 *
 * @param float $ir The bonus amount.
 * @param int $user_id The user ID.
 * @param int $sponsor_id The sponsor ID.
 * @param string $username The username.
 *
 * @since version
 */
function log_activity($ir, $user_id, $sponsor_id, $username)
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
			$db->quote($user_id),
			$db->quote($sponsor_id),
			$db->quote('<b>' . settings('plans')->indirect_referral_name . ' Bonus: </b> <a href="' .
				sef(44) . qs() . 'uid=' . $user_id . '">' . $username .
				'</a> has earned ' . number_format($ir, 2) . ' ' .
				settings('ancillaries')->currency),
			($db->quote(time()))
		]
	);
}

/**
 * Fetch all users.
 * This function retrieves all users except those with the 'starter' account type.
 *
 * @return array The list of users.
 *
 * @since version
 */
function users(): array
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE account_type <> ' . $db->quote('starter')
	)->loadObjectList();
}

/**
 * Fetch direct referrals for a given sponsor.
 * This function retrieves all direct referrals for a given sponsor ID.
 *
 * @param int $sponsor_id The sponsor ID.
 * @return array The list of direct referrals.
 *
 * @since version
 */
function user_direct($sponsor_id): array
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
 * Fetch indirect referral details for a user.
 * This function retrieves indirect referral details for a given user ID.
 *
 * @param int $user_id The user ID.
 * @return mixed|null The indirect referral details.
 *
 * @since version
 */
function user_indirect($user_id)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_indirect ' .
		'WHERE user_id = ' . $db->quote($user_id)
	)->loadObject();
}

/**
 * Ensure the value is non-negative.
 * This function returns the value if it is non-negative, otherwise returns 0.
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