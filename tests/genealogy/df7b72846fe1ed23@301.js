// https://observablehq.com/@f72a47e598f0f948/d3-redux@301
function _1(md) {
  return (
    md`# Collapsible Family Tree.

Shows using d3v7 to display a d3.hierarchy chart. Each node can be collapsed and expanded, if there are parents. Includes zooming and panning.`
  )
}

function _chart(width, d3, fullTreeData, height, transitionElbow, elbow) {
  const dx = 600;
  const dy = width / 6;
  const margin = ({ top: 10, right: 120, bottom: 10, left: 40 });
  const memberBox = {
    width: 205,
    height: 65,
    marginHeight: 180,
    marginWidth: 50
  };
  const direction = 1;

  const zoom = d3.zoom()
    .scaleExtent([0.5, 2])
    .on("zoom", zoomed);

  const tree = d3.tree()
    .nodeSize([memberBox.height + memberBox.marginHeight, memberBox.width + memberBox.marginWidth])
    .separation(() => 0.5)
    ;

  const root = d3.hierarchy(fullTreeData, (person) => {
    return person.nodes;
  });

  console.log('root: ', root);

  console.log(`width: ${width}`);
  root.x0 = dy / 20;
  root.y0 = 0;

  root.descendants()
    .forEach((node, i) => {
      node.id = i;
      node._children = node.children;
      if (node.depth && node.data.level > 3) {
        node.children = null;
      }
    });


  const svg = d3.create("svg")
    .attr('viewBox', [-margin.left, -margin.top, width, dx])
    // .on('click', reset);
    ;

  const gContainer = svg.append('g')
    .attr('cursor', 'grab')
    .attr('id', 'SVGcontainer')
    .classed('svg-content-responsive', true);

  const gLink = gContainer.append("g")
    .attr("fill", "none")
    .attr("stroke", "#555")
    .attr("stroke-opacity", 0.4)
    .attr("stroke-width", 1.5);

  const gNode = gContainer.append("g")
    .attr('id', 'node-container')
    .attr("cursor", "pointer")
    .attr("pointer-events", "all")
    ;

  // svg.call(
  //   zoom.transform,
  //   d3.zoomIdentity.translate(150, 0)
  //   .scale(1)
  // );
  svg.call(zoom);

  function reset() {
    svg.transition().duration(750).call(
      zoom.transform,
      d3.zoomIdentity,
      d3.zoomTransform(svg.node()).invert([150, height / 2])
    );
  }

  function update(source) {
    console.log('update with source: ', source);

    const nodes = root.descendants().reverse();
    const links = root.links();
    const duration = 350;

    // compute the new tree layout.
    tree(root);

    // find min & max x (vertical)
    let left = root;
    let right = root;
    root.eachBefore(node => {
      if (node.x < left.x) { left = node; }
      if (node.x > right.x) { right = node; }
    });

    let height = right.x - left.x + margin.top + margin.bottom;
    height = Math.max(height, 320);

    const transition = svg.transition()
      .duration(duration)
      .ease(d3.easeQuad)
      .attr("viewBox", [-margin.left, left.x - margin.top, width, height])
      .tween("resize", window.ResizeObserver ? null : () => svg.dispatch("toggle"));

    // update the nodes...
    const node = gNode.selectAll('g.node')
      .data(nodes, d => d.id)
      // @ts-ignore
      .call(d3.drag()
        .on("start", dragstarted)
        .on("drag", dragged)
        .on("end", dragended))
      ;

    // Enter any new nodes at the parent's previous position.
    const nodeEnter = node.enter().append('g')
      .attr("transform", d => `translate(${source.y0},${source.x0})`)
      .attr('fill-opacity', 0)
      .attr('stroke-opacity', 0)
      .attr('class', (node) => addNodeClasses(node));

    nodeEnter.append('rect')
      .attr('class', 'frame')
      .attr('x', 0)
      .attr('y', 0)
      .attr('width', 0)
      .attr('height', 0);

    // add expandIcon
    addExpandIcon(nodeEnter);
    addText(nodeEnter);

    nodeEnter.append('path')
      .attr('class', 'blessing-indicator')
      .attr('d', 'M0 0 L10 0, 0 10 Z')
      .attr('stroke-width', 1)
      .style('fill-opacity', 0)
      .style('stroke-opacity', 0);


    // Transition nodes to their new position.
    const nodeUpdate = node.merge(nodeEnter)
      .transition(transition)
      .attr("transform", d => `translate(${d.y},${d.x})`)
      .attr("fill-opacity", 1)
      .attr("stroke-opacity", 1);

    nodeUpdate.select('rect.frame')
      .attr('fill-opacity', 1)
      .attr('stroke-opacity', 1)
      .attr('x', -(memberBox.width / 2))
      .attr('y', -(memberBox.height / 2))
      .attr('width', memberBox.width)
      .attr('height', memberBox.height);

    nodeUpdate.select('path.blessing-indicator')
      .style('fill-opacity', 1)
      .style('stroke-opacity', 1)
      .attr('transform', `translate(${-memberBox.width / 2 + 1}, ${-memberBox.height / 2 + 1})`);

    nodeUpdate.select('.expandIcon')
      .style('visibility', (node) => {
        // const person = node.data;
        // console.log('expandIcon node: ', node);
        // const v = (person.nodes?.length > 0) || false;
        const v = (node.children?.length > 0) || (node._children?.length > 0);
        // console.log('expandIcon visible: ', v);
        return v ? 'visible' : 'hidden';
      })
      .style('fill-opacity', 1)
      .style('stroke-opacity', 1)
      // .attr('name', (d) => { console.log('nodeUpdate expandIcon d: ', d); })
      ;


    // Transition exiting nodes to the parent's new position.
    const nodeExit = node.exit()
      .transition(transition)
      .remove()
      .attr("transform", d => `translate(${direction * source.y},${source.x})`)
      .attr("fill-opacity", 0)
      .attr("stroke-opacity", 0);

    nodeExit.select('path.blessing-indicator')
      .style('fill-opacity', 0)
      .style('stroke-opacity', 0)
      .attr('transform', 'translate(0, 0)');

    // Update the links…
    const link = gLink.selectAll("path")
      .data(links, d => d.target.id);

    // Enter any new links at the parent's previous position.
    const linkEnter = link.enter().append("path")
      .attr("d", d => {
        const o = { x: source.x0, y: source.y0 };
        return transitionElbow({ source: o, target: o });
      });

    // Transition links to their new position.
    link.merge(linkEnter)
      .transition(transition)
      .attr("d", (d) => {
        return elbow(d, direction);
      });

    // Transition exiting nodes to the parent's new position.
    link.exit()
      .transition(transition)
      .remove()
      .attr("d", d => {
        const o = { x: source.x, y: source.y };
        return transitionElbow({ source: o, target: o });
      });

    // Stash the old positions for transition.
    root.eachBefore(d => {
      d.x0 = d.x;
      d.y0 = d.y;
    });

  }; // end Update

  function zoomed({ transform }) {
    gContainer.attr("transform", transform);
  }

  function dragstarted() {
    // @ts-ignore
    d3.select(this).raise();
    gContainer.attr("cursor", "grabbing");
  }

  function dragged(event, d) {
    // @ts-ignore
    d3.select(this).attr("cx", d.x = event.x).attr("cy", d.y = event.y);
  }

  function dragended() {
    gContainer.attr("cursor", "grab");
  }

  function addNodeClasses(node) {
    const displayClasses = ['node', 'person', 'ancestor'];
    const person = node.data;
    console.log('person: ', person);
    if (person.id /* && !person.generated && person.datasource === 'zbar' */) {
      displayClasses.push('person-member');
    }
    if (person.isProminent) {
      displayClasses.push('person-prominent');
    }
    if (person.hasDocument) {
      displayClasses.push('person-blessing');
    }
    if (person.unconfirmed) {
      displayClasses.push('person-unconfirmed');
    }
    if (person.level === 1) {
      displayClasses.push('person-match');
    }
    if (person.generated || (person.datasource === 'pump' && person.id === null)) {
      displayClasses.push('add-root');
    }
    if (person.generated || (person.datasource === 'pump' && person.id !== null && person.id.indexOf('pump') === 0)) {
      displayClasses.push('person-added');
    }
    if (person.childLinked && person.datasource === 'zbar') {
      displayClasses.push('person-added-id');
    }
    return displayClasses.join(' ');
  }

  function addExpandIcon(nodeEnter) {
    let expandIcon = nodeEnter.append('g')
      .attr('class', 'expandIcon')
      .attr('transform', (d) => `translate(${(memberBox.width / 2 - 15)},${-10})`);

    expandIcon.append('circle')
      .attr('cx', 0)
      .attr('cy', 0)
      .attr('r', 3)
      .attr('fill', '#aaa');

    expandIcon.append('circle')
      .attr('cx', 0)
      .attr('cy', 10)
      .attr('r', 3)
      .attr('fill', '#aaa');

    expandIcon.append('circle')
      .attr('cx', 0)
      .attr('cy', 20)
      .attr('r', 3)
      .attr('fill', '#aaa');

    expandIcon.append('rect')
      .attr('class', 'box')
      .attr('x', -8)
      .attr('y', -8)
      .attr('width', 16)
      .attr('height', 36)
      .style('fill-opacity', 0)
      .style('stroke-opacity', 0)
      .on('click', (event, d) => {
        // event.stopPropagation();
        if (d.children) {
          d._children = [...d.children];
          d.children = null;
        } else if (d._children) {
          d.children = [...d._children];
          d._children = null;
        }
        update(d);
      });
  }

  function addText(nodeEnter) {
    const yOffset = 10;

    nodeEnter.append('text')
      .attr('dx', -(memberBox.width / 2) + 10)
      .attr('dy', (-memberBox.height / 2) + 25 + yOffset)
      .attr('text-anchor', 'left')
      .attr('class', 'name')
      .attr('level', (d) => d.data.level)
      .on('click', (event, d) => {
        event.stopPropagation();
        console.warn('Show modal for: ', d.data.name);
      })
      .text((d) => nodeName(d)) // Check for null firstName in the case of 'Unknown' lastName.
      .style('fill-opacity', 1);

  }

  function nodeName(node) {
    const person = node.data;
    return person.name;
  }

  update(root);

  return svg.node();
}


