@extends('acceso::layouts.master')
@section('content')
    <link rel="stylesheet" href="/css/modules/acceso/login.css">
    <script src="/js/modules/acceso/login.js"></script>
    <div class="login-box">
        <div class='row'>
            <div class='col-sm-6 lfcol'>
                <div class="image-container">
                    <img style="width : 100%;" src="{{asset('/images/acceso-login.jpg')}}" alt="Mi Imagen" />
                    <div class="text-overlay">
                        <span class="neNameInst">Tecnologia y Seguridad</span><br>
                        <span class="neNameOrig"><img style="width : 10%; margin-top: -2px" src="{{asset('/images/logo.png')}}" alt="Mi Imagen" /> Total Secure</span>
                    </div>
                </div>
            </div>
            <div class='col-sm-6 rgcol'>
                <div class='row rgContent'>
                    <div class='col-sm-12'>
                        <div class='row loginTitle'>
                            Modulo de Control
                        </div>
                        <div class='row loginDescp'>
                            Inicie sesion para acceder a su cuenta.
                        </div>
                        <div class='row'>
                            <form id="aclg" class='frmLogin'>
                                <x-input :data="[ 'name' => 'usu_cedula', 'label' => 'Identificacion', 'placeholder' => 'Ingrese Identificacion' ]" />
                                <x-input :data="[ 'name' => 'password', 'label' => 'Contraseña', 'placeholder' => 'Ingrese su contraseña', 'type'=>'password' ]" />
                                <div align="right" class="form-group">
                                    <a href="#" data-toggle="modal" data-target="#mdlChgKey" class='lnkFgtPas'>Olvido su contraseña ?</a>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class='btn btn-secondary btnSbmt' >Iniciar Sesión</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-modal id="mdlChgKey" title="Actualizar Contraseña" >
        <x-slot name="body">
            <span class="descChgKey">Usted recibirá un mensaje en su correo institucional para proceder con la actualizacion de la contraseña</span>
            <form class="form-signin" id="formpass" name="formpass" method="POST">
                <x-input :data="[ 'name' => 'cedula2', 'label' => 'Identificacion', 'placeholder' => 'Ingrese su identificacion' ]" />
            </form>
        </x-slot>
        <x-slot name="footer">
            <button type="button" id="btnEnviar" class="btn btn-sm btn-info">Enviar Solicitud</button>
        </x-slot>
    </x-modal>

@stop
