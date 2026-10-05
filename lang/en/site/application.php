<?php

// Marketing site — the public employment application. Spanish in lang/es/site/application.php.
return [
    'meta' => [
        'title' => 'Application for Employment',
        'description' => 'Apply online and join our growing team',
    ],

    'heading' => 'Application for Employment',

    'employment' => [
        'title' => 'Employment Information',
        'confidential' => 'Information collected from respondents is kept strictly confidential.',
        'email_placeholder' => 'Email',
        'address' => 'Address',
        'apartment' => 'Apartment #',
        'city' => 'City',
        'state' => 'State',
        'state_placeholder' => 'Select a state…',
        'zip' => 'Zip',
    ],

    'position' => [
        'position' => 'Position applying for',
        'desired_salary' => 'Desired salary',
        'start_date' => 'When can you start?',
        'start_date_placeholder' => 'Start Date',
        'dob' => 'Date of birth',
        // flatpickr display format for the two date pickers.
        'date_picker_format' => 'F j, Y',
        'transportation' => 'Do you have reliable transportation?',
        'work_at_qcp' => 'Have you worked for Quality Cleaning Plus, Inc. in the last six months?',
        'work_at_qcp_explain' => 'If yes, where?',
        'work_at_qcp_explain_placeholder' => 'where?',
    ],

    'eligibility' => [
        'usa_citizen' => 'Are you a citizen of the United States?',
        'eligible_to_work' => 'If the answer is no, are you eligible to work in the United States?',
        'another_staff_agency' => 'Have you worked for another staffing agency?',
        'non_compete' => 'If the answer is yes, are you under a :term contract?',
        'non_compete_term' => 'non-compete',
        'convicted_felon' => 'Have you ever been convicted of a felony?',
        'felony_conviction' => 'If the answer is yes, please explain.',
        'felony_conviction_placeholder' => 'Explain',
    ],

    'emergency' => [
        'title' => 'Emergency Contact Information',
        'full_name' => 'Full Name',
        'relationship' => 'Relationship',
        'address' => 'Address',
    ],

    'acknowledgement' => 'I certify that my answers are true and correct to the best of my knowledge',
    'submit' => 'Submit Application',

    'errors' => [
        'start_date' => 'Start date field is required',
        'dob' => 'Date of birth field is required',
        'selection' => 'This selection is required',
    ],

    'sidebar' => [
        'application_date' => 'Application Date:',
        // Carbon translatedFormat() pattern for the application date.
        'date_format' => 'M d, Y',
        'position' => 'Position:',
        'location' => 'Location:',
        'various_locations' => 'Various locations',
        'hours' => 'Hours:',
    ],
];
