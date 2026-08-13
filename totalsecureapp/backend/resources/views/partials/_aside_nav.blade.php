@php
    $nombre       = Session::get('usuName');
    $randomColor  = '#' . str_pad(dechex(rand(0, 16777215)), 6, '0', STR_PAD_LEFT);
    $primeraLetra = substr( $nombre, 0, 1);
    $arrUrl = explode("/",$name_url);
    list($url1,$url2) = $arrUrl;
    $name_url = $url1."/".$url2;
    $ps_codigo = '';

    $hashedId = md5(Session::get('usuID'));
    $imagePath = public_path("images/photos/{$hashedId}.jpg");
@endphp


<aside class="main-sidebar sidebar-dark-primary elevation-4 fixed-left">

    <a href="#" class="brand-link">
        <img src="{{ asset('images/logo.png') }}" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">Dt360</span>
    </a>

    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">

                @if (file_exists($imagePath))
                    <div class="profile-image-container  img-circle">
                        <img src="{{ asset("images/photos/{$hashedId}.jpg") }}" class="profile-image" alt="User Image">
                    </div>
                @else
                    <div class="profile-name circle-avatar" style="background-color:{{ $randomColor }};">
                        {{ $primeraLetra }}
                    </div>
                @endif

            </div>
            <div class="info">
                <a href="#" class="d-block">{{ $nombre }}</a>
            </div>
        </div>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                @foreach($rsUrl as $key => $value)
                    @if($value->ps_codigo != $ps_codigo)
                        <li id="li_{{ $value->ps_codigo }}" class="nav-item">
                            <a href="#" id="lia_{{ $value->ps_codigo }}" class="nav-link">
                                <i class="nav-icon fas {{ $value->ps_icono }}"></i>
                                <p>
                                    {{  $value->ps_nombre }}
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                @php $ps_codigo = $value->ps_codigo; @endphp
                                @foreach($rsUrl as $key2 => $value2)
                                    @if($value2->ps_codigo == $value->ps_codigo)
                                        <li class="nav-item">
                                            <a id="ullia_{{  $value2->id }}" href="{{   url('/').'/'.$value2->name }}" class="nav-link">
                                                <i class="nav-icon {{ $value2->pr_icono }}"></i>
                                                <p>{{ $value2->pr_descripcion }}</p>
                                            </a>
                                        </li>
                                        @if($name_url == $value2->name)
                                            <script>
                                                $(function(){
                                                    $("#li_{{ $value->ps_codigo }}").addClass('menu-is-opening menu-open');
                                                    $("#lia_{{ $value->ps_codigo }}").addClass('active');
                                                    $("#ullia_{{ $value2->id }}").addClass('active');
                                                });
                                            </script>
                                        @endif
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
                <li class="nav-item" style="border-top: 1px solid #4f5962;margin-top: 12px;">
                    <a href="{{   url('/').'/acceso/seleccionar_perfil' }}" class="nav-link">
                        <i class="nav-icon fas fa-coffee"></i>
                        <p>Cambiar Perfil</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{   url('/').'/acceso/logout' }}" class="nav-link">
                        <i class="nav-icon fas fa-power-off"></i>
                        <p>Cerrar Sesion</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
