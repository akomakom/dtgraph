@extends('dtgraph.dynamic.applayout')

@section('header')


<script src="//d3js.org/d3.v3.min.js"></script>
<script>

    var errorData = null; //set by controller
    var errorTimeout = null;

    function handlError(error) {
        console.debug("Error:" + error);
        errorData.notices = error;
        errorData.$apply();
        if (errorTimeout) {
            clearTimeout(timeout);
        }

        //set an expiration for the error display
        errorTimeout = setTimeout(function() {
            errorData.notices = "";
            errorData.$apply();
        }, 20000);
    }

    var Line = function(cssClass, extent) {
        this.cssClass = cssClass;
        this.extent = extent;
    }

    //aggregator class for lines in order to calculate overall extents
    var Lines = function() {
        this.list  = {};
        var min = 0;
        var max = 0;

        this.addLine = function(line) {
            if (this.list[line.cssClass]) {
                throw "Already have a line with class " + line.cssClass;
            }

            this.list[line.cssClass] = line;
            this.recalculateExtents();
        };

        this.removeLine = function(cssClass) {
            delete this.list[cssClass];
            this.recalculateExtents();
        };

        this.recalculateExtents = function() {
            min = 0;
            max = 0;

            for(var lineName in this.list) {
                var line = this.list[lineName];
                if (!min || line.extent[0] < min) {
                    min = line.extent[0];
                }
                if (!max || line.extent[1] > max) {
                    max = line.extent[1];
                }
            }
        };

        this.getExtents = function() {
            return [min, max];
        };

        this.getCssClasses = function() {
            return Object.keys(this.list);
        }
    }


    var GraphManagement = function() {
        var lines = new Lines();


        var margin = {top: 20, right: 20, bottom: 30, left: 50},
            width = 960 - margin.left - margin.right,
            height = 500 - margin.top - margin.bottom;

        var x = d3.time.scale()
            .range([0, width]);

        var y = d3.scale.linear()
            .range([height, 0]);

        var xAxis = d3.svg.axis()
            .scale(x)
            .orient("bottom")
            .tickSize(-height);

        var yAxis = d3.svg.axis()
            .scale(y)
            .orient("left")
            .tickSize(-width);


        var line = d3.svg.line()
            .x(function (d) {
                return x(d.time);
            })
            .y(function (d) {
                return y(d.temp);
            });



        //define the zoom
        var zoom = d3.behavior.zoom()
            .x(x)
            .y(y)
            .scaleExtent([0.01,10000])
            .on("zoom", zoomed);


        var svg = d3.select("#graph")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", "translate(" + margin.left + "," + margin.top + ")")
            .call(zoom);


        svg.append("rect")
            .attr("width", width)
            .attr("height", height)
            .attr("class", "zoombg");


        //Set up axis

        svg.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + height + ")")
            .call(xAxis);

        svg.append("g")
            .attr("class", "y axis")
            .call(yAxis)
            .append("text")
            .attr("transform", "rotate(-90)")
            .attr("y", 6)
            .attr("dy", ".71em")
            .style("text-anchor", "end")
            .text("Temp (F)");




        //call the zoom on the SVG
