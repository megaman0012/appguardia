<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link rel="stylesheet" href="/css/documentos.css">
    <style>
        .main{ margin-top: 5%; }
        /*.main td:first-child{ width: 70%; }*/
        /*.main tr:first-child{ text-align: center; font-weight: bold;}*/
        .main .tr_cab{
            font-size: 10px;
            color: black;
            vertical-align: text-top;
            text-align: left;
            font-weight: bold;
            background-color:#ccccff;
        }
        .main .tr_sbcab{
            font-size: 0.50em;
            color: black;
            text-align: center;
            font-weight: bold;
            background-color:#ccffcc;
        }
        .main .tr_data{
            font-size: 0.5em;
            color: black;
            text-align: center;
        }
        .main .cell_data{
            background-color:white;
        }
        .main .cell_sbcab{
            font-weight: bold;
            background-color:#ccffcc;
        }
    </style>
</head>
<body>
    <header>
        <table cellpadding='0' cellspacing='0' style='width: 100%;' >
            <tr>
                <td align="left"  style="border:0px solid white; width: 50%; font-size: 15px;">FORMULARIO 006</td>
                <td align="right" style="border:0px solid white; width: 50%;">
                    <img width=200 src='{{asset('images/hagp_name.jpg')}}'>
                </td>
            </tr>
        </table>
    </header>
    <footer>
        <table border=0 cellpadding='0' cellspacing='0' style='width: 100%;' >
            <tr>
                <td style="font-size:8px; font-weight:bold;">SNS-MSP/HCU-form. 006/2021</td>
                <td align="right" style="font-size:8px; font-weight:bold;">Epicrisis ( <span class="pagenum"></span> ) </td>
            </tr>
        </table>
    </footer>

    @php

    @endphp

    <div class="main">
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%;'>
            <tr class="tr_cab">
                <td colspan='5' style='padding:1px 3px;'> &nbsp;A. DATOS DEL ESTABLIMIENTO DE ORIGEN Y USUARIO / PACIENTE </td>
            </tr>
            <tr class="tr_sbcab">
                <td>INSTITUCION DEL SISTEMA</td>
                <td>UNICODIGO</td>
                <td>ESTABLECIMIENTO DE SALUD</td>
                <td>NUMERO DE HISTORIA CLINICA UNICA</td>
                <td>NUMERO DE ARCHIVO</td>
            </tr>
            <tr class="tr_data">
                <td>MSP</td>
                <td>912000</td>
                <td>HOSP ABEL GILBERT</td>
                <td>{{ trim( $patient->mpcedu ?? '' ) }}</td>
                <td>{{ trim( $patient->mpcedu ?? '' ) }}</td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top:-2px;'>
            <tr class="tr_sbcab">
                <td rowspan=2 >PRIMER APELLIDO</td>
                <td rowspan=2 >SEGUNDO APELLIDO</td>
                <td rowspan=2 >PRIMER NOMBRE</td>
                <td rowspan=2 >SEGUNDO NOMBRE</td>
                <td rowspan=2 >SEXO</td>
                <td rowspan=2 >EDAD</td>
                <td colspan=4 >CONDICION EDAD(MARCAR)</td>
            </tr>
            <tr class="tr_sbcab">
                <td>H</td>
                <td>D</td>
                <td>M</td>
                <td>A</td>
            </tr>
            <tr class="tr_data">
                <td>{{ trim( $patient->mpape1 ?? '' ) }}</td>
                <td>{{ trim( $patient->mpape2 ?? '' ) }}</td>
                <td>{{ trim( $patient->mpnom1 ?? '' ) }}</td>
                <td>{{ trim( $patient->mpnom2 ?? '' ) }}</td>
                @php
                    list($edad, $unidad) = $patientage;
                @endphp
                <td>{{ trim( $patient->mpsexo ?? '' ) }}</td>
                <td>{{ $edad }}</td>
                <td>{{ $unidad == 'H' ? 'X' : '' }}</td>
                <td>{{ $unidad == 'D' ? 'X' : '' }}</td>
                <td>{{ $unidad == 'M' ? 'X' : '' }}</td>
                <td>{{ $unidad == 'A' ? 'X' : '' }}</td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td> &nbsp;B. RESUMEN DEL CUADRO CLINICO </td>
            </tr>
            <tr class='tr_data'>
                <td style="height: 260px;"> {{ $cuadro->epidesdet ?? '' }} </td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td> &nbsp;C. EVOLUCION Y COMPLICACIONES </td>
            </tr>
            <tr class='tr_data'>
                <td style="height: 260px;"> {{ $evolucion->epidesdet ?? '' }} </td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td> &nbsp;D. HALLAZGOS RELEVANTES DE EXAMENES Y PROCEDIMIENTOS DIAGNOSTICOS </td>
            </tr>
            <tr class='tr_data'>
                <td style="height: 260px;"> {{ $hallazgos->epidesdet ?? '' }} </td>
            </tr>
        </table>
    </div>

    <p class="break"></p>

    <div class="main">
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%;'>
            <tr class="tr_cab">
                <td colspan='5' style='padding:1px 3px;'> &nbsp;DATOS DEL USUARIO / PACIENTE </td>
            </tr>
            <tr class="tr_sbcab">
                <td>PRIMER APELLIDO</td>
                <td>PRIMER NOMBRE</td>
                <td>EDAD</td>
                <td>NUMERO DE HISTORIA CLINICA UNICA</td>
                <td>NUMERO DE ARCHIVO</td>
            </tr>
            <tr class="tr_data">
                <td>{{ trim( $patient->mpape1 ?? '' ) }}</td>
                <td>{{ trim( $patient->mpnom1 ?? '' ) }}</td>
                <td>{{ $edad }}</td>
                <td>{{ trim( $patient->mpcedu ?? '' ) }}</td>
                <td>{{ trim( $patient->mpcedu ?? '' ) }}</td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td> &nbsp;E. RESUMEN DE TRATAMIENTO Y PROCEDIMIENTOS TERAPEUTICOS </td>
            </tr>
            <tr class="tr_data">
                <td style="height: 150px;"> {{ $tratamiento->epidesdet ?? '' }} </td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td colspan=2> &nbsp;F. INDICACIONES DE ALTA / EGRESO </td>
            </tr>
            <tr class="tr_data">
                <td colspan=2 style="height: 150px;"> {{ $indicaciones->epidesdet ?? '' }} </td>
            </tr>
            <tr class="tr_data">
                <td style="width: 12%;">Proximo Control : </td>
                <td></td>
            </tr>
        </table>

        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td colspan=2> &nbsp;G. DIAGNOSTICO DE ALTA / EGRESO </td>
                <td align='center'> &nbsp;CIE </td>
            </tr>
            <tr class="tr_data">
                <td class="cell_sbcab"> DIAGNOSTICO PRINCIPAL </td>
                <td> {{ $dxprincipal->dx ?? '' }} </td>
                <td> {{ $dxprincipal->cie ?? '' }} </td>
            </tr>
            <tr class="tr_data">
                <td class="cell_sbcab" rowspan=5 style="width:15%; height: 60px;"> DIAGNOSTICO SECUNDARIO </td>
                <td style="width:75%;"> {{ $dxsec1->dx ?? '' }} </td>
                <td> {{ $dxsec1->cie ?? '' }} </td>
            </tr>
            <tr class="tr_data">
                <td> {{ $dxsec2->dx ?? '' }} </td>
                <td> {{ $dxsec2->cie ?? '' }} </td>
            </tr>
            @foreach(range(1, 3) as $i)
                <tr class="tr_data">
                    <td>  </td>
                    <td>  </td>
                </tr>
            @endforeach
            <tr class="tr_data">
                <td class="cell_sbcab"> CAUSA EXTERNA </td>
                <td> {{ $causaexterna->cedetall ?? '' }} </td>
                <td>  </td>
            </tr>
        </table>

        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td colspan=8> &nbsp;H. CONDICION DE ALTA / EGRESO </td>
                <td align='center'> &nbsp;VIVO </td>
                <td align='center' class="cell_data"> {{ $egresavivo->count == 1 ? 'X' : '' }} </td>
                <td align='center'> &nbsp;FALLECIDO </td>
                <td align='center' class="cell_data"> {{ $egresafallecido->count == 1 ? 'X' : '' }} </td>
            </tr>
            <tr class="tr_data">
                <td class="cell_sbcab"> ALTA MEDICA </td>
                <td style="width:3%;"> {{ $altamedica->count == 1 ? 'X' : '' }} </td>
                <td rowspan=2 class="cell_sbcab"> ASINTOMATICO </td>
                <td rowspan=2 style="width:3%;"> {{ $asintomatico->count == 1 ? 'X' : '' }} </td>
                <td rowspan=2 class="cell_sbcab"> DISCAPACIDAD</td>
                <td rowspan=2 style="width:3%;"> {{ $discapacidad->count == 1 ? 'X' : '' }} </td>
                <td rowspan=2 class="cell_sbcab"> RETIRO NO AUTORIZADO </td>
                <td rowspan=2 style="width:3%;"> {{ $retironoautr->count == 1 ? 'X' : '' }} </td>
                <td class="cell_sbcab">DEFUNCION MENOS DE 48 HORAS</td>
                <td style="width:3%;"> {{ $defunmenos48->count == 1 ? 'X' : '' }} </td>
                <td class="cell_sbcab">DIAS DE ESTADIA</td>
                <td style="width:3%;"> {{ $diasestancia->estancia ?? '' }} </td>
            </tr>
            <tr class="tr_data">
                <td class="cell_sbcab"> ALTA VOLUNTARIA </td>
                <td style="width:3%;"> {{ $altvoluntaria->count == 1 ? 'X' : '' }} </td>
                <td class="cell_sbcab">DEFUNCION MAS DE 48 HORAS</td>
                <td style="width:3%;"> {{ $defunmas48->count == 1 ? 'X' : '' }} </td>
                <td class="cell_sbcab">DIAS DE REPOSO</td>
                <td style="width:3%;">  </td>
            </tr>
            <tr class="tr_data">
                <td colspan=12 style="height:25px;"></td>
            </tr>
        </table>

        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab">
                <td colspan=5> &nbsp;I. MEDICOS TRATANTES </td>
            </tr>
            <tr class="tr_sbcab">
                <td colspan=2 style='width:45%; font-size:7px;'> NOMBRES Y APELLIDOS </td>
                <td style='width:10%; font-size:7px;'> ESPECIALIDAD </td>
                <td style='width:25%; font-size:7px;'> SELLO Y NUMERO DE DOCUMENTO DE IDENTIFICACION DEL PROFESIONAL </td>
                <td style='width:20%; font-size:7px;'> PERIODO DE RESPONSABILIDAD </td>
            </tr>

            @foreach(range(1, 4) as $i)
                <tr class="tr_data">
                    <td class="cell_sbcab" style="width:3%;">{{ $i }}</td>
                    @if(isset($responsables[$i - 1]))
                        <td style="width:25%;">{{ $responsables[$i - 1]->mmnomm  }}</td>
                        <td style="width:20%;">{{ $responsables[$i - 1]->menome  }}</td>
                        @php
                            $hashedIdr  = md5( $responsables[$i - 1]->mmcedm ?? '1');
                            $sealPathr = public_path("images/signatures/seal/{$hashedIdr}.jpg");
                            echo $sealPathr;
                        @endphp
                        @if (file_exists($sealPathr))
                            <td style="width:25%;">
                                <img width="150" src="{{ asset('images/signatures/seal/' . $hashedIdr . '.jpg') }}" alt="">
                                {{ $responsables[$i - 1]->mmcedm  }}
                            </td>
                        @else
                            <td style="width:25%;">
                                {{ $responsables[$i - 1]->mmcedm  }}
                            </td>
                        @endif
                        <td style="width:20%;">{{ $responsables[$i - 1]->periodo }}</td>
                    @else
                        <td></td><td></td><td></td><td></td>
                    @endif
                </tr>
            @endforeach
        </table>

        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top: 10px;'>
            <tr class="tr_cab" style="text-align: left;">
                <td colspan="5"> &nbsp;J. DATOS DEL PROFESIONAL RESPONSABLE</td>
            </tr>
            <tr class="tr_sbcab">
                <td>FECHA</td>
                <td>HORA</td>
                <td>PRIMER NOMBRE</td>
                <td>PRIMER APELLIDO</td>
                <td>SEGUNDO APELLIDO</td>
            </tr>
            <tr class="tr_data">
                <td align="center" style="width:15%;"> {{ $responsable->fecha ?? '' }} </td>
                <td align="center" style="width:10%;"> {{ $responsable->hora ?? '' }} </td>
                <td align="center" > {{ $responsable->mnom1 ?? '' }} </td>
                <td align="center" > {{ $responsable->mape1 ?? '' }}  </td>
                <td align="center" > {{ $responsable->mape2 ?? '' }}  </td>
            </tr>
        </table>

        @php
            $hashedId  = md5($responsable->mmcedm ?? '1');
            $signPath = public_path("images/signatures/sign/{$hashedId}.jpg");
            $sealPath = public_path("images/signatures/seal/{$hashedId}.jpg");
        @endphp

        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top:-2px;'>
            <tr class="tr_sbcab">
                <td align="center" style="font-size:6px; width:25%;"><b>NUMERO DE DOCUMENTO DE IDENTIFICACION</b></td>
                <td class="tdbold">FIRMA</td>
                <td class="tdbold">SELLO</td>
            </tr>
            <tr class="tr_data">
                <td align="center" style="width:15%; height:20px;">{{ $responsable->mmcedm ?? '' }}</td>
                <td align="" style="width:35%; height:65px;">
                    @if (file_exists($signPath))
                        <img width="150" src="{{ asset('images/signatures/sign/' . $hashedId . '.jpg') }}" alt="">
                    @endif
                </td>
                <td align="" style="width:35%;">
                    @if (file_exists($sealPath))
                        <img width="150" src="{{ asset('images/signatures/seal/' . $hashedId . '.jpg') }}" alt="">
                    @endif
                </td>
            </tr>
        </table>
        <table border='1' cellpadding='0' cellspacing='0' style='width: 100%; margin-top:-2px;'>
            <tr class="tr_sbcab">
                <td align="center" style="font-size:7px; width:15%;">ELABORADO POR</td>
                <td class='cell_data' style="font-size:7px; width:35%;">{{ $responsable->mmnomm ?? '' }}</td>
                <td align="center" style="font-size:7px; width:15%;">REVISADO POR</td>
                <td class='cell_data' style="font-size:6px; width:35%;">{{ $responsable->mmnomm ?? '' }}</td>
            </tr>
        </table>

    </div>

</body>
