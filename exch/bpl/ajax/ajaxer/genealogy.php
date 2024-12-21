<?php

namespace BPL\Ajax\Ajaxer\Genealogy;

/**
 * @param           $type
 * @param           $user_id
 * @param   string  $plan
 *
 * @return string
 *
 * @since version
 */
function main($type, $user_id, string $plan = 'binary_pair'): string
{
	$str = '<link rel=\'stylesheet prefetch\' href=\'https://fonts.googleapis.com/css?family=Roboto\'>';
	$str .= '<style>
			/* Scoped CSS to prevent conflicts with external styles */
			:root {
				--color-primary: steelblue;
				--color-secondary: #999;
				--color-background: #fff;
				--color-tooltip-bg: rgba(0, 0, 0, 0.7);
				--color-tooltip-text: #fff;
			
				--font-size-base: 0.75rem;
				/* 12px */
				--font-family-base: system-ui, -apple-system, sans-serif;
			
				--spacing-sm: 0.3125rem;
				/* 5px */
				--spacing-md: 0.625rem;
				/* 10px */
			
				--border-radius: 0.3125rem;
				/* 5px */
				--stroke-width-large: 3px;
				--stroke-width-small: 2px;
			
				--transition-default: 200ms ease;
			}
			
			#genealogy_' . $type . ' {
				width: 100%;
				height: 100%;
			}
			
			/* Node styles */
			#genealogy_' . $type . ' .node circle {
				fill: var(--color-background);
				stroke: var(--color-primary);
				stroke-width: var(--stroke-width-large);
			}
			
			#genealogy_' . $type . ' .node text {
				font: var(--font-size-base) var(--font-family-base);
			}
			
			/* Connection line styles */
			#genealogy_' . $type . ' .link {
				fill: none;
				stroke: var(--color-secondary);
				stroke-width: var(--stroke-width-small);
			}
			
			/* Tooltip */
			.tooltip {
				position: absolute;
				padding: var(--spacing-sm) var(--spacing-md);
				background-color: var(--color-tooltip-bg);
				color: var(--color-tooltip-text);
				border-radius: var(--border-radius);
				font-size: var(--font-size-base);
				font-family: var(--font-family-base);
				pointer-events: none;
				opacity: 0;
				transition: opacity var(--transition-default);
			}	
		</style>';
	$str .= '<div id="genealogy_' . $type . '"></div>';
	$str .= '<div class="tooltip" id="tooltip"></div>';
	$str .= '<script src="https://d3js.org/d3.v7.min.js"></script>';
	$str .= '<script>' . render($type, $user_id, $plan) . '</script>';

	return $str;
}

/**
 *
 * @return string[]
 *
 * @since version
 */
function plan_attr(): array
{
	return [
		'indirect_referral' => 'bonus_indirect_referral',
		'unilevel' => 'unilevel',
		'binary_pair' => 'income_cycle',
		'leadership_binary' => 'bonus_leadership',
		'leadership_passive' => 'bonus_leadership_passive',
		'matrix' => 'bonus_matrix',
		'power' => 'bonus_power',
		'matrix_table' => 'bonus_share',
		'harvest' => 'bonus_harvest'
	];
}

/**
 * @param           $type
 * @param           $user_id
 * @param   string  $plan
 *
 * @return string
 *
 * @since version
 */
function ajax($type, $user_id, string $plan = 'binary_pair'): string
{
	return 'jQuery.ajax({
			type: "post",
			dataType: "json",
			url: "bpl/ajax/action.php",
			data: {
				"action": "genealogy_' . $type . '",
				"id_user": ' . $user_id . ',
				"plan": "' . $plan . '"
			},
			success: function (data) {
				const treeVis = new TreeVisualization("#genealogy_' . $type . '", data);
			}
		});';
}

/**
 * @param $type
 * @param $user_id
 * @param $plan
 *
 * @return string
 *
 * @since version
 */
