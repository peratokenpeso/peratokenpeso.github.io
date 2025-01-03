<?php

namespace Cron\Fixed_Daily;

require_once 'Cron_Db_Info.php';
require_once 'Cron_Db_Connect.php';
require_once 'cron_query_local.php';
require_once 'cron_leadership_passive.php';
require_once 'cron_fixed_daily_leadership.php';

use Exception;
use DateTime;
use DateTimeZone;

use Cron\Db\Connect\Cron_Db_Connect as DB_Cron;

use function Cron\Database\Query\fetch_all;
use function Cron\Database\Query\fetch;
use function Cron\Database\Query\crud;

use function Cron\Leadership\Fixed_Daily\main as leadership_fixed_daily;

/**
 * Main function to process fixed daily income for all users.
 * This function iterates through all users and processes their fixed daily income.
 * It calculates the maturity, required directs, and actual directs for each user.
 * If the user's account type is 'fixed_deposit', it calculates the fixed deposit income.
 * If the user's account type is 'savings', it calculates the savings account income.
 * 
 * @param mixed $leadership
 * @return void
 * 
 * @since version
 */
function main($leadership = false)
{
	// Connect to the database
	$dbh = DB_Cron::connect();

	// Fetch investment settings
	$si = settings('investment');

	// Fetch all users with fixed daily income
	$fdu = fixed_daily_users();

	if (!empty($fdu)) {
		try {
			// Begin a database transaction
			$dbh->beginTransaction();

			// Process each user's fixed daily income
			foreach ($fdu as $fd) {
				$account_type = $fd->account_type;

				// Fetch maturity, required directs, and actual directs for the user
				$maturity = $si->{$account_type . '_fixed_daily_maturity'};
				$required_directs = $si->{$account_type . '_fixed_daily_required_directs'};
				$actual_directs = directs_valid($fd->user_id);

				// Fetch principal, interest, and donation rate for the user
				$principal = $si->{$fd->account_type . '_fixed_daily_principal'};
				$interest = $si->{$fd->account_type . '_fixed_daily_interest'} / 100;
				$rate_donation = $si->{$fd->account_type . '_fixed_daily_donation'} / 100;

				// Check if the user qualifies for fixed daily income
				if (
					(($required_directs && $actual_directs >= $required_directs) || !$required_directs)
					&& !$fd->processing
					&& $principal > 0
					&& $fd->day < $maturity
				) {
					// Calculate daily raw income, value after donation, and donation amount
					$daily_raw = $interest * $principal;
					$value_now = $daily_raw * (1 - $rate_donation);
					$donation_new = $daily_raw * $rate_donation;

					// Update the user's fixed daily income
					update_fixed_daily($fd, $value_now, $donation_new);
				}
			}

			// Process mature accounts
			mature();

			if ($leadership) {
				leadership_fixed_daily();
			}

			// Commit the transaction
			$dbh->commit();
		} catch (Exception $e) {
			// Rollback the transaction in case of an error
			try {
				$dbh->rollback();
			} catch (Exception $e2) {
				echo $e2->getMessage();
			}
		}
	}
}

/**
 * Fetch settings for a specific type.
 *
 * @param string $type The type of settings to fetch.
 * @return mixed The settings object.
 *
 * @since version
 */
function settings($type)
{
	return fetch(
		'SELECT * ' .
		'FROM network_settings_' . $type
	);
}

/**
 * Fetch all users with fixed daily income.
 *
 * @return array|false An array of users with fixed daily income, or false if none found.
 *
 * @since version
 */
function fixed_daily_users()
{
	return fetch_all(
		'SELECT * ' .
		'FROM network_fixed_daily d ' .
		'INNER JOIN network_users u ' .
		'ON d.user_id = u.id'
	);
}

/**
 * Update the fixed daily income for a user.
 *
 * @param object $user The user object.
 * @param float $value_now The current value to add.
 * @param float $donation_new The new donation to add.
 *
 * @since version
 */
