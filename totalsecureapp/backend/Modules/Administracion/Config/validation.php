<?php
return [
    'store_person' => [
        'rules' => [
            'pt_documento' => 'required|string|max:255',
            'pt_tip_doc'   => 'required|string',
            'pt_nmb_comp'  => 'nullable|string|max:255',
            'pt_gen_per'   => 'nullable|in:1,2,3',
            'pt_ape1'      => 'required|string|max:255',
            'pt_ape2'      => 'nullable|string|max:255',
            'pt_nmb1'      => 'required|string|max:255',
            'pt_nmb2'      => 'nullable|string|max:255',
            'pt_fch_nac'   => 'required|date',
            'pt_pais' => 'required|string|max:255',
            //'pt_provincia' => 'required|string|max:255',
            'pt_ciudad'    => 'required|string|max:255',
            'pt_parroquia' => 'required|string|max:255',
            'pt_direccion' => 'required|string|max:255',
        ],

        'messages' => [
            'pt_documento.required' => 'El número de documento es obligatorio.',
            'pt_documento.string'   => 'El número de documento debe ser una cadena de texto.',
            'pt_documento.max'      => 'El número de documento no puede exceder los 255 caracteres.',

            'pt_tip_doc.required' => 'El tipo de documento es obligatorio.',
            'pt_tip_doc.string'   => 'El tipo de documento debe ser una cadena de texto.',

            'pt_nmb_comp.nullable' => 'El nombre completo es opcional.',
            'pt_nmb_comp.string'   => 'El nombre completo debe ser una cadena de texto.',
            'pt_nmb_comp.max'      => 'El nombre completo no puede exceder los 255 caracteres.',

            'pt_gen_per.nullable' => 'El género es opcional.',
            'pt_gen_per.in'       => 'El género seleccionado no es válido.',

            'pt_ape1.required' => 'El primer apellido es obligatorio.',
            'pt_ape1.string'   => 'El primer apellido debe ser una cadena de texto.',
            'pt_ape1.max'      => 'El primer apellido no puede exceder los 255 caracteres.',

            'pt_ape2.nullable' => 'El segundo apellido es opcional.',
            'pt_ape2.string'   => 'El segundo apellido debe ser una cadena de texto.',
            'pt_ape2.max'      => 'El segundo apellido no puede exceder los 255 caracteres.',

            'pt_nmb1.required' => 'El primer nombre es obligatorio.',
            'pt_nmb1.string'   => 'El primer nombre debe ser una cadena de texto.',
            'pt_nmb1.max'      => 'El primer nombre no puede exceder los 255 caracteres.',

            'pt_nmb2.nullable' => 'El segundo nombre es opcional.',
            'pt_nmb2.string'   => 'El segundo nombre debe ser una cadena de texto.',
            'pt_nmb2.max'      => 'El segundo nombre no puede exceder los 255 caracteres.',

            'pt_fch_nac.required' => 'La fecha de nacimiento es obligatoria.',
            'pt_fch_nac.date'     => 'La fecha de nacimiento debe ser una fecha válida.',

            'pt_pais.required' => 'El campo país es obligatorio.',
            'pt_pais.string'   => 'El país debe ser una cadena de texto.',
            'pt_pais.max'      => 'El país no puede exceder los 255 caracteres.',

            /*'pt_provincia.required' => 'La provincia es obligatoria.',
            'pt_provincia.string' => 'La provincia debe ser una cadena de texto.',
            'pt_provincia.max' => 'La provincia no puede exceder los 255 caracteres.',*/

            'pt_ciudad.required' => 'La ciudad es obligatoria.',
            'pt_ciudad.string' => 'La ciudad debe ser una cadena de texto.',
            'pt_ciudad.max' => 'La ciudad no puede exceder los 255 caracteres.',

            'pt_parroquia.required' => 'La parroquia es obligatoria.',
            'pt_parroquia.string' => 'La parroquia debe ser una cadena de texto.',
            'pt_parroquia.max' => 'La parroquia no puede exceder los 255 caracteres.',

            'pt_direccion.required' => 'La dirección es obligatoria.',
            'pt_direccion.string' => 'La dirección debe ser una cadena de texto.',
            'pt_direccion.max' => 'La dirección no puede exceder los 255 caracteres.',
        ]
    ],
];
