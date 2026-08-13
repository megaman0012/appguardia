@extends('layouts.master')

@section('content')

    <link rel="stylesheet" href="/css/modules/formularios/form053/index.css">
    <script src="/js/modules/formularios/form053/index.js"></script>

    <title>Referencias</title>

    <div class="row">
        <div class="col-sm-12">
            <x-card  :data="[ 'class' => 'card-success card-outline' ]">
                <x-slot name="header">
                    <div class="card-title">Paciente</div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    </div>
                </x-slot>
                <x-slot name="body">
                    <form id="frmNewRf" action="" method="POST">
                        <div class="row">
                            <div class="col-sm-4">
                                <x-input :data="[ 'name' => 'pt_nmb_comp' , 'label' => 'Nombre Completo'    , 'placeholder' => 'Ingrese Nombre Completo', 'readonly'=>'true' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'pt_ape1'     , 'label' => 'Primer Apellido'    , 'placeholder' => 'Ingrese Primer Apellido' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'pt_ape2'     , 'label' => 'Segundo Apellido'   , 'placeholder' => 'Ingrese Segundo Apellido' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'pt_nmb1'     , 'label' => 'Primer Nombre'      , 'placeholder' => 'Ingrese Primer Nombre' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'pt_nmb2'     , 'label' => 'Segundo Nombre'     , 'placeholder' => 'Ingrese Segundo Nombre' ]" />
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-2">
                                <x-input :data="[ 'name' => 'pt_fch_nac'  , 'label' => 'Fecha de Nacimiento', 'class'=>'datepicker', 'placeholder' => 'aaaa-mm-dd', 'type'=> 'text' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'pt_gen_per'  , 'label' => 'Genero', 'placeholder' => 'Seleccione Tipo de Genero', 'type'=> 'select' , 'options' => $generoPersona ]" />
                            </div>
                            <div class="col-sm-3 col-6">
                                <x-input :data="[ 'name' => 'pt_tip_doc'  , 'label' => 'Tipo documento'     , 'placeholder' => 'Seleccione Tipo de Documento', 'type'=> 'select' , 'options' => $documentOptions ]" />
                            </div>
                            <div class="col-sm-3">
                                <x-input :data="[ 'name' => 'pt_documento', 'label' => 'Documento', 'placeholder' => 'Ingrese Numero de Documento']" />
                            </div>
                            <div class="col-sm-2">
                                <button type="button" id="btnBsq" class="btn btn-sm btn-outline-info btn-block mt-4">Busqueda</button>
                            </div>
                        </div>

                    </form>
                </x-slot>
            </x-card>
        </div>

    </div>

    <div class="row">
        <div class="col-sm-12">
            <x-card :data="[ 'class' => 'card-primary card-outline' ]">
                <x-slot name="header">
                    <div class="card-title mr-3 mt-1">Documento</div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    </div>
                </x-slot>
                <x-slot name="body">


                        <div class="row">
                            <div class="col-sm-4 col-12">
                                @php
                                    $optRefDoc='<option value="1">Referencia</option>
                                                <option value="2">Derivacion</option>
                                                <option value="3">Contrareferencia</option>
                                                <option value="4">Referencia Inversa</option>';
                                @endphp
                                <x-input :data="[ 'name' => 'rf_type'  , 'label' => '<b>Tipo de Formulario</b>', 'placeholder' => '', 'type'=> 'select' , 'options' => $optRefDoc ]" />
                            </div>
                        </div>
                        <!--<hr>-->

                    <fieldset class="fs">
                        <legend>Datos Institucionales</legend>
                        <div class="row">
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_org_ent_sys', 'label' => 'Ent. Sistema'    , 'placeholder' => 'Ingrese Nombre Completo', 'readonly'=>'true' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_org_his_cli', 'label' => 'Historia Clinica'    , 'placeholder' => 'Ingrese Historia Clinica' ]" />
                            </div>
                            <div id="dv_rf_est_sal" class="col-sm-4 col-12">
                                <x-input :data="[ 'name' => 'rf_org_est_sal', 'label' => 'Establecimiento de Salud'   , 'placeholder' => 'Ingrese Establecimiento de Salud', 'readonly'=>'true' ]" />
                            </div>
                            <div class="col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_org_est_typ'  , 'label' => 'Tipo', 'placeholder' => '', 'type'=> 'select' , 'options' => $optRefDoc, 'readonly'=>'true' ]" />
                            </div>
                            <div class="section1 col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_org_dis_are'    , 'label' => 'Distrito/Area'     , 'placeholder' => 'Ingrese Distrito/Area', 'readonly'=>'true' ]" />
                            </div>

                            <div class="section2 col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_org_srv'  , 'label' => 'Servicio', 'placeholder' => '', 'type'=> 'select' , 'options' => $servicio ]" />
                            </div>
                            <div class="section2 col-sm-2 col-12">
                                <x-input :data="[ 'name' => 'rf_org_esp'  , 'label' => 'Especialidad', 'placeholder' => '', 'type'=> 'select' , 'options' => $especialidad ]" />
                            </div>

                        </div>
                    </fieldset>
                    <fieldset class="fs">
                        <legend id="change_legend">Refiere o Deriva a:</legend>
                        <div class="row">
                            <div class="col-sm-2">
                                <x-input :data="[ 'name' => 'rf_dst_ent_sys' , 'label' => 'Ent. Sistema'    , 'placeholder' => 'Ingrese Nombre Completo', 'readonly'=>'true' ]" />
                            </div>
                            <div class="col-sm-4">
                                <x-input :data="[ 'name' => 'rf_dst_est_sal'     , 'label' => 'Establecimiento de Salud'   , 'placeholder' => 'Ingrese Segundo Apellido' ]" />
                            </div>
                            <div class="section2 col-sm-2">
                                <x-input :data="[ 'name' => 'rf_dst_est_typ'  , 'label' => 'Tipo', 'placeholder' => '', 'type'=> 'select' , 'options' => $optRefDoc, 'readonly'=>'true' ]" />
                            </div>
                            <div class="section2 col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_dst_dis_are'    , 'label' => 'Distrito/Area'     , 'placeholder' => 'Ingrese Distrito/Area', 'readonly'=>'true' ]" />
                            </div>
                            <div class="section1 col-sm-2 col-6">
                                <x-input :data="[ 'name' => 'rf_dst_srv'  , 'label' => 'Servicio', 'placeholder' => '', 'type'=> 'select' , 'options' => $servicio ]" />
                            </div>
                            <div class="section1 col-sm-2 col-12">
                                <x-input :data="[ 'name' => 'rf_dst_esp'  , 'label' => 'Especialidad', 'placeholder' => '', 'type'=> 'select' , 'options' => $especialidad ]" />
                            </div>
                            <div class="col-sm-2 col-12">
                                <x-input :data="[ 'name' => 'rf_dst_fec'  , 'label' => 'Fecha', 'class'=>'datepicker', 'placeholder' => 'aaaa-mm-dd', 'type'=> 'text' ]" />
                            </div>
                        </div>
                    </fieldset>

                    <div class="row">
                        <div class="section1 col-sm-4 col-12">
                            <x-input :data="[ 'name' => 'rf_motivo'     , 'label' => 'Motivo de la Referencia o Derivacion', 'placeholder' => 'Seleccione Motivo', 'type'=> 'select' , 'options' => $motivoReferencia ]" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <x-input :data="[ 'name' => 'rf_resumen'    , 'label' => 'Resumen del Cuadro Clinico'     , 'placeholder' => 'Ingrese resumen' , 'type'=> 'textarea', 'charlimit' => '500' ] " />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <x-input :data="[ 'name' => 'rf_hallazgo'   , 'label' => 'Hallazgos relevantes de exámenes y procedimientos diagnósticos'     , 'placeholder' => 'Ingrese Hallazgos' , 'type'=> 'textarea', 'charlimit' => '500' ] " />
                        </div>
                    </div>
                    <div class="row">
                        <div class="section2 col-sm-12">
                            <x-input :data="[ 'name' => 'rf_procedimiento', 'label' => 'Tratamiento y procedimientos terapeúticos realizados'     , 'placeholder' => 'Hallazgos' , 'type'=> 'textarea', 'charlimit' => '500' ] " />
                        </div>
                    </div>

                    <fieldset class="fs">
                        <legend class="d-flex justify-content-center align-items-center">
                            <b class="mr-2">Diagnosticos</b>
                            <button type="button" id="btnAddDx" class="btn btn-sm btn-outline-info btn-block"><i class="fas fa-plus"></i></button>
                        </legend>

                        <div id="dxs" class="mb-1">
                            <div class="row" id='dx1'>
                                <div class="col-sm-6 col-12">
                                    <input type="hidden" class="form-control" name="dx_code[]">
                                    <div class="dxcont d-flex align-items-center">
                                        <div class="number">1</div>
                                        <x-input :data="[ 'name' => 'dx_desc[]'  , 'label' => 'Diagnostico'  , 'class'=>'flex-grow-1', 'placeholder' => 'Autocompletado al ingresar 3 caracteres' ]" />
                                    </div>
                                </div>
                                <div class="col-sm-3 col-6">
                                    <x-input :data="[ 'name' => 'dx_cie[]'   , 'label' => 'Cie10'        , 'placeholder' => 'Autocompletado al ingresar 3 caracteres' ]" />
                                </div>
                                <div class="col-sm-3 col-6">
                                    <x-input :data="[ 'name' => 'dx_clasif[]', 'label' => 'Clasificacion', 'placeholder' => 'Seleccione Motivo', 'type'=> 'select' , 'options' => [ '1' => 'Presuntivo','2'=>'Definitivo'] ]" />
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="row">
                        <div class="section2 col-sm-12">
                            <x-input :data="[ 'name' => 'rf_tratamiento', 'label' => 'Tratamiento recomendado a seguir en Establecimientos de Salud de menor nivel de complejidad'     , 'placeholder' => 'Hallazgos' , 'type'=> 'textarea', 'charlimit' => '500' ] " />
                        </div>
                    </div>

                </x-slot>
            </x-card>
        </div>
    </div>

@endsection
