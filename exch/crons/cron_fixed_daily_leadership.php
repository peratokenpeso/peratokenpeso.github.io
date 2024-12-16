<?php

namespace Cron\Leadership\Fixed_Daily;

//require_once 'cron_income_local.php';
require_once 'cron_leadership_passive.php';
require_once 'cron_query_local.php';

use function Cron\Leadership_Passive\leadership_passive_users;
use function Cron\Leadership_Passive\user_directs;
use function Cron\Leadership_Passive\update_leadership_passive;
use function Cron\Leadership_Passive\update_user;

//use function Cron\Income\main as income_global;

use function Cron\Database\Query\fetch;
use function Cron\Database\Query\crud;

/**
 *
 *
 * @since version
 */
function main()
{
	$slp = settings('leadership_passive');

	$users = leadership_passive_users();

	if (!empty($users))
	{
		foreach ($users as $user)
		{
			$user_id      = $user->user_id;
			$account_type = $user->account_type;

//			$sp = settings('plans');
			$se = settings('entry');
			$sf = settings('freeze');

			$income_cycle_global = $user->income_cycle_global;

			$entry  = $se->{$account_type . '_entry'};
			$factor = $sf->{$account_type . '_percentage'} / 100;

			$freeze_limit = $entry * $factor;

			$status = $user->status_global;

			$count_directs = count(user_directs($user_id));

			$type_level       = $slp->{$account_type . '_leadership_passive_level'};
			$required_directs = $slp->{$account_type . '_leadership_passive_sponsored'};
			$max_daily_income = $slp->{$account_type . '_leadership_passive_max_daily_income'};
			$income_max       = $slp->{$account_type . '_leadership_passive_maximum'};

			$user_bonus_lp = $user->u_bonus_leadership_passive;
			$income_today  = $user->income_today;

			if ($type_level > 0 && $count_directs >= $required_directs && $status === 'active'
				/*&& (($max_daily_income > 0 && $income_today < $max_daily_income) || !$max_daily_income)
				&& ($income_max > 0 && $user_bonus_lp < $income_max || !$income_max)*/)
			{
				$lp_total = bonus_total_leadership_fixed_daily($user_id)['bonus'];

				$lp_add = $lp_total - $user->bonus_leadership_passive_last;

				if ($lp_add > 0)
				{
					if ($max_daily_income > 0 && ($income_today + $lp_add) >= $max_daily_income)
					{
						$lp_add = non_zero($max_daily_income - $income_today);
					}

					if ($income_max > 0 && ($user_bonus_lp + $lp_add) >= $income_max)
					{
						$lp_add = non_zero($income_max - $user_bonus_lp);
					}

					if ($income_cycle_global >= $freeze_limit)
					{
//						if ($saf)
//						{
//						if ($status === 'active')
//						{
						crud(
							'UPDATE network_leadership_passive ' .
							' SET flushout_global = :flushout_global ' .
							' WHERE user_id = :user_id',
							[
								'flushout_global' => ($user->flushout_global + $lp_add),
								'user_id'         => $user_id
							]
						);

						crud(
							'UPDATE network_users ' .
							'SET status_global = :status_global, ' .
							'income_flushout = :income_flushout ' .
							'WHERE id = :id',
							[
								'status_global'   => 'inactive',
								'income_flushout' => ($user->income_flushout + $lp_add),
								'id'              => $user_id
							]
						);
//						}

						update_leadership_passive($user, 0, $lp_total);
						update_user($user, 0);
//						}
					}
					else
					{
						$diff = $freeze_limit - $income_cycle_global;

						if ($diff < $lp_add)
						{
							$flushout_global = $lp_add - $diff;

//							if ($saf)
//							{
//							if ($status === 'active')
//							{
							crud(
								'UPDATE network_leadership_passive ' .
								' SET flushout_global = :flushout_global ' .
								' WHERE user_id = :user_id',
								[
									'flushout_global' => ($user->flushout_global + $flushout_global),
									'user_id'         => $user_id
								]
							);

							crud(
								'UPDATE network_users ' .
								'SET status_global = :status_global, ' .
								'income_flushout = :income_flushout, ' .
								'income_cycle_global = :income_cycle_global ' .
								'WHERE id = :id',
								[
									'status_global'       => 'inactive',
									'income_flushout'     => ($user->income_flushout + $lp_add),
									'income_cycle_global' => ($user->income_cycle_global + $diff),
									'id'                  => $user_id
								]
							);
//							}

							update_leadership_passive($user, $diff, $lp_total);
							update_user($user, $diff);
//							}
						}
						else
						{
							crud(
								'UPDATE network_users ' .
								'SET income_cycle_global = :income_cycle_global ' .
								'WHERE id = :id',
								[
									'income_cycle_global' => ($user->income_cycle_global + $lp_add),
									'id'                  => $user_id
								]
							);

							update_leadership_passive($user, $lp_add, $lp_total);
							update_user($user, $lp_add);
						}
					}
				}
			}
		}
	}
}

