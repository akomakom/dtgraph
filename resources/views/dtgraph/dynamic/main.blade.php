@extends('dtgraph.dynamic.applayout')

@section('header')


<script src="//d3js.org/d3.v3.min.js"></script>
<script>

    //defaults
    var timeStart = new Date().getTime() - 31536000000;      // a year ago
    var timeEnd = new Date().getTime();

    var sensorUrlConstructor = function(serialNumber, graphMode) {
        return "api/reading/" + serialNumber + "?start=" + Math.round(timeStart / 1000) + "&end=" + Math.round(timeEnd / 1000) + "&mode=" + graphMode;
    };


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

    var Line = function(serialNumber, cssClass) {
        this.serialNumber = serialNumber;
        this.cssClass = cssClass;
        this.path = null;
        this.yExtent = null; //y extent
        this.xExtent = null;
        this.graphMode = 'avg';
    };

    //aggregator class for lines in order to calculate overall extents
    var Lines = function() {
        this.list  = {};
        var min = 0;
        var max = 0;

        this.getCount = function() {
            return Object.keys(this.list).length;
        };

        this.addLine = function(serialNumber, cssClass) {
            if (this.list[cssClass]) {
                console.log("Already have a line with class " + cssClass);
                throw "Already have line " + cssClass;
            }

            this.list[cssClass] = new Line(serialNumber, cssClass);
            //this.recalculateExtents();
            return this.list[cssClass];
        };

        this.removeLine = function(cssClass) {
            var result = this.list[cssClass] != null;
            delete this.list[cssClass];
            this.recalculateExtents();
            return result;
        };

        this.recalculateExtents = function() {
            min = 0;
            max = 0;


            for(var lineName in this.list) {
                var line = this.list[lineName];
                if (!line.yExtent) {
                    continue;
                }
                if (!min || line.yExtent[0] < min) {
                    min = line.yExtent[0];
                }
                if (!max || line.yExtent[1] > max) {
                    max = line.yExtent[1];
                }
            }
        };

        //Return the overall y extent to display all known lines
        this.getExtents = function() {
            return [min, max];
        };

        this.getCssClasses = function() {
            return Object.keys(this.list);
        };

        this.xDomainExceedsExtents = function(extent) {
            result = false;
            var self = this;
            $.each(Object.keys(this.list), function(idx, key) {
                var line = self.list[key];

                if (line.xExtent[0] > extent[0] || line.xExtent[1] < extent[1] ) {
                    result = true;
                    false;
                }
            });
            return result;
        };


        this.get = function(cssClass) {
            return this.list[cssClass];
        };

        this.count = function() {
            return Object.keys(this.list).length;
        };

    };


    var GraphManagement = function() {
        var lines = new Lines();
        var self = this;


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
            .on("zoom", zoomed)
            .on("zoomend", zoomend);

        var previousZoomScale = zoom.scale();
        var zoomReloadFactor = 2;

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




        function zoomend(event) {
            var scale = zoom.scale(); //new scale
//            console.debug("Zoom change from " + previousZoomScale + " to " + scale);

            //save new viewport position
            var newRange = x.domain();
            timeStart = newRange[0].getTime();
            timeEnd = newRange[1].getTime();


            var redrawDelayed = function() {
                redraw(200);
            };

            if (Math.max(previousZoomScale, scale)/Math.min(previousZoomScale, scale) > zoomReloadFactor) {
                console.debug("zoom Reloading");
                previousZoomScale = scale;
                //sufficient zoom change to reload data
                //figure out the max/min of the current X axis, maybe load twice that.
                //TODO: Also, maybe do this on a slight timer delay, cancel it if another zoomstart happens.

                self.updateAllData(redrawDelayed);

            } else if (lines.xDomainExceedsExtents(newRange)) {
                console.debug("move Reloading");
                //have we panned beyond loaded data?

                self.updateAllData(redrawDelayed);
            }

        };

        //call the zoom on the SVG
//        svg.call(zoom);

        //define the zoom function
        function zoomed() {

////
//            svg.select(".x.axis").call(xAxis);
//            svg.select(".y.axis").call(yAxis);

//
            redraw(0);

//            svg.selectAll(".tipcircle")
//                .attr("cx", function(d,i){return x(d.date)})
//                .attr("cy",function(d,i){return y(d.value)});
//
//            svg.selectAll(".line")
//                .attr("class","line")
//                .attr("d", function (d) { return line(d.values)});
        }


        this.updateAllData = function(postcallback) {
            var count = Object.keys(lines.list).length;
            var counter = postcallback? function() {
                count--;
                if (count == 0) {
                    setTimeout(postcallback, 200);
                }
            } : null;

            $.each(Object.keys(lines.list), function(index, key) {
                self.updateData(lines.list[key], counter, false);
            });
        };

        this.updateData = function(lineWrapper, postcallback, setXDomain) {
            var url  = sensorUrlConstructor(lineWrapper.serialNumber, lineWrapper.graphMode);
            d3.json(url, function (error, data) {
                if (error) {
                    handlError("Failed to load data for sensor: " + error.status + " " + error.statusText + " from " + url);
                    return;
                }
                if (!data.ok) {
                    handlError('Bad data for sensor, not ok: ' + data);
                    return;
                }

                data = data.data;



                data.forEach(function (d) {
                    d.time = new Date(d.time * 1000);
                    d.temp = +d.temp;
                });

                var xExtent = d3.extent(data, function (d) {
                    return d.time;
                });
                
                lineWrapper.xExtent = xExtent;
                
                if (setXDomain) {
                    //only adjust x domain if this is the first line.
                    // after that, zoom wins
                    x.domain(xExtent);
                }

                if (lineWrapper.path == null) {
                    //make a path

                    var path = svg.append("path")
                        .datum(data)
                        .attr("class", "line " + lineWrapper.cssClass)
                        .attr("d", line);
                    lineWrapper.path = path;
                } else {
                    lineWrapper.path.datum(data).attr('d', line);
                }

                lineWrapper.yExtent = d3.extent(data, function (d) {
                    return d.temp;
                });
                lines.recalculateExtents();

                if (postcallback) {
                    postcallback();
                }

            });

        };

        this.addLine = function(serialNumber, cssClass, graphMode) {
            var result;
//
//            var line = lines.get(cssClass);
//            if (line) {
//                console.debug("Already showing line " + cssClass);
//                return; //already have this line
//            }

//            if (!line) {
//                line = new Line(serialNumber, cssClass, null, null);
            try {
                var line = lines.addLine(serialNumber, cssClass);
//            }
                line.graphMode = graphMode;

                this.updateData(line, function () {
                    zoom.x(x).y(y);

                    redraw(750);
                }, lines.count() == 1); //if we are adding and this is the first line, set x domain
            } catch (bla) {
                //forget it, don't care, don't add the line if it's a race condition
            }

        };

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
                svgTransition.select('.' + currentClasses[idx].replace(/ /g, '.')).duration(duration).attr('d', line);
            }

            //TODO: show range-dependent information, eg year/month, etc.
        }


        this.removeLine = function(cssClass) {
            if (lines.removeLine(cssClass)) {
                svg.selectAll('.line.' + cssClass.replace(/ /g, '.')).remove();  //remove from svg
                redraw(500);

                zoom.x(x).y(y);
            }
        };


    };

    var gm;

    // Load Sensor metadata
    (function(angular) {
        'use strict';







        //TODO: remove
        function applyAlreadyCheckedSensorCheckboxes() {
            $('ul.sensors input[type=checkbox]:checked').each(function(idx, checkbox) {
//                $(checkbox).attr('checked', false).attr('checked', true);
                $(checkbox).trigger('click').trigger('click');
//                $(checkbox).trigger('click');
            });
        }


        var dtgraphApp = angular.module('dtgraphApp', ['ngStorage']);


        dtgraphApp.directive('d3angular', function () {

            return {
                restrict: 'A',
                scope: {
                },
                link: function (scope, element, attrs) {
                    gm = new GraphManagement();  //load it at the right point in the init sequence.
                }
            }
        });


        dtgraphApp.controller('DtgraphNoticesCtrl', function($scope) {
            //$scope.notices = errorData;
            errorData = $scope;
            $scope.notices = "";
        });

        dtgraphApp.controller('DtgraphSensorCtrl', function ($scope, $http, $localStorage) {

            $scope.$storage = $localStorage.$default({
                checkedSensors: {},
                timeRange: { timeStart: timeStart, timeEnd: timeEnd},
                graphModes: { min: false, max: false, avg: true},
            });

            $scope.$watchCollection('$storage.checkedSensors', function(newValue, oldValue) {
                console.debug("value changed from " + oldValue + " to " + newValue);
                $scope.applyCheckedSensors();
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
//                        setTimeout(applyAlreadyCheckedSensorCheckboxes, 0);
                    } else {
                        handlError("Failed to load sensor data: " + response);
                    }
                },
                function(response) {
                    handleError("Failed to load sensor data: " + response);
                }
            );


            $scope.applyCheckedSensors = function() {
                var index = 0;
                $.each($scope.$storage.checkedSensors, function(serialNumber, onState) {

                    var cssClass = "line" + (Number.parseInt(index) + 1);
                    //by now the state has already been changed
                    $.each($scope.$storage.graphModes, function(mode, modeOn) {
                        if (onState && modeOn) {
                            gm.addLine(serialNumber, cssClass + " " + mode, mode);
                        } else {
                            gm.removeLine(cssClass + " " + mode);
                        }
                    });

                    index++;
                });
            };
//
//            $scope.sensorChecked = function(serialNumber, index) {
////                sensor.selected = !sensor.selected;
//                var cssClass = "line" + (Number.parseInt(index) + 1);
//                //by now the state has already been changed
//                if ($scope.$storage.checkedSensors[sensor.SerialNumber]) {
//                    gm.addLine(sensorUrlConstructor(sensor.SerialNumber), sensor.SerialNumber, cssClass);
//                } else {
//                    gm.removeLine(cssClass);
//                }
//            };

            $scope.graphModeChanged = function() {
                $scope.applyCheckedSensors();
            }
        });

    })(window.angular);


