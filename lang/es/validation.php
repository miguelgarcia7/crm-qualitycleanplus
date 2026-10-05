<?php

// Spanish validation messages for the public marketing forms under /es
// (marketing-site-audit.md D1). The back office stays English, so this covers
// the rules those forms use rather than every rule Laravel ships.
return [
    'accepted' => 'Debe aceptar :attribute.',
    'array' => 'El campo :attribute debe ser una lista.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
        'file' => 'El archivo :attribute no debe pesar más de :max kilobytes.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',

    'attributes' => [
        // Contact forms
        'contact_first_name' => 'nombre',
        'contact_last_name' => 'apellido',
        'contact_email' => 'correo electrónico',
        'contact_phone' => 'teléfono',
        'contact_company' => 'empresa',
        'contact_address' => 'dirección',
        'contact_city' => 'ciudad',
        'contact_state' => 'estado',
        'contact_zip' => 'código postal',
        'contact_inquiry_type' => 'tipo de consulta',
        'contact_call_back_time' => 'mejor hora para llamarle',
        'contact_message' => 'mensaje',

        // Job application
        'first_name' => 'nombre',
        'middle_name' => 'segundo nombre',
        'last_name' => 'apellido',
        'second_last_name' => 'segundo apellido',
        'email' => 'correo electrónico',
        'phone' => 'teléfono',
        'address' => 'dirección',
        'apartment_number' => 'número de apartamento',
        'city' => 'ciudad',
        'state' => 'estado',
        'zip' => 'código postal',
        'position' => 'puesto',
        'desired_salary' => 'salario deseado',
        'start_date' => 'fecha de inicio',
        'dob' => 'fecha de nacimiento',
        'transportation' => 'transporte',
        'work_at_qcp' => 'trabajo previo en Quality Cleaning Plus',
        'usa_citizen' => 'ciudadanía estadounidense',
        'eligible_to_work' => 'permiso para trabajar',
        'another_staff_agency' => 'otra agencia de personal',
        'convicted_felon' => 'antecedentes penales',
        'acknowledgement' => 'la declaración',
        'full_name' => 'nombre del contacto de emergencia',
        'emergency_phone' => 'teléfono de emergencia',
        'relationship' => 'parentesco',
        'full_address' => 'dirección del contacto de emergencia',
    ],
];
