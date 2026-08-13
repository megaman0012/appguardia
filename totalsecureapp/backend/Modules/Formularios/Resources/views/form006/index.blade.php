@extends('layouts.master')

@section('content')

    <link rel="stylesheet" href="/css/modules/formularios/form006/index.css">
    <script src="/js/modules/formularios/form006/index.js"></script>

    <title>Epicrisis</title>

    <div class="row">
        <div class="col-sm-4">
            <x-card :data="[ 'class' => 'card-info card-outline' ]">
                <x-slot name="header">
                    <div class="card-title">Busqueda</div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    </div>
                </x-slot>
                <x-slot name="body">
                    <form id="frmQry" method="POST">
                        <div class="row">
                            <div class="col-sm-12">
                                <x-input :data="[ 'name' => 'pt_documento', 'label' => 'Documento', 'placeholder' => 'Ingrese Numero de Documento' ]" />
                            </div>
                            <div class="col-sm-12">
                                <x-input :data="[ 'name' => 'pt_tip_doc'  , 'label' => 'Tipo documento'     , 'placeholder' => 'Seleccione Tipo de Documento', 'type'=> 'select' , 'options' => $documentOptions ]" />
                            </div>
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn btn-outline-info mt-2 btnquery">Consultar Informacion</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </x-slot>
            </x-card>
        </div>
        <div class="col-sm-8">
            <x-card :data="[ 'class' => 'card-success card-outline' ]">
                <x-slot name="header">
                    <div class="card-title">Lista de Informacion</div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    </div>
                </x-slot>
                <x-slot name="body">
                    <div class="row flex-container">
                        <div class="col-lg-12">
                            <x-table id="dtTable" class="datatable table-striped">
                                <x-slot name="thead">
                                    <th style='width:10%;'>Registro</th>
                                    <th style='width:15%;'>Fecha</th>
                                    <th>Paciente</th>
                                    <th>Medico</th>
                                    <th style='width:5%;'>Documento</th>
                                </x-slot>
                            </x-table>
                        </div>
                    </div>
                </x-slot>
            </x-card>
        </div>
    </div>

@endsection
