<?php

namespace BPL\Ajax\Mods\Binary\Genealogy;

use Exception;

use function BPL\Mods\Local\Database\Query\fetch;
use function BPL\Mods\Local\Database\Query\fetch_all;
use function BPL\Mods\Local\Helpers\settings;

// Input validation with meaningful defaults
$id_user = filter_input(INPUT_POST, 'id_user', FILTER_VALIDATE_INT) ?: 0;
$plan = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

try {
	echo generateNetworkTree($id_user, $plan);
} catch (Exception $e) {
	http_response_code(400);
	echo json_encode(['error' => $e->getMessage()]);
}

function generateNetworkTree(int $id_user, string $plan): string
{
	if (!$id_user || !$plan) {
		throw new Exception('Invalid user ID or plan provided');
	}

	$head = userBinary($id_user);

	if (!$head) {
		throw new Exception('User not found');
	}

	return json_encode(buildTreeData($head, $plan));
}

function buildTreeData(object $user, string $plan): array
{
	// Step 1: Build the parent node
	$tree = [
		'username' => $user->username,
		'details' => buildUserDetails($user, $plan)
	];

	// Step 2: Get and process direct children
	$children = getBinaryDownlines($user->id);

	if (!empty($children)) {
		$tree['children'] = array_map(
			function ($child) use ($plan) {
				$childNode = [
					'username' => $child->username,
					'details' => buildUserDetails($child, $plan)
				];

				// Get and process grandchildren
				$grandchildren = getBinaryDownlines($child->id);

				if (!empty($grandchildren)) {
					$childNode['children'] = buildGrandchildrenNodes($grandchildren, $plan);
				}

				return $childNode;
			},
			$children
		);
	}

	return $tree;
}

/**
 * Gets direct descendants (children) for a given user ID
 * 
 * @param int $userId Parent user ID
 * @return array Array of user objects
 */
function getBinaryDownlines(int $userId): array
{
	return fetch_all(
		'SELECT * ' .
		'FROM network_users u ' .
		'INNER JOIN network_binary b ' .
		'ON u.id = b.user_id ' .
		'WHERE u.block = :block ' .
		'AND b.upline_id = :upline_id',
		[
			'upline_id' => $userId,
			'block' => 0
		]
	);
}

/**
 * Builds nodes for grandchildren level
 * 
 * @param array $grandchildren Array of grandchild user objects
 * @param string $plan Plan type
 * @return array Processed grandchildren nodes
 */
function buildGrandchildrenNodes(array $grandchildren, string $plan): array
{
	return array_map(
		function ($grandchild) use ($plan) {
			return [
				'username' => $grandchild->username,
				'details' => buildUserDetails($grandchild, $plan)
			];
		},
		$grandchildren
	);
}

function buildUserDetails(object $user, string $plan): array
{
	$balance = number_format($user->payout_transfer, 2);

	if (settings('ancillaries')->withdrawal_mode === 'standard') {
		$balance = number_format($user->balance, 2);
	}

	$details = [
		'id' => $user->id,
		'account' => settings('entry')->{$user->account_type . '_package_name'},
		'balance' => $balance,
		'income_cycle' => number_format($user->income_cycle, 2),
		'status' => ucfirst($user->status)
	];

	return $details;
}

function userBinary($id_user)
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

/**
 * Debug function for development
 */
function debug($data, $label = '')
{
	echo "\n/* Debug $label */\n";
	print_r($data);
	echo "\n/* End Debug $label */\n";
}