/**
 * @param $value
 *
 * @return int|mixed
 *
 * @since version
 */
function non_zero($value)
{
	return $value < 0 ? 0 : $value;
}

/**
 * @param $type
 *
 * @return mixed
 *
 * @since version
 */
function settings($type)
{
	return fetch('SELECT * ' .
		'FROM network_settings_' . $type);
}

/**
 * @param $user_id
 *
 * @return mixed
 *
 * @since version
 */
function user($user_id)
{
	return fetch(
		'SELECT * ' .
		'FROM network_users ' .
		'WHERE id = :id',
		['id' => $user_id]
	);
}

/**
 * @param $user_id
 *
 * @return mixed
 *
 * @since version
 */
function user_cd($user_id)
{
	return fetch(
		'SELECT * ' .
		'FROM network_commission_deduct ' .
		'WHERE id = :id',
		['id' => $user_id]
	);
}

/**
 * @param   array  $head
 *
 * @return array[]
 *
 * @since version
 */
function level_leadership_fixed_daily(array $head = []): array
{
	$group   = [];
	$passive = [];

	foreach ($head as $sponsor)
	{
		$directs = user_directs($sponsor);

		if (!empty($directs))
		{
			foreach ($directs as $direct)
			{
				$group[] = $direct->id;

				if (empty(user_cd($direct->id)))
				{
					$passive[] = $direct->fixed_daily_interest;
				}
			}
		}
	}

	return [$group, $passive];
}

/**
 * @param $n
 * @param $level_1
 *
 * @return mixed
 *
 * @since version
 */
function nested($n, $level_1)
{
	$result = [$level_1];

	for ($i_i = 2; $i_i <= $n; $i_i++)
	{
		$result[] = level_leadership_fixed_daily(array_reverse($result)[0][0]);
	}

	return array_reverse($result)[0];
}

/**
 * @param $indirect
 * @param $share
 * @param $share_cut
 *
 * @return float|int
 *
 * @since version
 */
function get_leadership_fixed_daily($indirect, $share, $share_cut)
{
	$leadership = 0;

	if ($indirect)
	{
		foreach ($indirect as $fixed_daily)
		{
			$leadership += $fixed_daily * $share * $share_cut / 100 / 100;
		}
	}

	return $leadership;
}

/**
 * @param $user_id
 *
 * @return array
 *
 * @since version
 */
function bonus_total_leadership_fixed_daily($user_id): array
{
	$level_1 = level_leadership_fixed_daily([$user_id]);

	$account_type = user($user_id)->account_type;

	$settings_leadership_passive = settings('leadership_passive');

	$member = count($level_1[0]);
	$bonus  = get_leadership_fixed_daily(
		$level_1[1],
		$settings_leadership_passive->{$account_type . '_leadership_passive_share_1'},
		$settings_leadership_passive->{$account_type . '_leadership_passive_share_cut_1'}
	);

	$type_level = $settings_leadership_passive->{$account_type . '_leadership_passive_level'};

	for ($i_i = 2; $i_i <= $type_level; $i_i++)
	{
		$member += count(nested($i_i, $level_1)[0]);

		$bonus += get_leadership_fixed_daily(
			nested($i_i, $level_1)[1],
			$settings_leadership_passive->{$account_type . '_leadership_passive_share_' . $i_i},
			$settings_leadership_passive->{$account_type . '_leadership_passive_share_cut_' . $i_i}
		);
	}

	return [
		'member' => $member,
		'bonus'  => $bonus
	];
}