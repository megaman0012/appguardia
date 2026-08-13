<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/png">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
        <title>{!! config('acceso.name') !!}</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
        <link rel="stylesheet" href="/AdminLTE320/plugins/fontawesome-free/css/all.min.css">
        <link rel="stylesheet" href="/AdminLTE320/dist/css/adminlte.min.css">
        <link rel="stylesheet" href="/AdminLTE320/plugins/toastr/toastr.css">
        <link rel="stylesheet" href="/css/general.css">
        <link rel="stylesheet" href="/css/components.css">
        <script>
            var urlbase = "{!! url('/'); !!}";
        </script>
        <script src="/AdminLTE320/plugins/jquery/jquery.min.js"></script>
        <script src="/AdminLTE320/plugins/jquery-ui/jquery-ui.min.js"></script>
        <script src="/AdminLTE320/plugins/toastr/toastr.min.js"></script>
        <script src="/AdminLTE320/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    </head>
    <body>
        <div class="login-page">
            @yield('content')
        </div>
        <script src="/AdminLTE320/dist/js/adminlte.min.js"></script>
        <script src="/js/general.js"></script>
    </body>
</html>
