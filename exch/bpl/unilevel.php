<?php

namespace BPL\Unilevel;

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
 * Main function to process unilevel bonuses for all users.
 * This function iterates through all users and processes their unilevel bonuses.
 *
 * @since version
 */
function main()
{
	$unilevelSettings = settings('unilevel');
	$users = getUsers();

	foreach ($users as $user) {
		processUserUnilevelBonus($user, $unilevelSettings);
	}
}

/**
 * Process unilevel bonus for a single user.
 * This function calculates and updates the unilevel bonus for a given user.
 *
 * @param object $user The user object.
 * @param object $unilevelSettings Unilevel settings.
 *
 * @since version
 */
function processUserUnilevelBonus($user, $unilevelSettings)
{
	$accountType = $user->account_type;
	$userId = $user->id;
	$userUnilevel = $user->unilevel;

	$level = $unilevelSettings->{$accountType . '_unilevel_level'};
	$maintenance = $unilevelSettings->{$accountType . '_unilevel_maintenance'};
	$incomeLimitCycle = $unilevelSettings->{$accountType . '_unilevel_max_daily_income'};
	$incomeMax = $unilevelSettings->{$accountType . '_unilevel_maximum'};

	$userUnilevelDetails = getUserUnilevelDetails($userId);
	$incomeToday = $userUnilevelDetails->income_today;

	if (
		$level && $userUnilevelDetails->period_unilevel >= $maintenance &&
		(($incomeLimitCycle > 0 && $incomeToday < $incomeLimitCycle) || !$incomeLimitCycle) &&
		(($incomeMax > 0 && $userUnilevel < $incomeMax) || !$incomeMax)
	) {
		$totalUnilevelBonus = calculateTotalUnilevelBonus($userId, $level);
		$bonusToAdd = $totalUnilevelBonus - $userUnilevelDetails->bonus_unilevel_last;

		if ($bonusToAdd > 0) {
			if ($incomeLimitCycle > 0 && ($incomeToday + $bonusToAdd) >= $incomeLimitCycle) {
				$bonusToAdd = nonZero($incomeLimitCycle - $incomeToday);
			}

			if ($incomeMax > 0 && ($userUnilevel + $bonusToAdd) >= $incomeMax) {
				$bonusToAdd = nonZero($incomeMax - $userUnilevel);
			}

			updateUnilevelBonus($totalUnilevelBonus, $bonusToAdd, $user);
		}
	}
}

/**
 * Calculate the total unilevel bonus for a user.
 * This function calculates the total bonus based on the user's unilevel structure.
 *
 * @param int $userId The user ID.
 * @param int $level The maximum unilevel level.
 * @return float The total unilevel bonus.
 *
 * @since version
 */
function calculateTotalUnilevelBonus($userId, $level): float
{
	$totalBonus = 0;

	// Iterate through each level
	for ($currentLevel = 1; $currentLevel <= $level; $currentLevel++) {
		$levelUsers = getLevelUsers($userId, $currentLevel);
		$totalBonus += calculateLevelBonus($levelUsers, $currentLevel);
	}

	return $totalBonus;
}

/**
 * Fetch users for a specific level.
 * This function retrieves users for a given level based on the user's direct and indirect referrals.
 *
 * @param int $userId The user ID.
 * @param int $level The level.
 * @return array The list of users for the level.
 *
 * @since version
 */
function getLevelUsers($userId, $level): array
{
	$users = [$userId];

	// Iterate through each level to fetch indirect referrals
	for ($i = 1; $i < $level; $i++) {
		$newUsers = [];

		foreach ($users as $user) {
			$directs = getDirectReferrals($user);
			$newUsers = array_merge($newUsers, $directs);
		}

		$users = $newUsers;
	}

	return $users;
}

/**
 * Calculate the unilevel bonus for a specific level and users.
 * This function calculates the bonus based on the user's account type and share percentage.
 *
 * @param array $users The list of users for the level.
 * @param int $level The level.
 * @return float The unilevel bonus.
 *
 * @since version
 */
function calculateLevelBonus($users, $level): float
{
	$bonus = 0;
	$unilevelSettings = settings('unilevel');

	foreach ($users as $user) {
		$accountType = $user->account_type;

		$memberShare = $unilevelSettings->{$accountType . '_unilevel_share_' . $level};
		$memberShareCut = $unilevelSettings->{$accountType . '_unilevel_share_cut_' . $level};
		$memberCut = $memberShare * $memberShareCut / 100 / 100;

		$cut = isCdAccountType($accountType) ? 0 : $memberCut;
		$bonus += $cut * $user->unilevel_points;
	}

	return $bonus;
}

/**
 * Update unilevel bonus for a user.
 * This function updates the unilevel bonus and related fields for a given user.
 *
 * @param float $total The total bonus.
 * @param float $add The bonus to add.
 * @param object $user The user object.
 *
 * @since version
 */
