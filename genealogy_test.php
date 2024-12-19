<!DOCTYPE html>
<html>

<head>
    <title>D3 Genealogy Visualization with Tooltips</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
    <style>
        .node circle {
            fill: #fff;
            stroke: #4682B4;
            stroke-width: 2px;
        }

        .node text {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .link {
            fill: none;
            stroke: #ccc;
            stroke-width: 1.5px;
        }

        .tooltip {
            position: absolute;
            text-align: center;
            padding: 8px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            border-radius: 4px;
            pointer-events: none;
            /* Prevent tooltip from blocking mouse events */
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div id="chart"></div>
    <script>
        const data = {
            "id": "1",
            "username": "admin",
            "account": "Enterprise",
            "caption": "IR",
            "bonus_indirect_referral": "0.00",
            "balance": "0.00",
            "children": [{
                "id": "2",
                "username": "host1",
                "account": "NFA",
                "caption": "IR",
                "bonus_indirect_referral": "0.00",
                "balance": "0.00",
                "children": [{
                    "id": "3",
                    "username": "host2",
                    "account": "VIP",
                    "caption": "IR",
                    "bonus_indirect_referral": "0.00",
                    "balance": "0.00",
                    "children": [{
                        "id": "5",
                        "username": "host4",
                        "account": "NFA",
                        "caption": "IR",
                        "bonus_indirect_referral": "0.00",
                        "balance": "0.00"
                    },
                    {
                        "id": "6",
                        "username": "host5",
                        "account": "NFA",
                        "caption": "IR",
                        "bonus_indirect_referral": "0.00",
                        "balance": "0.00"
                    }
                    ]
                },
                {
                    "id": "4",
                    "username": "host3",
                    "account": "VIP",
                    "caption": "IR",
                    "bonus_indirect_referral": "0.00",
                    "balance": "0.00",
                    "children": [{
                        "id": "7",
                        "username": "host6",
                        "account": "NFA",
                        "caption": "IR",
                        "bonus_indirect_referral": "0.00",
                        "balance": "0.00"
                    },
                    {
                        "id": "8",
                        "username": "host7",
                        "account": "NFA",
                        "caption": "IR",
                        "bonus_indirect_referral": "0.00",
                        "balance": "0.00"
                    }
                    ]
                }
                ]
            }
            ]
        };

        const margin = {
            top: 40,
            right: 90,
            bottom: 50,
            left: 90
        };
        const width = 800 - margin.left - margin.right;
        const height = 600 - margin.top - margin.bottom;

        const svg = d3.select("#chart")
            .append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", `translate(${margin.left},${margin.top})`);

        const tree = d3.tree().size([width, height]);
        const root = d3.hierarchy(data);
        const treeData = tree(root);

        const links = svg.selectAll(".link")
            .data(treeData.links())
            .enter()
            .append("path")
            .attr("class", "link")
            .attr("d", d3.linkVertical().x(d => d.x).y(d => d.y));

        const tooltip = d3.select("body").append("div").attr("class", "tooltip");

        const node = svg.selectAll(".node")
            .data(treeData.descendants())
            .enter()
            .append("g")
            .attr("class", "node")
            .attr("transform", d => `translate(${d.x},${d.y})`)
            .on("mouseover", function (event, d) {
                tooltip.transition().duration(200).style("opacity", .9);
                tooltip.html(`Username: ${d.data.username}<br>Account: ${d.data.account}<br>Caption: ${d.data.caption}<br>Bonus: ${d.data.bonus_indirect_referral}<br>Balance: ${d.data.balance}`)
                    .style("left", (event.pageX) + "px")
                    .style("top", (event.pageY - 28) + "px");
            })
            .on("mouseout", function (event, d) {
                tooltip.transition().duration(500).style("opacity", 0);
            });

        node.append("circle").attr("r", 6);

        node.append("text")
            .attr("dy", "-1.2em")
            .attr("x", 0)
            .style("text-anchor", "middle")
            .text(d => d.data.username);
    </script>
</body>

</html>