</script>

@endsection



@section('sidebar')

<div id="graphcontrols">
    <div id="sensors"  ng-controller="DtgraphSensorCtrl">

        <ul class="sensors">
            <li ng-repeat="sensor in sensors" ng-click="rowClicked(obj)">
                <input type="checkbox" ng-model="$storage.checkedSensors[sensor.SerialNumber]" name="sensor" value="@{{sensor.SerialNumber}}" />
                <span class="sensorcolor bgcolor@{{$index + 1}}">&nbsp;</span>
                <span title="@{{sensor.description}} (@{{sensor.SerialNumber}})">@{{sensor.name}}</span>
            </li>
        </ul>
    </div>
    <div id="graphmodes"  ng-controller="DtgraphSensorCtrl">
        <input type="checkbox" ng-model="$storage.graphModes.avg" ng-change="graphModeChanged()"/> <span>Avg</span>
        <input type="checkbox" ng-model="$storage.graphModes.min" ng-change="graphModeChanged()"/> <span>Min</span>
        <input type="checkbox" ng-model="$storage.graphModes.max" ng-change="graphModeChanged()"> <span>Max</span>
    </div>
</div>
@endsection


@section('content')
<div id="notices" ng-controller="DtgraphNoticesCtrl" ng-model="notices">@{{notices}}</div>
<svg id="graph" d3angular></svg>
@endsection
