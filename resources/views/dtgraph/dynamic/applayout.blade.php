<html>
    <head>
        <title>App Name - @yield('title')</title>
        <style>
            #content {
                position: relative;
                width: 100%;
            }
            #sidebar {
                position: absolute;
                top: 0;
                bottom: 0;
                left: 0;
                width: 240px;
            }
            #main {
                position: relative;
                margin-left: 250px;
            }
        </style>

        <script language="JavaScript" src="js/main.js"></script>

    </head>
    <body>

        <div id="content">
            <div id="sidebar">
                <h1>Sidebar</h1>

                @section('sidebar')
                This is the master sidebar.
                @show


            </div>
            <div id="main">
                <h1>Main</h1>

                @yield('content')
            </div>
        </div>




    </body>
</html>