@extends('layouts.master')

@section('content')

    <script src="/js/modules/administracion/persona.js"></script>

    <x-card type="info" title="Actualizar Contraseña" >
        <x-slot name="header">
            <div>Informacion Personas</div>
        </x-slot>
        <x-slot name="body">
            <div class="row mb-3">
                <div class="col-sm-12">
                    <button type="button" class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#mdlNewPt" >Nueva Persona</button>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <x-table id="dtPersona" class="datatable stripe">
                        <x-slot name="thead">
                            <th>ID</th>
                            <th>Documento</th>
                            <th>Nombres Completos</th>
                            <th>Fecha Nacimiento</th>
                            <th>Pais</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </x-slot>
                    </x-table>
                </div>
            </div>
        </x-slot>
    </x-card>

    <x-modal id="mdlNewPt" title="Informacion Personas" width="700px">
        <x-slot name="body">
            <form id="frmNewPt" action="" method="POST">
                <div class="row">
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_documento', 'label' => 'Documento', 'placeholder' => 'Ingrese Numero de Documento']" />
                    </div>
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_tip_doc'  , 'label' => 'Tipo documento'     , 'placeholder' => 'Seleccione Tipo de Documento', 'type'=> 'select' , 'options' => $documentOptions ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-8">
                        <x-input :data="[ 'name' => 'pt_nmb_comp' , 'label' => 'Nombre Completo'    , 'placeholder' => 'Ingrese Nombre Completo', 'readonly'=>'true' ]" />
                    </div>
                    <div class="col-sm-4">
                        <x-input :data="[ 'name' => 'pt_gen_per'  , 'label' => 'Genero', 'placeholder' => 'Seleccione Tipo de Genero', 'type'=> 'select' , 'options' => $generoPersona ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_ape1'     , 'label' => 'Primer Apellido'    , 'placeholder' => 'Ingrese Primer Apellido' ]" />
                    </div>
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_ape2'     , 'label' => 'Segundo Apellido'   , 'placeholder' => 'Ingrese Segundo Apellido' ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_nmb1'     , 'label' => 'Primer Nombre'      , 'placeholder' => 'Ingrese Primer Nombre' ]" />
                    </div>
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_nmb2'     , 'label' => 'Segundo Nombre'     , 'placeholder' => 'Ingrese Segundo Nombre' ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <x-input :data="[ 'name' => 'pt_fch_nac'  , 'label' => 'Fecha de Nacimiento', 'class'=>'datepicker', 'placeholder' => 'aaaa-mm-dd', 'type'=> 'text' ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_pais', 'label' => 'País'               , 'placeholder' => 'Ingrese País' ]" />
                    </div>
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_provincia', 'label' => 'Provincia'          , 'placeholder' => 'Ingrese Provincia' ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_ciudad'   , 'label' => 'Ciudad'             , 'placeholder' => 'Ingrese Ciudad' ]" />
                    </div>
                    <div class="col-sm-6">
                        <x-input :data="[ 'name' => 'pt_parroquia', 'label' => 'Parroquia'          , 'placeholder' => 'Ingrese Parroquia' ]" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <x-input :data="[ 'name' => 'pt_direccion', 'label' => 'Dirección'          , 'placeholder' => 'Ingrese Dirección' ]" />
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-sm btn-primary">Guardar Informacion</button>
                </div>
            </form>
        </x-slot>
        <x-slot name="footer"></x-slot>
    </x-modal>



@endsection
