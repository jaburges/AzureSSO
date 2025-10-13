/**
 * PTA Shortcodes JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initPTAShortcodes();
    });
    
    function initPTAShortcodes() {
        // Initialize any interactive elements
        initRoleCards();
        initOrgCharts();
    }
    
    function initRoleCards() {
        $('.pta-role-item').on('click', function() {
            $(this).toggleClass('expanded');
        });
    }
    
    function initOrgCharts() {
        // This function will be called by individual org chart shortcodes
        // when D3.js is available
    }
    
    // Global function for rendering org charts
    window.renderPTAOrgChart = function(containerId, data, options) {
        if (typeof d3 === 'undefined') {
            console.error('D3.js is required for org charts');
            return;
        }
        
        var container = d3.select('#' + containerId);
        var width = container.node().getBoundingClientRect().width;
        var height = parseInt(options.height) || 400;
        
        // Clear any existing content
        container.selectAll("*").remove();
        
        var svg = container.append("svg")
            .attr("width", width)
            .attr("height", height);
        
        // Create a simple hierarchical layout
        renderSimpleOrgChart(svg, data, width, height, options);
    };
    
    function renderSimpleOrgChart(svg, data, width, height, options) {
        var departments = data.departments;
        var roles = data.roles;
        var assignments = data.assignments;
        
        if (!departments || departments.length === 0) {
            svg.append("text")
                .attr("x", width / 2)
                .attr("y", height / 2)
                .attr("text-anchor", "middle")
                .style("fill", "#999")
                .text("No organizational data available");
            return;
        }
        
        // Simple layout: departments as boxes with roles beneath
        var deptWidth = Math.min(200, (width - 40) / departments.length);
        var deptHeight = 60;
        var roleHeight = 40;
        var margin = 20;
        
        // Group for departments
        var deptGroup = svg.append("g")
            .attr("class", "departments");
        
        departments.forEach(function(dept, i) {
            var x = margin + (i * (deptWidth + 10));
            var y = margin;
            
            // Department box
            var deptBox = deptGroup.append("g")
                .attr("class", "department")
                .attr("transform", "translate(" + x + "," + y + ")");
            
            deptBox.append("rect")
                .attr("width", deptWidth)
                .attr("height", deptHeight)
                .attr("rx", 5)
                .style("fill", "#007cba")
                .style("stroke", "#005a87")
                .style("stroke-width", 1);
            
            deptBox.append("text")
                .attr("x", deptWidth / 2)
                .attr("y", 20)
                .attr("text-anchor", "middle")
                .style("fill", "white")
                .style("font-weight", "bold")
                .style("font-size", "12px")
                .text(dept.name);
            
            if (dept.vp) {
                deptBox.append("text")
                    .attr("x", deptWidth / 2)
                    .attr("y", 40)
                    .attr("text-anchor", "middle")
                    .style("fill", "white")
                    .style("font-size", "10px")
                    .text("VP: " + dept.vp);
            }
            
            // Department roles
            var deptRoles = roles.filter(function(role) {
                return role.department_id == dept.id;
            });
            
            deptRoles.forEach(function(role, j) {
                var roleY = y + deptHeight + 20 + (j * (roleHeight + 5));
                
                var roleBox = deptGroup.append("g")
                    .attr("class", "role")
                    .attr("transform", "translate(" + x + "," + roleY + ")");
                
                var fillColor = role.assigned_count >= role.max_occupants ? "#28a745" : 
                               role.assigned_count > 0 ? "#ffc107" : "#dc3545";
                
                roleBox.append("rect")
                    .attr("width", deptWidth)
                    .attr("height", roleHeight)
                    .attr("rx", 3)
                    .style("fill", fillColor)
                    .style("fill-opacity", 0.2)
                    .style("stroke", fillColor)
                    .style("stroke-width", 1);
                
                roleBox.append("text")
                    .attr("x", 5)
                    .attr("y", 15)
                    .style("font-size", "10px")
                    .style("font-weight", "bold")
                    .text(role.name);
                
                roleBox.append("text")
                    .attr("x", 5)
                    .attr("y", 30)
                    .style("font-size", "9px")
                    .style("fill", "#666")
                    .text(role.assigned_count + "/" + role.max_occupants + " filled");
                
                // Add assignments
                var roleAssignments = assignments.filter(function(assignment) {
                    return assignment.role_id == role.id;
                });
                
                if (roleAssignments.length > 0) {
                    roleBox.append("title")
                        .text("Assigned to: " + roleAssignments.map(function(a) {
                            return a.user_name;
                        }).join(", "));
                }
            });
        });
        
        // Add interactivity
        if (options.interactive) {
            svg.selectAll(".role")
                .style("cursor", "pointer")
                .on("click", function(event, d) {
                    // Could add modal or tooltip here
                    console.log("Role clicked", d);
                });
        }
    }
    
})(jQuery);