function _transitionElbow() {
  return (
    function transitionElbow(d) {
      return 'M' + d.source.y + ',' + d.source.x
        + 'H' + d.source.y
        + 'V' + d.source.x
        + 'H' + d.source.y;
    }
  )
}

function _elbow() {
  return (
    function elbow(d, direction) {
      const memberBox = {
        width: 205,
        height: 65,
        marginHeight: 180,
        marginWidth: 50
      };

      // start point x1, y1
      const x1 = d.source.y + (direction * memberBox.width / 2);
      const y1 = d.source.x;
      // endpoint x4, y4
      const x4 = d.target.y - (direction * memberBox.width / 2);
      const y4 = d.target.x;

      const x2 = x1 + (x4 - x1) / 2;
      const y2 = y1;

      const x3 = x2;
      const y3 = y4;

      return `M${x1},${y1}H${x2}V${y2 + (y3 - y2)}H${x4}`;
    }
  )
}

function _fullTreeData(FileAttachment) {
  return (
    FileAttachment("family-tree-clean@1.json").json()
  )
}

function _height() {
  return (
    600
  )
}

function _d3(require) {
  return (
    require('d3@7')
  )
}

function _twoGenerationData(FileAttachment) {
  return (
    FileAttachment("family-tree-2-generation@2.json").json()
  )
}

