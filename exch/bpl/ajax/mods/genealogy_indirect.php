<?php

namespace BPL\Ajax\Mods\Indirect\Genealogy;

use Exception;

use function BPL\Mods\Local\Database\Query\fetch_all;
use function BPL\Mods\Local\Helpers\settings;
use function BPL\Mods\Local\Helpers\user;

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

	$head = user($id_user);

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
	$children = getDirectDescendants($user->id);

	if (!empty($children)) {
		$tree['children'] = array_map(
			function ($child) use ($plan) {
				$childNode = [
					'username' => $child->username,
					'details' => buildUserDetails($child, $plan)
				];

				// Get and process grandchildren
				$grandchildren = getDirectDescendants($child->id);

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
function getDirectDescendants(int $userId): array
{
	return fetch_all(
		'SELECT * 
        FROM network_users 
        WHERE sponsor_id = :sponsor_id 
        AND block = :block',
		[
			'sponsor_id' => $userId,
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
	$details = [
		'id' => $user->id,
		'account' => settings('entry')->{$user->account_type . '_package_name'},
		'balance' => number_format($user->balance, 2)
	];

	$planAttrs = getPlanAttributes();

	if (isset($planAttrs[$plan])) {
		$planInfo = $planAttrs[$plan];
		$details['plan'] = $planInfo['code'];
		$details[$planInfo['field']] = number_format($user->{$planInfo['field']}, 2);
	}

	return $details;
}

function getPlanAttributes(): array
{
	return [
		'indirect_referral' => ['code' => 'IR', 'field' => 'bonus_indirect_referral'],
		'unilevel' => ['code' => 'UB', 'field' => 'unilevel'],
		'leadership_binary' => ['code' => 'LB', 'field' => 'bonus_leadership'],
		'leadership_passive' => ['code' => 'LP', 'field' => 'bonus_leadership_passive']
	];
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