//        svg.call(zoom);

        //define the zoom function
        function zoomed() {
////
//            svg.select(".x.axis").call(xAxis);
//            svg.select(".y.axis").call(yAxis);

//
            redraw(1);

//            svg.selectAll(".tipcircle")
//                .attr("cx", function(d,i){return x(d.date)})
//                .attr("cy",function(d,i){return y(d.value)});
//
//            svg.selectAll(".line")
//                .attr("class","line")
//                .attr("d", function (d) { return line(d.values)});
        }

        this.addLine = function(url, cssClass) {
            var result;

            d3.json(url, function (error, data) {
                if (error) {
                    handlError("Failed to load data for sensor: " + error.status + " " + error.statusText + " from " + url);
                    return;
                }
                if (!data.ok) {
                    handlError('Bad data for sensor, not ok: ' + data);
                    return;
                }
//                console.log(data);


                data = data.data;

                data.forEach(function (d) {
                    d.time = new Date(d.time * 1000);
                    d.temp = +d.temp;
                });


                lines.addLine(new Line(cssClass, d3.extent(data, function (d) {
                    return d.temp;
                })));
                x.domain(d3.extent(data, function (d) {
                    return d.time;
                }));
                //y.domain(d3.extent(data, function(d) { return d.temp; }));

                redraw(750);
                zoom.x(x).y(y);

                var path = svg.append("path")
                    .datum(data)
                    .attr("class", "line " + cssClass)
                    .attr("d", line);

//            svg.select(".line.line" + lineCount)   // change the line
//                .duration(750)
//                .attr("d", line(data));
//                .datum(data);

//            path.duration(750).datum(data);

            });

        }

        function redraw(duration) {
            y.domain(lines.getExtents());

            var svgTransition = d3.select("#graph").transition();

            svgTransition.select(".x.axis") // change the x axis
                .duration(duration)
                .call(xAxis);
            svgTransition.select(".y.axis") // change the y axis
                .duration(duration)
                .call(yAxis);

            //recalculate all existing graphs.
            // this has to be done individually (per graph)
            var currentClasses = lines.getCssClasses();
            for (idx in currentClasses) {
                svgTransition.select('.' + currentClasses[idx]).duration(duration).attr('d', line);
            }
        }

        this.removeLine = function(cssClass) {
            lines.removeLine(cssClass);  // stop taking it into account
            svg.selectAll('.line.' + cssClass).remove();  //remove from svg
            redraw(500);

            zoom.x(x).y(y);
        }


    };





    // Load Sensor metadata
    (function(angular) {
        'use strict';

        function applyAlreadyCheckedSensorCheckboxes() {
            $('ul.sensors input[type=checkbox]:checked').each(function(idx, checkbox) {
//                $(checkbox).attr('checked', false).attr('checked', true);
                $(checkbox).trigger('click');
                $(checkbox).trigger('click');
            });
        }


        var dtgraphApp = angular.module('dtgraphApp', ['ngStorage']);

        dtgraphApp.controller('DtgraphNoticesCtrl', function($scope) {
            //$scope.notices = errorData;
            errorData = $scope;
            $scope.notices = "";
        });

        dtgraphApp.controller('DtgraphSensorCtrl', function ($scope, $http, $localStorage) {

            $scope.$storage = $localStorage.$default({
                checkedSensors: {},
            });

            $http.get('/api/sensor').then(
                function(response) {
                    var data = response.data;
                    if (data.ok) {
                        $scope.sensors = data.data;
                        //make sure that they are all in checkedSensors
                        for (var idx in $scope.sensors) {
                            var sensor = $scope.sensors[idx];
                            //if it's false or undefined, ensure we have a hash entry
                            if (!$scope.$storage.checkedSensors[sensor.SerialNumber]) {
                                $scope.$storage.checkedSensors[sensor.SerialNumber] = false;
                            }
                        }

                        //we can't add graphs at this point, we need to do it
                        // after the document is processed
                        setTimeout(applyAlreadyCheckedSensorCheckboxes, 0);
                    } else {
                        handlError("Failed to load sensor data: " + response);
                    }
                },
                function(response) {
                    handleError("Failed to load sensor data: " + response);
                }
            );


            $scope.sensorChecked = function(sensor, index) {
//                sensor.selected = !sensor.selected;
                var cssClass = "line" + (index + 1);
                //by now the state has already been changed
                if ($scope.$storage.checkedSensors[sensor.SerialNumber]) {
                    gm.addLine("api/reading/" + sensor.SerialNumber + "?start=1388552400&end=1420088399", cssClass);
                } else {
                    gm.removeLine(cssClass);
                }
            };
        });



    })(window.angular);



    var gm;

    $(function() {
        gm = new GraphManagement();
    });


</script>

@endsection



@section('sidebar')

<div id="graphcontrols">
    <div id="sensors"  ng-controller="DtgraphSensorCtrl">

        <ul class="sensors">
            <li ng-repeat="sensor in sensors" ng-click="rowClicked(obj)">
                <input type="checkbox" ng-model="$storage.checkedSensors[sensor.SerialNumber]" ng-change="sensorChecked(sensor, $index)" name="sensor" value="@{{sensor.SerialNumber}}" />
                <span class="sensorcolor bgcolor@{{$index + 1}}">&nbsp;</span>
                <span title="@{{sensor.description}} (@{{sensor.SerialNumber}})">@{{sensor.name}}</span>
            </li>
        </ul>
    </div>
</div>
@endsection


@section('content')
<div id="notices" ng-controller="DtgraphNoticesCtrl" ng-model="notices">@{{notices}}</div>
<svg id="graph"></svg>
@endsection
