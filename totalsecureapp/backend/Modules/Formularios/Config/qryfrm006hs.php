<?php

return [

    'getpatient'    => "SELECT * FROM capbas WHERE mpcedu = ? AND mptdoc = ? LIMIT 1",

    'getallepicrisis'=>"SELECT e.mpcedu, e.mptdoc, e.clapro, e.ingcsc, e.epictvo, e.epifecreg, c.mpnomc, m.mmnomm FROM epiman e, capbas c, maemed1 m
                        WHERE c.mpcedu = e.mpcedu
                        AND c.mptdoc = e.mptdoc
                        AND m.mmcodm = e.epimedcod
                        AND e.mpcedu = ? AND e.mptdoc = ?",

    'cuadroclinico' => "SELECT epidesdet FROM epimandes WHERE mpcedu=? AND mptdoc=? AND ingcsc=? AND epictvo=? AND epidesatr='EPICAM3'",

    'evolucion'     => "SELECT epidesdet FROM epimandes WHERE mpcedu=? AND mptdoc=? AND ingcsc=? AND epictvo=? AND epidesatr='EPICAM9'",

    'hallazgos'     => "SELECT epidesdet FROM epimandes WHERE mpcedu=? AND mptdoc=? AND ingcsc=? AND epictvo=? AND (epidesatr='EPICAM6' or epidesatr='EPICAM10')",

    'tratamiento'   => "SELECT epidesdet FROM epimandes WHERE mpcedu=? AND mptdoc=? AND ingcsc=? AND epictvo=? AND epidesatr='EPICAM11'",

    'indicaciones'  => "SELECT epidesdet FROM epimandes WHERE mpcedu=? AND mptdoc=? AND ingcsc=? AND epictvo=? AND epidesatr='EPICAM13'",

    'dxprincipal'   => "SELECT trim(dmnomb) dx, a.ingsaldx cie FROM ingresos a, maedia b
                        WHERE mpcedu=?
                        AND mptdoc=?
                        AND ingcsc=?
                        AND a.ingsaldx=b.dmcodi",

    'dxsecundario1' => "SELECT trim(dmnomb) dx, a.ingdxsal1 cie FROM ingresos a, maedia b
                        WHERE mpcedu=?
                        AND mptdoc=?
                        AND ingcsc=?
                        AND a.ingdxsal1=b.dmcodi",

    'dxsecundario2' => "SELECT trim(dmnomb) dx, a.ingdxsal2 cie FROM ingresos a, maedia b
                        WHERE mpcedu=?
                        AND mptdoc=?
                        AND ingcsc=?
                        AND a.ingdxsal2=b.dmcodi",

    'causaexterna'  => "SELECT cedetall FROM hccom1 a, maecaue b
                        WHERE hisckey = ?
                        AND histipdoc = ?
                        AND a.hccauext = b.cecodigo
                        ORDER BY hiscsec DESC
                        LIMIT 1",

    'egresavivo'    => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND tffchm='0001-01-01 00:00:00'",

    'egresafallecido'=>"SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND tffchm>'0001-01-01 00:00:00'",

    'altamedica'    => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND (tfmots='AD' OR tfmots='AT' OR tfmots='OM')",

    'altavoluntaria'=> "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND tfmots='SV'",

    'asintomatico'  => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND tfmots = 'A'",

    'discapacidad'  => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND (tfmots = 'DL' OR tfmots = 'DM' OR tfmots = 'DG')",

    'retironoautr'  => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND (tfmots = 'SI' OR tfmots = 'F')",

    'defunmenos48'  => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND tffchm > '0001-01-01 00:00:00' AND tffchm > LOCALTIMESTAMP - INTERVAL '2 days'",

    'defunmas48'    => "SELECT count(*) FROM tmpfac a WHERE tfcedu=? AND tftdoc=? AND tmctving=? AND tffchm > '0001-01-01 00:00:00' AND tffchm < LOCALTIMESTAMP - INTERVAL '2 days'",

    'diasestancia'  => "SELECT (ingfecegr::date - ingfecadm::date)::int estancia FROM ingresos WHERE mpcedu=? AND mptdoc=? AND ingcsc=?",

    'responsables'  => "SELECT count(*), mmnomm, menome, mmcedm,
                        (SELECT min(hisfhorat)::date FROM hccom1 WHERE hisckey=a.hisckey AND hctvin1= a.hctvin1 AND hiscmmed=a.hiscmmed)|| '   ' ||
                        (SELECT max(hisfhorat)::date FROM hccom1 WHERE hisckey=a.hisckey AND hctvin1= a.hctvin1 AND hiscmmed=a.hiscmmed) periodo
                        FROM hccom1 a , maemed1 b, maeesp c
                        WHERE hiscmmed=mmcodm
                        AND hcesp=mecode
                        AND hisckey=?
                        AND histipdoc=?
                        AND hctvin1=?
                        AND fhcindesp<>'EN'
                        AND fhcindesp<>'TR'
                        GROUP BY hisckey, hctvin1, hiscmmed, mmnomm, menome,mmcedm
                        ORDER BY 1 DESC
                        LIMIT 4",

    'responsable'   => "SELECT e.epifecreg::date AS fecha, e.epifecreg::time AS hora, m.mmcedm, m.mmnomm, m.mnom1, m.mape1, m.mape2 FROM epiman e, capbas c, maemed1 m
                        WHERE c.mpcedu = e.mpcedu
                        AND c.mptdoc = e.mptdoc
                        AND m.mmcodm = e.epimedcod
                        AND e.mpcedu = ?
                        AND e.mptdoc = ?
                        AND e.ingcsc = ?
                        AND e.epictvo = ?",

];