function updateUnilevelBonus($total, $add, $user)
{
	$db = db();
	$userId = $user->id;
	$username = $user->username;
	$sponsorId = $user->sponsor_id;

	$entrySettings = settings('entry');
	$freezeSettings = settings('freeze');

	$accountType = $user->account_type;
	$incomeCycleGlobal = $user->income_cycle_global;

	$entry = $entrySettings->{$accountType . '_entry'};
	$factor = $freezeSettings->{$accountType . '_percentage'} / 100;
	$freezeLimit = $entry * $factor;

	$status = $user->status_global;

	if ($incomeCycleGlobal >= $freezeLimit) {
		if ($status === 'active') {
			update(
				'network_users',
				[
					'status_global = ' . $db->quote('inactive'),
					'income_flushout = income_flushout + ' . $add
				],
				['id = ' . $db->quote($userId)]
			);
		}

		updateNetworkUnilevel($total, 0, $userId);
	} else {
		$diff = $freezeLimit - $incomeCycleGlobal;

		if ($diff < $add) {
			$flushoutGlobal = $add - $diff;

			if ($user->status_global === 'active') {
				$fieldUser = ['unilevel = unilevel + ' . $diff];
				$fieldUser[] = 'status_global = ' . $db->quote('inactive');
				$fieldUser[] = 'income_cycle_global = income_cycle_global + ' . cd_filter($userId, $diff);
				$fieldUser[] = 'income_flushout = income_flushout + ' . $flushoutGlobal;

				if (settings('ancillaries')->withdrawal_mode === 'standard') {
					$fieldUser[] = 'balance = balance + ' . cd_filter($userId, $diff);
				} else {
					$fieldUser[] = 'payout_transfer = payout_transfer + ' . cd_filter($userId, $diff);
				}

				update(
					'network_users',
					$field_user,
					['id = ' . $db->quote($userId)]
				);
			}

			updateNetworkUnilevel($total, $diff, $userId);
			logActivity($diff, $userId, $sponsorId, $username);
		} else {
			$fieldUser = ['unilevel = unilevel + ' . $add];
			$fieldUser[] = 'income_cycle_global = income_cycle_global + ' . cd_filter($userId, $add);

			if (settings('ancillaries')->withdrawal_mode === 'standard') {
				$fieldUser[] = 'balance = balance + ' . cd_filter($userId, $add);
			} else {
				$fieldUser[] = 'payout_transfer = payout_transfer + ' . cd_filter($userId, $add);
			}

			update(
				'network_users',
				$fieldUser,
				['id = ' . $db->quote($userId)]
			);

			updateNetworkUnilevel($total, $add, $userId);
			logActivity($add, $userId, $sponsorId, $username);
		}
	}
}

/**
 * Update unilevel record for a user.
 * This function updates the unilevel bonus and related fields in the network_unilevel table.
 *
 * @param float $total The total bonus.
 * @param float $add The bonus to add.
 * @param int $userId The user ID.
 *
 * @since version
 */
function updateNetworkUnilevel($total, $add, $userId)
{
	$db = db();

	update(
		'network_unilevel',
		[
			'bonus_unilevel = bonus_unilevel + ' . $add,
			'bonus_unilevel_now = bonus_unilevel_now + ' . $add,
			'bonus_unilevel_last = ' . $db->quote($total),
			'income_today = income_today + ' . $add
		],
		['user_id = ' . $db->quote($userId)]
	);
}

/**
 * Log unilevel bonus activity.
 * This function logs the activity when a user earns a unilevel bonus.
 *
 * @param float $bonus The bonus amount.
 * @param int $userId The user ID.
 * @param int $sponsorId The sponsor ID.
 * @param string $username The username.
 *
 * @since version
 */
function logActivity($bonus, $userId, $sponsorId, $username)
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
			$db->quote($userId),
			$db->quote($sponsorId),
			$db->quote('<b>' . settings('plans')->unilevel_name . ' Bonus: </b> <a href="' .
				sef(44) . qs() . 'uid=' . $userId . '">' . $username .
				'</a> has earned ' . number_format($bonus, 2) . ' ' .
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
function getUsers(): array
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE account_type <> ' . $db->quote('starter')
	)->loadObjectList();
}

/**
 * Fetch unilevel details for a user.
 * This function retrieves unilevel details for a given user ID.
 *
 * @param int $userId The user ID.
 * @return mixed|null The unilevel details.
 *
 * @since version
 */
function getUserUnilevelDetails($userId)
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_unilevel ' .
		'WHERE user_id = ' . $db->quote($userId)
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
function nonZero($value)
{
	return $value < 0 ? 0 : $value;
}

/**
 * Fetch direct referrals for a given sponsor.
 * This function retrieves all direct referrals for a given sponsor ID.
 *
 * @param int $sponsorId The sponsor ID.
 * @return array The list of direct referrals.
 *
 * @since version
 */
function getDirectReferrals($sponsorId): array
{
	$db = db();

	return $db->setQuery(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE account_type <> ' . $db->quote('starter') .
		' AND sponsor_id = ' . $db->quote($sponsorId)
	)->loadObjectList();
}

/**
 * Check if the account type includes 'cd'.
 * This function checks if the account type includes the 'cd' suffix.
 *
 * @param string $accountType The account type.
 * @return bool True if the account type includes 'cd', otherwise false.
 *
 * @since version
 */
function isCdAccountType($accountType): bool
{
	$codeTypeArr = explode('_', $accountType);
	return in_array('cd', $codeTypeArr, true);
}