function update_fixed_daily($user, $value_now, $donation_new)
{
	// Fetch entry and freeze settings
	$se = settings('entry');
	$sf = settings('freeze');

	$user_id = $user->user_id;
	$account_type = $user->account_type;

	$income_cycle_global = $user->income_cycle_global;

	// Calculate freeze limit based on entry and freeze percentage
	$entry = $se->{$account_type . '_entry'};
	$factor = $sf->{$account_type . '_percentage'} / 100;
	$freeze_limit = $entry * $factor;

	$status = $user->status_global;

	// Get the current time
	$time = new DateTime('now');
	$time->setTimezone(new DateTimeZone('Asia/Manila'));
	$now = $time->format('U');

	// Check if the user's income cycle has reached the freeze limit
	if ($income_cycle_global >= $freeze_limit) {
		if ($status === 'active') {
			// Update flushout global and mark the user as inactive
			crud(
				'UPDATE network_fixed_daily ' .
				' SET flushout_global = :flushout_global ' .
				' WHERE fixed_daily_id = :fixed_daily_id',
				[
					'flushout_global' => ($user->flushout_global + $value_now),
					'fixed_daily_id' => $user->fixed_daily_id
				]
			);

			crud(
				'UPDATE network_users ' .
				'SET status_global = :status_global, ' .
				'income_flushout = :income_flushout ' .
				'WHERE id = :id',
				[
					'status_global' => 'inactive',
					'income_flushout' => ($user->income_flushout + $value_now),
					'id' => $user->user_id
				]
			);
		}
	} else {
		$diff = $freeze_limit - $income_cycle_global;

		if ($diff < $value_now) {
			$flushout_global = $value_now - $diff;

			if ($status === 'active') {
				// Update fixed daily and user details for partial freeze
				crud(
					'UPDATE network_fixed_daily ' .
					' SET day = :day ' .
					', value_last = :value_last ' .
					', flushout_global = :flushout_global ' .
					', time_last = :time_last ' .
					', date_last_cron = :date_last_cron ' .
					' WHERE fixed_daily_id = :fixed_daily_id',
					[
						'day' => ($user->day + 1),
						'value_last' => ($user->value_last + $diff),
						'flushout_global' => ($user->flushout_global + $flushout_global),
						'time_last' => $now,
						'fixed_daily_id' => $user->fixed_daily_id,
						'date_last_cron' => time()
					]
				);

				crud(
					'UPDATE network_users ' .
					'SET status_global = :status_global, ' .
					'income_flushout = :income_flushout, ' .
					'income_cycle_global = :income_cycle_global ' .
					'WHERE id = :id',
					[
						'status_global' => 'inactive',
						'income_cycle_global' => ($user->income_cycle_global + $diff),
						'income_flushout' => ($user->income_flushout + $flushout_global),
						'id' => $user->user_id
					]
				);

				// Update the user's income details
				update_user($user, $diff, $donation_new);
			}
		} else {
			// Update fixed daily and user details for normal income
			crud(
				'UPDATE network_fixed_daily ' .
				' SET day = :day ' .
				', value_last = :value_last ' .
				', time_last = :time_last ' .
				', date_last_cron = :date_last_cron ' .
				' WHERE fixed_daily_id = :fixed_daily_id',
				[
					'day' => ($user->day + 1),
					'value_last' => ($user->value_last + $value_now),
					'time_last' => $now,
					'fixed_daily_id' => $user->fixed_daily_id,
					'date_last_cron' => time()
				]
			);

			crud(
				'UPDATE network_users ' .
				'SET income_cycle_global = :income_cycle_global ' .
				'WHERE id = :id',
				[
					'income_cycle_global' => ($user->income_cycle_global + $value_now),
					'id' => $user->user_id
				]
			);

			// Update the user's income details
			update_user($user, $value_now, $donation_new);
		}
	}
}

/**
 * Calculate the total fixed daily income for a user based on selected components.
 *
 * @param object $user The user object.
 * @param array $options An associative array specifying which components to include.
 *                       Example: ['fixed_daily_interest' => true, 'donation' => true]
 * @return float|int The total fixed daily income.
 *
 * @since version
 */