function _9(htl) {
  return (
    htl.html`<style type="text/css">
.member-chart svg {
  width: 100%;
}
.svg-container {
  display: inline-block;
  position: relative;
  width: 100%;
  padding-bottom: 100%; /* aspect ratio */
  vertical-align: top;
  overflow: hidden;
  background-color: #FFFFFF;
  border: 1px solid #ddd;
  margin-top: -24px;
  border-top: 0;
}
.svg-content-responsive {
  display: inline-block;
  position: absolute;
  top: 10px;
  left: 0;
  cursor: grab;
}
.person rect {
  fill: #fff;
  stroke: #268695;
  stroke-width: 3px;
}

text.name, text.name2 {
  font-family: 'Ensign:Sans', "Zoram", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  font-size: 14px;
  font-weight: bold;
  fill: #333333;
}

text.addIcon, text.addIcon2 {
  font-family: "pb-icons";
  fill: #3aab8b;
  font-size: 31px;
  cursor: pointer;
}

text.name, text.name2 {
  font-weight: bold;
  font-size: 12px;
}

text.name2 {
  font-size: 14px;
  fill: #268695;
}

text.name3 {
  fill: #3aab8b;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
}

text.name:hover, text.name2:hover, text.name3:hover {
  text-decoration: underline;
  cursor: pointer;
}

.expandIcon:hover {
  cursor: pointer;
}
.person.person-member rect, .person.person-member path {
  fill: #BFE4F1;
}
.person.person-prominent rect, .person.person-prominent text.prominent-text, .person.person-prominent path {
  fill: #FADEDD;
  stroke: #c01e25;

  font-family: 'Ensign:Sans', "Zoram", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  font-size: 10px;
}
.person.person-match rect, .person.person-match path {
  fill: #d9f9b7;
  stroke: #3f8c1e;
}
.person.add-root rect {
  stroke-dasharray: 8;
  fill: #FFFFFF
}
.person.person-added rect {
  stroke-dasharray: 8;
  fill: #FFFFFF
}
.person.person-added-id rect {
  stroke-dasharray: 8;
  fill: #BFE4F1;
}
.person text.birthDate {
  font-family: 'Ensign:Sans', "Zoram", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  font-size: 12px;
  fill: #333333;
}
.person text.memberId {
  font-family: 'Ensign:Sans', "Zoram", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  font-size: 12px;
  font-weight: bold;
  fill: #333333;
}

.person.person-prominent text.prominent-text {
  fill: #c01e25;
  stroke: #c01e25;
}

.person path.blessing-indicator {
  fill: #268695;
  display: none;
}
.person.person-blessing path.blessing-indicator {
  display: block;
}
.person.person-prominent path.blessing-indicator {
  fill: #c01e25;
  stroke: #c01e25;
}

.person.person-match path.blessing-indicator {
  fill: #3f8c1e;
  stroke: #3f8c1e;
}



.person.person-unconfirmed rect {
  stroke-dasharray: 5;
}
.person {
  font: 14px sans-serif;
}

.link {
  fill: none;
  stroke: #ccc;
  stroke-width: 1.5px;
}

#scrollContainer .nav-tabs {
  margin-left: 0;
  margin-right: 0;
}
</style>`
  )
}

