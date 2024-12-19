// https://observablehq.com/@bumbeishvili/vertical-collapsible-d3-flextree@192
function _1(md) {
    return (
        md`# Vertical collapsible d3.flextree (d3.v6)`
    )
}

function _2(md) {
    return (
        md`**d3.flextree** can be considered as superset of d3.tree and integrates smoothly with d3.

Its non layered approach opens possibilities for more compact tree layouts, which would not be possible using standard d3-tree lib.
`
    )
}

function _3(md) {
    return (
        md`Bellow you can see simplest possible example of vertical collapsible d3.flextree. Code is based on **d3.v6**`
    )
}

function* _4(d3, createSvg, treeData, flextree) {

    var width = 960,
        height = 500;

    // append the svg object to the body of the page
    const svg = d3.select(createSvg`<svg width=500 height=500></svg>`);
    const g = svg
        .attr("width", width)
        .attr("height", height)
        .append("g")
        .attr('transform', `translate(${width / 2},100)`)


    const duration = 750;
    let i = 0,
        root = d3.hierarchy(treeData, function (d) {
            return d.children;
        });

    // Collapse after second level
    root.children.forEach(collapse);
    root.x0 = 0;
    root.y0 = 0;


    const flexLayout = flextree.flextree();

    update(root);

    // Collapse the node and all it's children
    function collapse(d) {
        if (d.children) {
            d._children = d.children
            d._children.forEach(collapse)
            d.children = null
        }
    }

    function update(source) {

        // Assigns the x and y position for the nodes
        var treeData = flexLayout(root)

        // Compute the new tree layout.
        var nodes = treeData.descendants(),
            links = treeData.descendants().slice(1);

        // ****************** Nodes section ***************************

        // Update the nodes...
        var node = g.selectAll('g.node')
            .data(nodes, d => d.id || (d.id = ++i));

        // Enter any new modes at the parent's previous position.
        var nodeEnter = node.enter().append('g')
            .attr('class', 'node')
            .attr("transform", function (d) {
                return "translate(" + source.x0 + "," + source.y0 + ")";
            })
            .on('click', click);

        // Add Circle for the nodes
        nodeEnter.append('circle')
            .attr('class', 'node')
            .attr('r', 1e-6)
            .style("fill", function (d) {
                return d._children ? "lightsteelblue" : "#fff";
            });

        // Add labels for the nodes
        nodeEnter.append('text')
            .attr('pointer-events', 'none')
            .attr('dy', '0.35em')
            .text(function (d) {
                return d.data.name;
            })
            .attr('text-anchor', 'middle')

        // UPDATE
        var nodeUpdate = nodeEnter.merge(node)
            .attr("fill", "#fff")
            .attr("stroke", "steelblue")
            .attr("stroke-width", "3px;")
            .style('font', '12px sans-serif')

        // Transition to the proper position for the node
        nodeUpdate.transition()
            .duration(duration)
            .attr("transform", function (event, i, arr) {
                const d = d3.select(this).datum();
                return "translate(" + d.x + "," + d.y + ")";
            });

        // Update the node attributes and style
        nodeUpdate.select('circle.node')
            .attr('r', 20)
            .style("fill", function (d) {
                return d._children ? "lightsteelblue" : "#fff";
            })
            .attr('cursor', 'pointer');


        // Remove any exiting nodes
        var nodeExit = node.exit().transition()
            .duration(duration)
            .attr("transform", function (event, i, arr) {
                const d = d3.select(this).datum();
                return "translate(" + source.x + "," + source.y + ")";
            })
            .remove();

        // On exit reduce the node circles size to 0
        nodeExit.select('circle')
            .attr('r', 1e-6);

        // On exit reduce the opacity of text labels
        nodeExit.select('text')
            .style('fill-opacity', 1e-6)



        // ****************** links section ***************************

        // Update the links...
        var link = g.selectAll('path.link')
            .data(links, function (d) {
                return d.id;
            });

        // Enter any new links at the parent's previous position.
        var linkEnter = link.enter().insert('path', "g")
            .attr("class", "link")
            .attr('d', function (d) {
                var o = {
                    x: source.x0,
                    y: source.y0
                }
                return diagonal(o, o)
            });

        // UPDATE
        var linkUpdate = linkEnter.merge(link)
            .attr("fill", "none")
            .attr("stroke", "#ccc")
            .attr("stroke-width", "2px")

        // Transition back to the parent element position
        linkUpdate.transition()
            .duration(duration)
            .attr('d', function (d) {
                return diagonal(d, d.parent)
            });

        // Remove any exiting links
        var linkExit = link.exit().transition()
            .duration(duration)
            .attr('d', function (event, i, arr) {
                const d = d3.select(this).datum();
                var o = {
                    x: source.x,
                    y: source.y
                }
                return diagonal(o, o)
            })
            .remove();

        // Store the old positions for transition.
        nodes.forEach(function (d) {
            d.x0 = d.x;
            d.y0 = d.y;
        });

        // Creates a curved (diagonal) path from parent to the child nodes
        function diagonal(s, d) {
            const path = `M ${s.x} ${s.y}
            C ${(s.x + d.x) / 2} ${s.y},
              ${(s.x + d.x) / 2} ${d.y},
              ${d.x} ${d.y}`

            return path
        }

        // Toggle children on click.
        function click(event, d) {
            if (d.children) {
                d._children = d.children;
                d.children = null;
            } else {
                d.children = d._children;
                d._children = null;
            }
            update(d);
        }
    }

    yield svg.node()
}


function _treeData() {
    return (
        {
            "name": "Atwel",
            "size": [100, 100],
            "children": [
                {
                    "name": "BA",
                    "size": [100, 50],
                    "children": [
                        { "name": "BAA", "size": [100, 50] },
                        {
                            "name": "BAB",
                            "size": [100, 50],
                            "children": [
                                { "name": "BABA", "size": [100, 50] },
                                { "name": "BABB", "size": [100, 50] }
                            ]
                        },
                        { "name": "BAC", "size": [200, 50] },
                    ]
                },
                {
                    "name": "BB",
                    "size": [100, 75],
                    "children": [
                        { "name": "BBA", "size": [50, 50] },
                        { "name": "BBB", "size": [50, 50] }
                    ]
                }
            ]
        }
    )
}

function _createSvg(svg) {
    return (
        svg
    )
}

function _d3(require) {
    return (
        require('d3@v6')
    )
}

function _flextree(require) {
    return (
        require("d3-flextree@v2")
    )
}

export default function define(runtime, observer) {
    const main = runtime.module();
    main.variable(observer()).define(["md"], _1);
    main.variable(observer()).define(["md"], _2);
    main.variable(observer()).define(["md"], _3);
    main.variable(observer()).define(["d3", "createSvg", "treeData", "flextree"], _4);
    main.variable(observer("treeData")).define("treeData", _treeData);
    main.variable(observer("createSvg")).define("createSvg", ["svg"], _createSvg);
    main.variable(observer("d3")).define("d3", ["require"], _d3);
    main.variable(observer("flextree")).define("flextree", ["require"], _flextree);
    return main;
}
