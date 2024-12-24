<?php

namespace BPL\Ajax\Mods\Binary\Genealogy;

use function BPL\Mods\Local\Database\Query\fetch;
use function BPL\Mods\Local\Database\Query\fetch_all;

use function BPL\Mods\Local\Helpers\settings;

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
	$head = user_binary($id_user);

	echo '{';

	details($head, $plan);

	if (!empty(get_child_binary($id_user)))
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
	$children = get_child_binary($parent);

	if (!empty($children))
	{
		foreach ($children as $child)
		{
			echo array_search($child, $children, true) > 0 ? ', {' : '{';

			details($child, $plan);

			if (!empty(get_child_binary($child->user_id)))
			{
				echo ', "children": [';
				make_json($child->user_id, $plan);
				echo ']';
			}

			echo '}';
		}
	}
}

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
 * @param $child
 * @param $plan
 *
 *
 * @since version
 */
function details($child, $plan): void
{
	echo '"id": "' . $child->user_id . '", ';
	echo '"username": "' . $child->username . '", ';
	echo '"account": "' . settings('entry')->{$child->account_type . '_package_name'} .
		(!empty(user_cd($child->user_id)) ? '_CD' : '') . '", ';

	if ($plan === 'binary_pair')
	{
		switch ($child->status)
		{
			case 'active':
				$caption = 'y';
				break;
			case 'reactivated':
				$caption = 'x';
				break;
			case 'graduate':
				$caption = 'z';
				break;
			default:
				$caption = 'n';
				break;
		}

		echo '"caption": "' . ucfirst($caption) . '", ';
		echo '"income_cycle": "' . number_format($child->income_cycle, 2) . '", ';
	}

	echo '"balance": "' . number_format($child->balance, 2) . '"';
}

/**
 * @param $id
 *
 * @return array
 *
 * @since version
 */
function get_child_binary($id): array
{
	return fetch_all(
		'SELECT * ' .
		'FROM network_users u ' .
		'INNER JOIN network_binary b ' .
		'ON u.id = b.user_id ' .
		'WHERE u.block = :block ' .
		'AND b.upline_id = :upline_id',
		[
			'upline_id' => $id,
			'block'     => 0
		]
	);
}

/**
 * @param $id_user
 *
 * @return mixed
 *
 * @since version
 */
function user_binary($id_user)
{
	return fetch(
		'SELECT * ' .
		'FROM network_users u ' .
		'INNER JOIN network_binary b ' .
		'ON u.id = b.user_id ' .
		'WHERE u.id = :user_id',
		['user_id' => $id_user]
	);
}