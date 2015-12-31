@extends('dtgraph.dynamic.applayout')


@section('content')


<script src="//d3js.org/d3.v3.min.js"></script>
<script>

    var margin = {top: 20, right: 20, bottom: 30, left: 50},
        width = 960 - margin.left - margin.right,
        height = 500 - margin.top - margin.bottom;

    var parseDate = d3.time.format("%d-%b-%y").parse;

    var x = d3.time.scale()
        .range([0, width]);

    var y = d3.scale.linear()
        .range([height, 0]);

    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom");

    var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left");

    var line = d3.svg.line()
        .x(function(d) { return x(d.time); })
        .y(function(d) { return y(d.temp); });

    var svg = d3.select("body").append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");


    d3.json("api/reading/10AD1E370008008B?start=1450055586&end=1451058001", function(error, data) {
        if (error) throw error;
        console.log(data);

        data = data.data;

        data.forEach(function(d) {
            d.time= new Date(d.time * 1000);
                // parseDate(d.time);
            d.temp = +d.temp;
        });

        x.domain(d3.extent(data, function(d) { return d.time; }));
        y.domain(d3.extent(data, function(d) { return d.temp; }));

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

        svg.append("path")
            .datum(data)
            .attr("class", "line")
            .attr("d", line);
    });

</script>


@endsection
