<?php

namespace BPL\Ajax\Mods\Harvest\Genealogy\Basic;

use function BPL\Mods\Local\Database\Query\fetch;
use function BPL\Mods\Local\Database\Query\fetch_all;

use function BPL\Mods\Local\Helpers\settings;
use function BPL\Mods\Local\Helpers\user;

$id_user = filter_input(INPUT_POST, 'id_user', FILTER_VALIDATE_INT);
$plan    = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_STRING);

main($id_user, $plan);

/**
 * @param $id_user
 * @param $plan
 *
 *
 * @since version
 */
function main($id_user, $plan)
{
	echo '{';

	details_basic(user($id_user), $plan);

	if (!empty(get_child($id_user)))
	{
		echo ', "children": [';
		make_json($id_user, $plan);
		echo ']';
	}

	echo '}';
}

/**
 * @param $parent
 * @param $plan
 *
 *
 * @since version
 */
function make_json($parent, $plan)
{
	$children = get_child($parent);

	if (!empty($children))
	{
		foreach ($children as $child)
		{
			echo array_search($child, $children, true) > 0 ? ', {' : '{';

			details_basic($child, $plan);

			if (!empty(get_child($child->id)))
			{
				echo ', "children": [';
				make_json($child->id, $plan);
				echo ']';
			}

			echo '}';
		}
	}
}

/**
 * @param $child
 *
 * @param $plan
 *
 * @since version
 */
function details_basic($child, $plan): void
{
	echo '"id": "' . $child->id . '", ';
	echo '"username": "' . $child->username . '", ';
	echo '"account": "' . settings('entry')
			->{$child->account_type . '_package_name'} . '", ';

	if ($plan === 'harvest')
	{
		echo '"caption": "BH", ';
		echo '"bonus_harvest": "' .
			number_format(harvest_user($child->id)->bonus_harvest_basic_last, 2) . '", ';
	}

	echo '"balance": "' . number_format($child->balance, 2) . '"';
}

/**
 * @param $user_id
 *
 * @return array|false
 *
 * @since version
 */
function get_child($user_id)
{
	$harvest_id = user_harvest_basic($user_id)->id;

	return fetch_all(
		'SELECT * ' .
		'FROM network_harvest_basic b ' .
		'INNER JOIN network_users u ' .
		'ON b.user_id = u.id ' .
		'WHERE harvest_upline_id = :harvest_upline_id',
		['harvest_upline_id' => $harvest_id]);
}

/**
 * @param           $user_id
 *
 * @return mixed
 *
 * @since version
 */
function user_harvest_basic($user_id)
{
	return fetch(
		'SELECT * ' .
		'FROM network_harvest_basic ' .
		' WHERE user_id = :user_id' .
		' AND has_mature = :has_mature' .
		' AND is_active = :is_active',
		[
			'user_id'    => $user_id,
			'has_mature' => '0',
			'is_active'  => '1'
		]
	);
}

/**
 * @param $user_id
 *
 * @return mixed
 *
 * @since version
 */
function harvest_user($user_id)
{
	return fetch(
		'SELECT * ' .
		'FROM network_harvest ' .
		'WHERE user_id = :user_id',
		['user_id' => $user_id]
	);
}