function calculate_fixed_daily_income(
	$user,
	$options = [
		'fixed_daily_interest' => true,
		'donation' => true
	]
) {
	$income = 0;

	// Add fixed daily interest if enabled
	if ($options['fixed_daily_interest'] && isset($user->fixed_daily_interest)) {
		$income += $user->fixed_daily_interest;
	}

	// Add donation if enabled
	if ($options['donation'] && isset($user->donation)) {
		$income += $user->donation;
	}

	return $income;
}

/**
 * Update user details with the fixed daily income.
 *
 * @param object $user The user object.
 * @param float $value_now The current value to add.
 * @param float $donation_new The new donation to add.
 * @param array $options An associative array specifying which components to include.
 *                       Example: ['fixed_daily_interest' => true, 'donation' => true]
 *
 * @since version
 */
function update_user(
	$user,
	$value_now,
	$donation_new,
	$options = [
		'fixed_daily_interest' => true,
		'donation' => true
	]
) {
	// Calculate total fixed daily income based on selected components
	$income = calculate_fixed_daily_income($user, $options);

	// Update the user's donation, fixed daily interest, and balance
	crud(
		'UPDATE network_users ' .
		'SET donation = :donation, ' .
		'fixed_daily_interest = :fixed_daily_interest, ' .
		'fixed_daily_balance = :fixed_daily_balance ' .
		'WHERE id = :id',
		[
			'donation' => ($user->donation + ($options['donation'] ? $donation_new : 0)),
			'fixed_daily_interest' => ($user->fixed_daily_interest + ($options['fixed_daily_interest'] ? $value_now : 0)),
			'fixed_daily_balance' => ($user->fixed_daily_balance + $income),
			'id' => $user->user_id
		]
	);
}

/**
 * Fetch all fixed daily income records.
 *
 * @return array|false An array of fixed daily income records, or false if none found.
 *
 * @since version
 */
function fixed_daily()
{
	return fetch_all(
		'SELECT * ' .
		'FROM network_fixed_daily'
	);
}

/**
 * Process mature fixed daily income accounts.
 *
 * @since version
 */
function mature()
{
	$dbh = DB_Cron::connect();

	// Fetch investment settings
	$si = settings('investment');

	// Fetch all users with fixed daily income
	$results = fixed_daily_users();

	if (!empty($results)) {
		foreach ($results as $result) {
			$maturity = $si->{$result->account_type . '_fixed_daily_maturity'};

			// Check if the account has matured and hasn't been marked as mature yet
			if ($result->day === $maturity && (int) $result->time_mature === 0) {
				try {
					// Begin a database transaction
					$dbh->beginTransaction();

					// Mark the account as mature
					update_fixed_daily_time_mature($result);

					// Commit the transaction
					$dbh->commit();
				} catch (Exception $e) {
					// Rollback the transaction in case of an error
					try {
						$dbh->rollback();
					} catch (Exception $e2) {
					}
				}
			}
		}
	}
}

/**
 * Mark a fixed daily income account as mature.
 *
 * @param object $result The fixed daily income record.
 *
 * @since version
 */
function update_fixed_daily_time_mature($result)
{
	// Get the current time
	$time = new DateTime('now');
	$time->setTimezone(new DateTimeZone('Asia/Manila'));
	$now = $time->format('U');

	// Update the time_mature field for the account
	crud(
		'UPDATE network_fixed_daily ' .
		'SET time_mature = :time_mature ' .
		'WHERE fixed_daily_id = :fixed_daily_id',
		[
			'time_mature' => $now,
			'fixed_daily_id' => $result->fixed_daily_id
		]
	);
}

/**
 * Fetch valid direct referrals for a user.
 *
 * @param int $user_id The user ID.
 * @return array|false An array of valid direct referrals, or false if none found.
 *
 * @since version
 */
function directs_valid($user_id)
{
	return fetch_all(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE account_type <> :account_type ' .
		' AND sponsor_id = :sponsor_id',
		[
			'account_type' => 'starter',
			'sponsor_id' => $user_id
		]
	);
}