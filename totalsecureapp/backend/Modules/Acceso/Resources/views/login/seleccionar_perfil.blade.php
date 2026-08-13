@extends('acceso::layouts.master')
@section('content')

    <link rel="stylesheet" href="/css/modules/acceso/seleccionar_perfil.css">
    <script src="/js/modules/acceso/seleccionar_perfil.js"></script>
    <div id="acPerfLgBx" class="login-box">

        <div class="perfil-logo">
            <b>Perfiles</b>
        </div>

        <div id="tittleAp" align="center" > Bienvenid@ {{ Session::get('usuName') }}</div>

        <x-card :data="[ 'class' => 'card-danger card-outline' ]">
            <x-slot name="body">
                <x-table id="acPerfTbl" class="datatable table-striped">
                    <x-slot name="thead">
                        <th align="center">Perfil</th>
                        <th align="center">Descripcion</th>
                        <th align="center">Accion</th>
                    </x-slot>
                    <x-slot name="tbody">
                        {!! $perfil !!}
                    </x-slot>
                </x-table>
            </x-slot>
        </x-card>

        <div align="center" class="mt-4 perfil-footer">
            Copyright © Total Secure {{ date('Y') }}
        </div>

        <div align="center" class="mt-0 perfil-footer">
            <button id="acPerfLgBack" type="button" class="btn btn-sm btn-outline-danger btn-block">Cerrar Sesion</button>
        </div>

    </div>
@stop
