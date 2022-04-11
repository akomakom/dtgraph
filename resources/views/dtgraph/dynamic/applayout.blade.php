<html ng-app="dtgraphApp">
    <head>
        <title>DTGraph - @yield('title')</title>
        <style>
            #content {
                position: relative;
                width: 100%;
            }
            #sidebar {
                /*position: absolute;*/
                /*top: 0;*/
                /*bottom: 0;*/
                /*left: 0;*/
                min-width: 180px;
                float: left;
            }
            #main {
                float: left;
                /*position: relative;*/
                /*margin-left: 190px;*/
            }
        </style>

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <script language="JavaScript" src="js/main.js"></script>
        <link rel="stylesheet" href="css/main.css"/>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
<!--        <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.15/angular.min.js"></script>-->
<!--        <script language="JavaScript" src="js/angular/angular.min.js"></script>-->
        <script language="JavaScript" src="js/angular/angular.min.js"></script>
        <script language="JavaScript" src="js/angular/ngStorage.min.js"></script>
<!--        <script language="JavaScript" src="js/angular/angular-route.min.js"></script>-->

        @yield('header')

    </head>
    <body>

        <div id="content">
            <div id="sidebar">
                <h1>Sidebar</h1>


                @yield('sidebar')


            </div>
            <div id="main">
                <h1>Main</h1>

                @yield('content')
            </div>
        </div>




    </body>
</html>