export default function define(runtime, observer) {
  const main = runtime.module();
  function toString() { return this.url; }
  const fileAttachments = new Map([
    ["family-tree-2-generation@2.json", { url: new URL("./files/8b805f40cfb75b6090f2e31dbc861f64180410b0365c34bf7f50584b9913899832d7b13bbb4e5b44854dc382d918c7cd4ba5bd2d086c9e6a6afb3aef08c1f7a1.json", import.meta.url), mimeType: "application/json", toString }],
    ["family-tree-clean@1.json", { url: new URL("./files/56c1f1eb73a872c09e92a9d0d987155882ea9c39f23e0f6a74c4015c94e786072b1e4e4d86f1e34b8251917598c26cd454bd5dfca5b46fc109105877f8e44aed.json", import.meta.url), mimeType: "application/json", toString }]
  ]);
  main.builtin("FileAttachment", runtime.fileAttachments(name => fileAttachments.get(name)));
  main.variable(observer()).define(["md"], _1);
  main.variable(observer("chart")).define("chart", ["width", "d3", "fullTreeData", "height", "transitionElbow", "elbow"], _chart);
  main.variable(observer("transitionElbow")).define("transitionElbow", _transitionElbow);
  main.variable(observer("elbow")).define("elbow", _elbow);
  main.variable(observer("fullTreeData")).define("fullTreeData", ["FileAttachment"], _fullTreeData);
  main.variable(observer("height")).define("height", _height);
  main.variable(observer("d3")).define("d3", ["require"], _d3);
  main.variable(observer("twoGenerationData")).define("twoGenerationData", ["FileAttachment"], _twoGenerationData);
  main.variable(observer()).define(["htl"], _9);
  return main;
}