function render($type, $user_id, $plan): string
{
	$attr = set_attr($plan);

	$str = '
			/**
			 * Type definition for a node in the tree structure.
			 * Each node can have a name, optional details, and optional child nodes.
			 * @typedef {Object} TreeNode
			 * @property {string} name - Display name of the node
			 * @property {Object} [details] - Optional metadata about the node
			 * @property {string} details.id - Unique identifier
			 * @property {string} details.account - Account information
			 * @property {number} details.balance - Account balance
			 * @property {string} details.plan - Subscription plan type
			 * @property {number} details.bonus_indirect_referral - Bonus amount
			 * @property {TreeNode[]} [children] - Array of child nodes
			*/
		
			/**
			 * Global configuration object for customizing the tree visualization.
			 * Contains settings for margins, node appearance, animations, and link styling.
			 * @constant {Object}
			*/
			const CONFIG = {
				margin: {
					top: 20,     // Top margin in pixels
					right: 90,   // Right margin in pixels
					bottom: 30,  // Bottom margin in pixels
					left: 90     // Left margin in pixels
				},
				nodeRadius: 10,              // Radius of node circles in pixels
				transitionDuration: 200,     // Duration of animations in milliseconds
				linkStyle: {
					strokeWidth: 2,          // Width of connecting lines
					strokeColor: "#999"      // Color of connecting lines
				}
			};
		
			/**
			 * Class representing an interactive tree visualization using D3.js
			 * Features include:
			 * - Collapsible nodes
			 * - Interactive zooming
			 * - Tooltips with node details
			 * - Smooth transitions
			 * - Automatic layout
			*/
			class TreeVisualization {
				/**
				 * Creates a new tree visualization instance
				 * @param {string} containerId - ID of the DOM element to contain the tree
				 * @param {TreeNode} data - Hierarchical data structure for the tree
				*/
				constructor(containerId, data) {
					this.i = 0;  // Counter for generating unique node IDs
					this.container = d3.select(containerId);
		
					// Calculate dimensions based on margins
					this.width = 960 - CONFIG.margin.left - CONFIG.margin.right;
					this.height = 500 - CONFIG.margin.top - CONFIG.margin.bottom;
			
					// Select tooltip container
					this.tooltip = d3.select("#tooltip");
			
					this.initializeSVG();
					this.initializeTree(data);
					this.setupZoom();
				}
		
				/**
				 * Sets up the SVG container and adds basic structural elements
				 * Includes a border and transformation group for zooming
				 * @private
				*/
				initializeSVG() {
				// Create main SVG container with specified dimensions
				this.svg = this.container.append("svg")
					.attr("width", this.width + CONFIG.margin.left + CONFIG.margin.right)
					.attr("height", this.height + CONFIG.margin.top + CONFIG.margin.bottom)
					.style("display", "block")
					.style("margin", "auto");
		
				// Create group for zoom transformations
				this.zoomGroup = this.svg.append("g")
					.attr("transform", `translate(${CONFIG.margin.left},${CONFIG.margin.top})`);
		
				this.addBorder();
			}
		
			/**
			 * Adds a dashed border around the visualization
			 * @private
			*/
			addBorder() {
				this.svg.append("rect")
					.attr("width", this.svg.attr("width"))
					.attr("height", this.svg.attr("height"))
					.attr("fill", "none")
					.attr("stroke", CONFIG.linkStyle.strokeColor)
					.attr("stroke-width", CONFIG.linkStyle.strokeWidth)
					.attr("stroke-dasharray", "5,5");
			}
		
			/**
			 * Initializes zoom behavior with scale limits and event handling
			 * @private
			*/
			setupZoom() {
				const zoom = d3.zoom()
					.scaleExtent([0.5, 2])  // Limit zoom scale between 0.5x and 2x
					.on("zoom", ({ transform }) => {
						this.zoomGroup.attr("transform", transform);
					});
		
				this.svg.call(zoom);
			}
		
			/**
			 * Sets up the initial tree structure and collapses all nodes
			 * @param {TreeNode} data - Root node of the tree
			 * @private
			*/
			initializeTree(data) {
				// Create D3 tree layout
				this.tree = d3.tree().size([this.width, this.height]);
		
				// Create hierarchy from data
				this.root = d3.hierarchy(data, d => d.children);
		
				// Set initial position at center-top
				this.root.x0 = this.width / 2;
				this.root.y0 = 0;
		
				// Collapse all nodes initially
				if (this.root.children) {
					this.root.children.forEach(this.collapse);
				}
		
				this.update(this.root);
			}
		
			/**
			 * Recursively collapses all children of a node
			 * @param {d3.HierarchyNode} node - Node to collapse
			 * @private
			*/
			collapse = (node) => {
				if (node.children) {
					node._children = node.children;  // Store children in _children
					node._children.forEach(this.collapse);
					node.children = null;  // Remove children to collapse
				}
			}
		
			/**
			 * Generates SVG path data for connecting lines between nodes
			 * Uses curved lines with control points
			 * @param {Object} source - Starting point coordinates
			 * @param {Object} target - Ending point coordinates
			 * @returns {string} SVG path data
			 * @private
			*/
			diagonal(source, target) {
				return `M ${source.x} ${source.y}
						C ${source.x} ${(source.y + target.y) / 2},
						${target.x} ${(source.y + target.y) / 2},
						${target.x} ${target.y}`;
			}
		
			/**
			 * Updates the visualization when node states change
			 * Handles node positions, transitions, and link updates
			 * @param {d3.HierarchyNode} source - Node that triggered the update
			 * @public
			*/
			update(source) {
				const treeData = this.tree(this.root);
				const nodes = treeData.descendants();
				const links = treeData.descendants().slice(1);
		
				// Normalize for fixed-depth
				nodes.forEach(node => {
					node.y = node.depth * 120;  // Vertical spacing between levels
				});
		
				this.updateNodes(nodes, source);
				this.updateLinks(links, source);
		
				// Store the old positions for transitions
				nodes.forEach(node => {
					node.x0 = node.x;
					node.y0 = node.y;
				});
			}
		
			/**
			 * Handles the enter/update/exit cycle for nodes
			 * @param {d3.HierarchyNode[]} nodes - Array of tree nodes
			 * @param {d3.HierarchyNode} source - Source node for transitions
			 * @private
			*/
			updateNodes(nodes, source) {
				const node = this.zoomGroup.selectAll("g.node")
					.data(nodes, d => d.id || (d.id = ++this.i));
		
				const nodeEnter = this.createNodes(node, source);
				this.updateExistingNodes(nodeEnter, node);
				this.removeNodes(node, source);
			}
		
			/**
			 * Creates new nodes in the visualization
			 * @param {d3.Selection} node - D3 selection of nodes
			 * @param {d3.HierarchyNode} source - Source node for transitions
			 * @returns {d3.Selection} New node elements
			 * @private
			*/
			createNodes(node, source) {
				const nodeEnter = node.enter().append("g")
					.attr("class", "node")
					.attr("transform", () => `translate(${source.x0},${source.y0})`)
					.on("click", (_event, d) => this.handleClick(d))
					.on("mouseover", (_event, d) => this.handleMouseOver(d))
					.on("mousemove", (event) => this.handleMouseMove(event))
					.on("mouseout", () => this.handleMouseOut());
		
				// Add circles for nodes
				nodeEnter.append("circle")
					.attr("class", "node")
					.attr("r", 1e-6)
					.style("fill", d => d._children ? "lightsteelblue" : "#fff");
		
				// Add labels for nodes
				nodeEnter.append("text")
					.attr("dy", ".35em")
					.attr("x", d => d.children || d._children ? -13 : 13)
					.attr("text-anchor", d => d.children || d._children ? "end" : "start")
					.text(d => d.data.username);
		
				return nodeEnter;
			}
		
			/**
			 * Updates existing nodes with new positions and states
			 * @param {d3.Selection} nodeEnter - Enter selection of new nodes
			 * @param {d3.Selection} node - Update selection of existing nodes
			 * @private
			*/
			updateExistingNodes(nodeEnter, node) {
				const nodeUpdate = nodeEnter.merge(node);
		
				// Transition nodes to their new positions
				nodeUpdate.transition()
					.duration(CONFIG.transitionDuration)
					.attr("transform", d => `translate(${d.x},${d.y})`);
		
				nodeUpdate.select("circle.node")
					.attr("r", CONFIG.nodeRadius)
					.style("fill", d => d._children ? "lightsteelblue" : "#fff")
					.attr("cursor", "pointer");
			}
		
			/**
			 * Handles the removal of nodes from the visualization
			 * @param {d3.Selection} node - D3 selection of nodes to remove
			 * @param {d3.HierarchyNode} source - Source node for transitions
			 * @private
			*/
			removeNodes(node, source) {
				const nodeExit = node.exit().transition()
					.duration(CONFIG.transitionDuration)
					.attr("transform", () => `translate(${source.x},${source.y})`)
					.remove();
		
				nodeExit.select("circle")
					.attr("r", 1e-6);
		
				nodeExit.select("text")
					.style("fill-opacity", 1e-6);
			}
		
			/**
			 * Updates the connecting lines between nodes
			 * @param {d3.HierarchyNode[]} links - Array of links between nodes
			 * @param {d3.HierarchyNode} source - Source node for transitions
			 * @private
			*/
			updateLinks(links, source) {
				const link = this.zoomGroup.selectAll("path.link")
					.data(links, d => d.id);
		
				// Enter any new links at the parent\'s previous position
				const linkEnter = link.enter().insert("path", "g")
					.attr("class", "link")
					.attr("d", () => {
						const o = { x: source.x0, y: source.y0 };
						return this.diagonal(o, o);
					});
		
				// Transition links to their new position
				linkEnter.merge(link)
					.transition()
					.duration(CONFIG.transitionDuration)
					.attr("d", d => this.diagonal(d, d.parent));
		
				// Remove any exiting links
				link.exit().transition()
					.duration(CONFIG.transitionDuration)
					.attr("d", () => {
						const o = { x: source.x, y: source.y };
						return this.diagonal(o, o);
					})
					.remove();
			}
		
			/**
			 * Handles click events on nodes to toggle expansion/collapse
			 * @param {d3.HierarchyNode} node - Clicked node
			 * @private
			*/
			handleClick(node) {
				if (node.children) {
					node._children = node.children;
					node.children = null;
				} else {
					node.children = node._children;
					node._children = null;
				}
		
				this.update(node);
			}
		
			/**
			 * Shows tooltip with node details on mouseover
			 * @param {d3.HierarchyNode} node - Node being hovered
			 * @private
			*/
			handleMouseOver(node) {
				this.tooltip
					.style("opacity", 1)
					.html(this.generateTooltipContent(node.data.details));
			}
		
			/**
			 * Generates HTML content for the tooltip
			 * @param {Object} details - Node details object
			 * @returns {string} HTML content for tooltip
			 * @private
			*/
			generateTooltipContent(details) {
				const { id, account, balance, plan, ' . $attr . '} = details;
				
				return `
					<div><strong>ID:</strong> ${id}</div>
					<div><strong>Account:</strong> ${account}</div>
					<div><strong>balance:</strong> ${balance}</div>            
					<div><strong>${plan}:</strong> ${' . $attr . '}</div>
				`;
			}
		
			/**
			 * Updates tooltip position on mouse movement
			 * @param {MouseEvent} event - Mouse move event
			 * @private
			*/
			handleMouseMove(event) {
				this.tooltip
					.style("left", `${event.pageX + 10}px`)
					.style("top", `${event.pageY - 20}px`);
			}
		
			/**
			 * Hides tooltip when mouse leaves a node
			 * @private
			*/
			handleMouseOut() {
				this.tooltip.style("opacity", 0);
			}
		}';

	$str .= ajax($type, $user_id, $plan);

	return $str;
}

/**
 * @param $plan
 *
 * @return string
 *
 * @since version
 */
function set_attr($plan): string
{
	$attr = 'status';

	foreach (plan_attr() as $k => $v) {
		if ($k === $plan && $v !== 'status') {
			$attr = $v;
		}
	}

	return $attr;
}