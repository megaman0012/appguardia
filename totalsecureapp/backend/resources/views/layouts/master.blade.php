<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}" >
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Core</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="/AdminLTE320/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/AdminLTE320/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/AdminLTE320/plugins/toastr/toastr.css">
    <link rel="stylesheet" href="/AdminLTE320/plugins/jquery-ui/jquery-ui.min.css">

    <link rel="stylesheet" href="/AdminLTE320/plugins/datatables-bs4/css/dataTables.bootstrap4.css">

    <link rel="stylesheet" href="/AdminLTE320/plugins/datatables1/buttons.dataTables.min.css">

    <link rel="stylesheet" href="/css/general.css">
    <link rel="stylesheet" href="/css/components.css">
    <script src="/AdminLTE320/plugins/jquery/jquery.min.js"></script>
    <script src="/AdminLTE320/plugins/jquery-ui/jquery-ui.min.js"></script>
    <script src="/AdminLTE320/plugins/toastr/toastr.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/dataTables.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/dataTables.buttons.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/buttons.flash.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/jszip.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/pdfmake.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/vfs_fonts.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/buttons.html5.min.js"></script>
    <script src="/AdminLTE320/plugins/datatables1/buttons.print.min.js"></script>
    <script src="/js/general.js"></script>

    <script>
        var urlbase = "{{ url('/') }}";
        var current_url = "{{ url()->current() }}";
        var iduser = "{{ Session::get('usuID') }}";
        var nombre_usuario = "{{ Session::get('usuName') }}";
    </script>
</head>
<body class="hold-transition sidebar-mini">

    <div id="asyncLoader" align="center">
        <span>Espere por favor...</span>
        <div class="spinner-border spinner-border-sm text-light" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <div class="wrapper">

        <nav class="main-header navbar navbar-expand navbar-white navbar-light fixed-top">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"></a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
                        <i class="fas fa-th-large"></i>
                    </a>
                </li>
            </ul>
        </nav>

        @php
            $name_url = Route::getFacadeRoot()->current()->uri();
            $rsUrl = Session::get("url");
            $match = $rsUrl->firstWhere('name', $name_url);
            $iconoSect = $match->pr_icono ?? '';
            $descripcion = $match->pr_descripcion ?? '';
            $subdescripcion = $match->pr_subdescripcion ?? '';
        @endphp

        @include('partials._aside_nav', [ 'name_url' => $name_url, 'rsUrl' => $rsUrl ])

        <div class="content-wrapper">

            <section class="content-header" id="stdscpall">
                <div class="container-fluid">
                    <div class="row mb-2 stdscprow" >
                        <div class="col-sm-6">
                            <table id="msdscp">
                                <tr>
                                    <td><h1 class="m-0"><i style="color:#17a2b8;"class="{{ $iconoSect }}"></i> {{ $descripcion }}</h1></td>
                                    <td><small style="bottom: 4px;position: absolute;margin-left: 5px;">{{ $subdescripcion }}</small></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-sm-6 brcum">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="#"><i class="fas fa-tachometer-alt"></i> Dt360</a></li>
                                <li class="breadcrumb-item active">{{ $descripcion }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </section>

        </div>


        <footer class="main-footer">
            <div class="float-right d-none d-sm-block">
                <b>Version</b> 3.2.0
            </div>
            Copyright &copy; {{ date('Y') }} <strong><a href="https://adminlte.io">Tics Hagp</a>.</strong>
        </footer>

        <aside class="control-sidebar control-sidebar-dark">
            <select class="custom-select mb-3 text-light border-0 bg-pink"><option>None Selected</option><option class="bg-primary">Primary</option><option class="bg-warning">Warning</option><option class="bg-info">Info</option><option class="bg-danger">Danger</option><option class="bg-success">Success</option><option class="bg-indigo">Indigo</option><option class="bg-lightblue">Lightblue</option><option class="bg-navy">Navy</option><option class="bg-purple">Purple</option><option class="bg-fuchsia">Fuchsia</option><option class="bg-pink">Pink</option><option class="bg-maroon">Maroon</option><option class="bg-orange">Orange</option><option class="bg-lime">Lime</option><option class="bg-teal">Teal</option><option class="bg-olive">Olive</option></select>
        </aside>

    </div>


<script src="/AdminLTE320/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/AdminLTE320/dist/js/adminlte.min.js"></script>
</body>
</html>
