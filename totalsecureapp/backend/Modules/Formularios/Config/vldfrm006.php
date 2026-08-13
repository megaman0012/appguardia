<?php

return [

    'getbydoc' => [
        'rules' => [
            'pt_documento' => 'required|string|max:255',
            'pt_tip_doc' => 'required',
        ],
        'messages' => [
            'pt_documento.required' => 'Parametro Documento Es Requerido Para Continuar.',
            'pt_tip_doc.required' => 'Seleccione Tipo Documento Para Continuar.',
        ],
    ],

];
