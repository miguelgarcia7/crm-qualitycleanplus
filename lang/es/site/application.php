<?php

// Sitio de mercadeo — la solicitud de empleo pública. Inglés en lang/en/site/application.php.
return [
    'meta' => [
        'title' => 'Solicitud de Empleo',
        'description' => 'Presente su solicitud en línea y únase a nuestro equipo en crecimiento',
    ],

    'heading' => 'Solicitud de Empleo',

    'employment' => [
        'title' => 'Información de Empleo',
        'confidential' => 'La información recopilada de los solicitantes se mantiene estrictamente confidencial.',
        'email_placeholder' => 'Correo electrónico',
        'address' => 'Dirección',
        'apartment' => 'Apartamento #',
        'city' => 'Ciudad',
        'state' => 'Estado',
        'state_placeholder' => 'Seleccione un estado…',
        'zip' => 'Código postal',
    ],

    'position' => [
        'position' => 'Puesto que solicita',
        'desired_salary' => 'Salario deseado',
        'start_date' => '¿Cuándo puede empezar?',
        'start_date_placeholder' => 'Fecha de inicio',
        'dob' => 'Fecha de nacimiento',
        // Formato de flatpickr para los dos selectores de fecha ("5 de octubre de 2026").
        'date_picker_format' => 'j \de F \de Y',
        'transportation' => '¿Tiene transporte confiable?',
        'work_at_qcp' => '¿Ha trabajado para Quality Cleaning Plus, Inc. en los últimos seis meses?',
        'work_at_qcp_explain' => 'Si la respuesta es sí, ¿dónde?',
        'work_at_qcp_explain_placeholder' => '¿Dónde?',
    ],

    'eligibility' => [
        'usa_citizen' => '¿Es usted ciudadano de los Estados Unidos?',
        'eligible_to_work' => 'Si la respuesta es no, ¿es elegible para trabajar en los Estados Unidos?',
        'another_staff_agency' => '¿Ha trabajado para otra agencia de personal?',
        'non_compete' => 'Si la respuesta es sí, ¿está usted bajo un contrato de :term?',
        'non_compete_term' => 'no competencia',
        'convicted_felon' => '¿Alguna vez ha sido condenado por un delito grave?',
        'felony_conviction' => 'Si la respuesta es sí, por favor explique.',
        'felony_conviction_placeholder' => 'Explique',
    ],

    'emergency' => [
        'title' => 'Información de Contacto en Caso de Emergencia',
        'full_name' => 'Nombre completo',
        'relationship' => 'Parentesco',
        'address' => 'Dirección',
    ],

    'acknowledgement' => 'Certifico que mis respuestas son verdaderas y correctas a mi leal saber y entender',
    'submit' => 'Enviar Solicitud',

    'errors' => [
        'start_date' => 'La fecha de inicio es obligatoria',
        'dob' => 'La fecha de nacimiento es obligatoria',
        'selection' => 'Esta selección es obligatoria',
    ],

    'sidebar' => [
        'application_date' => 'Fecha de solicitud:',
        // Patrón de Carbon translatedFormat() para la fecha de solicitud ("5 de octubre de 2026").
        'date_format' => 'j \d\e F \d\e Y',
        'position' => 'Puesto:',
        'location' => 'Ubicación:',
        'various_locations' => 'Varias ubicaciones',
        'hours' => 'Horario:',
    ],
];
